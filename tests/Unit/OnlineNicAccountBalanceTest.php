<?php

namespace Tests\Unit;

use App\Integrations\OnlineNic\Commands\GetAccountBalanceCommand;
use App\Integrations\OnlineNic\Contracts\OnlineNicTransport;
use App\Integrations\OnlineNic\OnlineNicAccountService;
use App\Integrations\OnlineNic\OnlineNicAuthenticator;
use App\Integrations\OnlineNic\OnlineNicClient;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class OnlineNicAccountBalanceTest extends TestCase
{
    public function test_documented_command_checksum_and_cached_balance(): void
    {
        Cache::flush();
        $command = new GetAccountBalanceCommand;
        $auth = new OnlineNicAuthenticator('123', 'secret');
        $this->assertSame('account', $command->category());
        $this->assertSame('GetAccountBalance', $command->action());
        $this->assertSame(md5('123'.md5('secret').'tx'.'getaccountbalance'), $auth->requestChecksum('tx', $command->action(), []));

        $transport = new AdminBalanceTransport([
            $this->response('Greeting'), $this->response('Logged in'), $this->response('Balance', '<data name="balance">130265</data>'),
        ]);
        config(['onlinenic.account_currency' => 'USD']);
        $service = new OnlineNicAccountService(new OnlineNicClient($transport, $auth, '123', 'secret'));
        $first = $service->balance();
        $second = $service->balance();

        $this->assertSame('130265', $first['amount']);
        $this->assertSame('USD', $first['currency']);
        $this->assertSame($first, $second);
        $this->assertCount(2, $transport->writes);
    }

    private function response(string $message, string $data = ''): string
    {
        return '<response><code>1000</code><msg>'.$message.'</msg><value>none</value><resData>'.$data.'</resData><cltrid>tx</cltrid><svtrid>srv</svtrid></response>';
    }
}

final class AdminBalanceTransport implements OnlineNicTransport
{
    public array $writes = [];

    public function __construct(private array $responses) {}

    public function connect(): void {}

    public function write(string $payload): void
    {
        $this->writes[] = $payload;
    }

    public function read(): string
    {
        return array_shift($this->responses);
    }

    public function close(): void {}
}
