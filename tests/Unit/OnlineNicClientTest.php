<?php

namespace Tests\Unit;

use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Commands\CreateDomainCommand;
use App\Integrations\OnlineNic\Commands\UpdateDomainDnsCommand;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;
use App\Integrations\OnlineNic\Contracts\OnlineNicTransport;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderAuthenticationFailed;
use App\Integrations\OnlineNic\OnlineNicAuthenticator;
use App\Integrations\OnlineNic\OnlineNicClient;
use App\Integrations\OnlineNic\OnlineNicTransactionIdGenerator;
use App\Integrations\OnlineNic\Xml\OnlineNicResponseParser;
use App\Integrations\OnlineNic\Xml\OnlineNicXmlBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OnlineNicClientTest extends TestCase
{
    public function test_checksum_is_deterministic_and_uses_documented_login_formula(): void
    {
        $auth = new OnlineNicAuthenticator('123', 'secret');

        $this->assertSame('4db8f0fb0a05d58bf8507fedbf036727', $auth->requestChecksum('abc', 'Login', ['clid' => '123']));
        $this->assertSame($auth->requestChecksum('abc', 'Login'), $auth->requestChecksum('abc', 'Login'));
    }

    public function test_write_checksums_follow_contact_and_com_formulas(): void
    {
        $auth = new OnlineNicAuthenticator('123', 'secret');
        $prefix = '123'.md5('secret').'tx';
        $this->assertSame(md5($prefix.'crtcontact'.'Alice'.'Example'.'alice@example.com'), $auth->requestChecksum('tx', 'CreateContact', ['domaintype' => 0, 'name' => 'Alice', 'org' => 'Example', 'email' => 'alice@example.com', 'password' => 'ignored']));
        $this->assertSame(md5($prefix.'createdomain'.'0'.'example.com'.'2'.'ns1.example.net'.'ns2.example.net'.'r'.'a'.'t'.'b'.'password'), $auth->requestChecksum('tx', 'CreateDomain', ['domaintype' => 0, 'mltype' => 0, 'domain' => 'example.com', 'period' => 2, 'dns' => ['ns1.example.net', 'ns2.example.net'], 'registrant' => 'r', 'tech' => 't', 'billing' => 'b', 'admin' => 'a', 'password' => 'password']));
    }

    public function test_create_domain_command_maps_com_order_data(): void
    {
        $command = new CreateDomainCommand(new DomainRegistrationData('example.com', 2, ['ns1.example.net', 'ns2.example.net'], ['registrant' => 'r', 'administrative' => 'a', 'technical' => 't', 'billing' => 'b'], 'password'));

        $this->assertSame('CreateDomain', $command->action());
        $this->assertSame(['domaintype' => 0, 'mltype' => 0, 'domain' => 'example.com', 'period' => 2, 'dns' => ['ns1.example.net', 'ns2.example.net'], 'registrant' => 'r', 'tech' => 't', 'billing' => 'b', 'admin' => 'a', 'password' => 'password'], $command->payload());
    }

    public function test_update_nameservers_uses_documented_action_payload_and_checksum(): void
    {
        $data = new UpdateNameserversData('example.com', [' NS1.Example.NET ', 'ns2.example.net']);
        $command = new UpdateDomainDnsCommand($data, 0);
        $auth = new OnlineNicAuthenticator('123', 'secret');

        $this->assertSame('UpdateDomainDns', $command->action());
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com', 'nameserver' => ['ns1.example.net', 'ns2.example.net']], $command->payload());
        $this->assertSame(md5('123'.md5('secret').'tx'.'updatedomaindns'.'0'.'example.com'), $auth->requestChecksum('tx', $command->action(), $command->payload()));
        $xml = (new OnlineNicXmlBuilder)->build('domain', $command->action(), $command->payload(), 'tx', 'checksum');
        $this->assertSame(2, substr_count($xml, '<param name="nameserver">'));
        $this->assertStringNotContainsString('<param name="A">', $xml);
    }

    public function test_transaction_ids_are_unique_and_provider_safe(): void
    {
        $generator = new OnlineNicTransactionIdGenerator;
        $first = $generator->generate();
        $second = $generator->generate();

        $this->assertMatchesRegularExpression('/^codex-[a-f0-9]{24}$/', $first);
        $this->assertNotSame($first, $second);
    }

    public function test_builder_escapes_payload_and_parser_normalizes_response(): void
    {
        $xml = (new OnlineNicXmlBuilder)->build('domain', 'Dummy', ['value' => 'A&B <C>'], 'tx-1', 'hash');
        $this->assertStringContainsString('A&amp;B &lt;C&gt;', $xml);

        $response = (new OnlineNicResponseParser)->parse(<<<'XML'
<response><category>domain</category><action>Dummy</action><code>1001</code><msg>Pending</msg><value>no value</value><resData><data name="domain">example.com</data><data name="dns">ns1.example</data><data name="dns">ns2.example</data></resData><cltrid>tx-1</cltrid><svtrid>srv-1</svtrid></response>
XML);
        $this->assertSame('pending', $response->providerStatus);
        $this->assertSame(['ns1.example', 'ns2.example'], $response->data['dns']);
        $this->assertSame('srv-1', $response->svtrid);
    }

    public function test_login_is_required_and_ambiguous_read_is_not_retried(): void
    {
        $transport = new FakeOnlineNicTransport([
            '<response><code>1000</code><msg>Greeting</msg><cltrid></cltrid><svtrid>greet</svtrid><resData/></response>',
            '<response><code>1000</code><msg>Logged in</msg><cltrid>login</cltrid><svtrid>srv-login</svtrid><resData/></response>',
        ]);
        $client = $this->client($transport);
        $this->expectException(ProviderAuthenticationFailed::class);
        $client->execute(new DummyCommand);
    }

    public function test_write_then_read_failure_becomes_ambiguous_and_is_sent_once(): void
    {
        $transport = new FakeOnlineNicTransport([
            '<response><code>1000</code><msg>Greeting</msg><cltrid></cltrid><svtrid>greet</svtrid><resData/></response>',
            '<response><code>1000</code><msg>Logged in</msg><cltrid>login</cltrid><svtrid>srv-login</svtrid><resData/></response>',
        ], true);
        $client = $this->client($transport);
        $client->connect();
        $client->login();

        try {
            $client->execute(new DummyCommand);
            self::fail('Expected ambiguous response.');
        } catch (ProviderAmbiguousResponse) {
            $this->assertCount(2, $transport->writes);
        }
    }

    public function test_malformed_response_after_write_is_ambiguous(): void
    {
        $success = '<response><code>1000</code><msg>OK</msg><cltrid>x</cltrid><svtrid>srv</svtrid><resData/></response>';
        $transport = new FakeOnlineNicTransport([$success, $success, 'not xml']);
        $client = $this->client($transport);
        $client->connect();
        $client->login();

        $this->expectException(ProviderAmbiguousResponse::class);
        $client->execute(new DummyCommand);
    }

    public function test_session_limit_reconnects_before_next_command(): void
    {
        $success = '<response><code>1000</code><msg>OK</msg><cltrid>x</cltrid><svtrid>srv</svtrid><resData/></response>';
        $transport = new FakeOnlineNicTransport([$success, $success, $success, $success, $success, $success]);
        $client = new OnlineNicClient($transport, new OnlineNicAuthenticator('123', 'secret'), '123', 'secret', requestLimit: 1);
        $client->connect();
        $client->login();
        $client->execute(new DummyCommand);
        $client->execute(new DummyCommand);

        $this->assertCount(4, $transport->writes);
        $this->assertSame(2, $transport->connects);
    }

    private function client(FakeOnlineNicTransport $transport): OnlineNicClient
    {
        return new OnlineNicClient($transport, new OnlineNicAuthenticator('123', 'secret'), '123', 'secret');
    }
}

final class DummyCommand implements OnlineNicCommand
{
    public function category(): string
    {
        return 'domain';
    }

    public function action(): string
    {
        return 'Dummy';
    }

    public function payload(): array
    {
        return ['domain' => 'example.com'];
    }
}

final class FakeOnlineNicTransport implements OnlineNicTransport
{
    /** @var list<string> */
    public array $writes = [];

    public int $connects = 0;

    public function __construct(private array $responses, private bool $failReads = false) {}

    public function connect(): void
    {
        $this->connects++;
    }

    public function write(string $payload): void
    {
        $this->writes[] = $payload;
    }

    public function read(): string
    {
        if ($this->failReads && count($this->writes) >= 2) {
            throw new RuntimeException('read failed');
        }

        return array_shift($this->responses) ?? throw new RuntimeException('no response');
    }

    public function close(): void {}
}
