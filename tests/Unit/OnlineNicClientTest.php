<?php

namespace Tests\Unit;

use App\Domain\Registrar\DTOs\DomainPriceQuery;
use App\Domain\Registrar\DTOs\DomainRegistrationData;
use App\Domain\Registrar\DTOs\RenewDomainData;
use App\Domain\Registrar\DTOs\RequestTransferData;
use App\Domain\Registrar\DTOs\TransferLockData;
use App\Domain\Registrar\DTOs\UpdateNameserversData;
use App\Integrations\OnlineNic\Commands\CancelRegTransferCommand;
use App\Integrations\OnlineNic\Commands\CreateDomainCommand;
use App\Integrations\OnlineNic\Commands\GetAuthCodeCommand;
use App\Integrations\OnlineNic\Commands\QueryRegTransferCommand;
use App\Integrations\OnlineNic\Commands\RenewDomainCommand;
use App\Integrations\OnlineNic\Commands\RequestRegTransferCommand;
use App\Integrations\OnlineNic\Commands\UpdateDomainDnsCommand;
use App\Integrations\OnlineNic\Commands\UpdateDomainStatusCommand;
use App\Integrations\OnlineNic\Contracts\OnlineNicCommand;
use App\Integrations\OnlineNic\Contracts\OnlineNicTransport;
use App\Integrations\OnlineNic\Exceptions\ProviderAmbiguousResponse;
use App\Integrations\OnlineNic\Exceptions\ProviderAuthenticationFailed;
use App\Integrations\OnlineNic\OnlineNicAuthenticator;
use App\Integrations\OnlineNic\OnlineNicClient;
use App\Integrations\OnlineNic\OnlineNicRegistrarGateway;
use App\Integrations\OnlineNic\OnlineNicTldResolver;
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

    public function test_renewal_commands_use_documented_operation_payload_and_checksum(): void
    {
        $command = new RenewDomainCommand(new RenewDomainData('example.com', 2), 0);
        $auth = new OnlineNicAuthenticator('123', 'secret');

        $this->assertSame('RenewDomain', $command->action());
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com', 'period' => 2], $command->payload());
        $this->assertSame(md5('123'.md5('secret').'tx'.'renewdomain'.'0'.'example.com'.'2'), $auth->requestChecksum('tx', $command->action(), $command->payload()));

        $transport = new FakeOnlineNicTransport([
            $this->response('Greeting'), $this->response('Logged in'),
            $this->response('Price', '<data name="price">8.59</data>'),
            $this->response('Renewed', '<data name="domain">example.com</data><data name="exDate">2029-09-22</data>'),
        ]);
        $gateway = new OnlineNicRegistrarGateway($this->client($transport), new OnlineNicTldResolver);
        $this->assertSame('8.59', $gateway->getDomainPrice(new DomainPriceQuery('example.com', 'renewal', 2))->amount);
        $this->assertSame('2029-09-22', $gateway->renewDomain(new RenewDomainData('example.com', 2), 'tx')->expiresAt);
        $this->assertStringContainsString('<param name="op">renew</param>', $transport->writes[1]);
        $this->assertStringContainsString('<action>RenewDomain</action>', $transport->writes[2]);
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

    public function test_security_commands_use_documented_actions_parameters_and_checksums(): void
    {
        $auth = new OnlineNicAuthenticator('123', 'secret');
        $lock = new UpdateDomainStatusCommand(new TransferLockData('example.com', true), 0);
        $unlock = new UpdateDomainStatusCommand(new TransferLockData('example.com', false), 0);
        $code = new GetAuthCodeCommand('example.com', 0);

        $this->assertSame('UpdateDomainStatus', $lock->action());
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com', 'addstatus' => 'clientTransferProhibited'], $lock->payload());
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com', 'remstatus' => 'clientTransferProhibited'], $unlock->payload());
        $this->assertSame(md5('123'.md5('secret').'tx'.'updatedomainstatus'.'0'.'example.com'), $auth->requestChecksum('tx', $lock->action(), $lock->payload()));
        $this->assertSame('GetAuthcode', $code->action());
        $this->assertSame(md5('123'.md5('secret').'tx'.'getauthcode'.'0'.'example.com'), $auth->requestChecksum('tx', $code->action(), $code->payload()));
    }

    public function test_transfer_commands_use_only_documented_fields_and_checksums(): void
    {
        $auth = new OnlineNicAuthenticator('123', 'secret');
        $commands = [new RequestRegTransferCommand(new RequestTransferData('example.com'), 0), new QueryRegTransferCommand('example.com', 0), new CancelRegTransferCommand('example.com', 0)];
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com', 'mailway' => 'Off'], $commands[0]->payload());
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com'], $commands[1]->payload());
        $this->assertSame(['domaintype' => 0, 'domain' => 'example.com'], $commands[2]->payload());
        foreach ($commands as $command) {
            $this->assertSame(md5('123'.md5('secret').'tx'.strtolower($command->action()).'0'.'example.com'), $auth->requestChecksum('tx', $command->action(), $command->payload()));
        }
        $this->assertArrayNotHasKey('password', $commands[0]->payload());
        $this->assertSame('pending', OnlineNicRegistrarGateway::normalizeTransferStatus('pendingTransfer'));
        $this->assertSame('completed', OnlineNicRegistrarGateway::normalizeTransferStatus('transferSuccessfully'));
        $this->assertSame('failed', OnlineNicRegistrarGateway::normalizeTransferStatus('clientRejected'));
        $this->assertSame('cancelled', OnlineNicRegistrarGateway::normalizeTransferStatus('clientCanceled; client canceled'));
        $this->assertSame('action_required', OnlineNicRegistrarGateway::normalizeTransferStatus('new-provider-state'));
    }

    public function test_domain_info_extra_normalizes_transfer_lock_and_auth_code(): void
    {
        $transport = new FakeOnlineNicTransport([
            $this->response('Greeting'), $this->response('Logged in'),
            $this->response('Info', '<data name="domain">example.com</data><data name="dns">ns1.example.net</data><data name="dns">ns2.example.net</data>'),
            $this->response('Extra', '<data name="status">clientTransferProhibited</data>'),
            $this->response('Auth', '<data name="domain">example.com</data><data name="password">secret-epp</data>'),
        ]);
        $gateway = new OnlineNicRegistrarGateway($this->client($transport), new OnlineNicTldResolver);

        $this->assertTrue($gateway->getDomainInfo('example.com')->transferLocked);
        $this->assertSame('secret-epp', $gateway->getAuthCode('example.com')->authCode);
        $this->assertStringContainsString('<action>InfoDomainExtra</action>', $transport->writes[2]);
        $this->assertStringContainsString('<action>GetAuthcode</action>', $transport->writes[3]);
    }

    public function test_documented_ok_status_is_unlocked_and_missing_status_is_unknown(): void
    {
        foreach ([['<data name="status">ok</data>', false], ['', null]] as [$extra, $expected]) {
            $transport = new FakeOnlineNicTransport([$this->response('Greeting'), $this->response('Logged in'), $this->response('Info', '<data name="domain">example.com</data>'), $this->response('Extra', $extra)]);
            $info = (new OnlineNicRegistrarGateway($this->client($transport), new OnlineNicTldResolver))->getDomainInfo('example.com');
            $this->assertSame($expected, $info->transferLocked);
        }
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

    private function response(string $message, string $data = ''): string
    {
        return '<response><code>1000</code><msg>'.$message.'</msg><value>none</value><resData>'.$data.'</resData><cltrid>tx</cltrid><svtrid>srv</svtrid></response>';
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
