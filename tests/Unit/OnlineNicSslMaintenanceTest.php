<?php

namespace Tests\Unit;

use App\Integrations\OnlineNic\OnlineNicAuthenticator;
use App\Integrations\OnlineNic\Ssl\SslCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OnlineNicSslMaintenanceTest extends TestCase
{
    public static function commands(): array
    {
        return [
            ['Cancel', ['orderId' => '42']],
            ['ChangeApproverEmail', ['orderId' => '42', 'approverEmail' => 'admin@example.com']],
            ['ResendApproverEmail', ['orderId' => '42']],
            ['Reissue', ['orderId' => '42', 'CSR' => 'csr-data']],
            ['ResendFulfillmentEmail', ['orderId' => '42']],
        ];
    }

    #[DataProvider('commands')]
    public function test_ssl_maintenance_command_and_checksum_mapping(string $action, array $params): void
    {
        $command = new SslCommand($action, $params);
        $auth = new OnlineNicAuthenticator('123', 'secret');

        $this->assertSame('ssl', $command->category());
        $this->assertSame($action, $command->action());
        $this->assertSame($params, $command->payload());
        $this->assertSame(md5('123'.md5('secret').'tx'.$action.'42'), $auth->requestChecksum('tx', $action, $params));
    }
}
