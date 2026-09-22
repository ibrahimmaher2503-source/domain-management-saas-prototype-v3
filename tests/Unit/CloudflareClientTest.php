<?php

namespace Tests\Unit;

use App\Domain\Dns\Exceptions\AmbiguousDnsWrite;
use App\Domain\Dns\Exceptions\DnsConflict;
use App\Integrations\Cloudflare\CloudflareClient;
use App\Integrations\Cloudflare\CloudflareDnsProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareClientTest extends TestCase
{
    public function test_token_transport_and_structured_records(): void
    {
        config(['cloudflare.api_token' => 'test-secret', 'cloudflare.account_id' => 'account-1']);
        Http::fake(function ($request) {
            $this->assertSame('Bearer test-secret', $request->header('Authorization')[0]);
            $this->assertStringStartsWith('https://api.cloudflare.com/client/v4/', $request->url());
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/zones')) {
                $this->assertSame('account-1', $request['account']['id']);

                return Http::response(['success' => true, 'result' => ['id' => 'zone-1', 'name' => 'example.com', 'status' => 'pending', 'name_servers' => ['amy.ns.cloudflare.com', 'bob.ns.cloudflare.com']]]);
            }
            $this->assertSame(['priority' => 1, 'weight' => 2, 'port' => 443, 'target' => 'srv.example.net'], $request['data']);

            return Http::response(['success' => true, 'result' => ['id' => 'record-1', ...$request->data(), 'proxiable' => false]]);
        });
        $provider = new CloudflareDnsProvider(new CloudflareClient);
        $this->assertSame('zone-1', $provider->createZone('example.com')['id']);
        $record = $provider->createRecord('zone-1', ['type' => 'SRV', 'name' => '_sip.example.com', 'ttl' => 1, 'data' => ['priority' => 1, 'weight' => 2, 'port' => 443, 'target' => 'srv.example.net']]);
        $this->assertSame('record-1', $record['id']);
        $this->assertArrayNotHasKey('zone_id', $record);
    }

    public function test_conflict_is_safe(): void
    {
        config(['cloudflare.api_token' => 'test-secret']);
        Http::fake(['*' => Http::response(['success' => false, 'errors' => [['code' => 81053, 'message' => 'secret provider details']]], 400)]);
        try {
            (new CloudflareClient)->request('POST', '/zones/zone-1/dns_records', []);
            $this->fail('Expected conflict.');
        } catch (DnsConflict $exception) {
            $this->assertStringNotContainsString('secret provider details', $exception->getMessage());
        }
    }

    public function test_server_failure_is_ambiguous(): void
    {
        config(['cloudflare.api_token' => 'test-secret']);
        Http::fake(['*' => Http::response(['success' => false], 503)]);
        $this->expectException(AmbiguousDnsWrite::class);
        (new CloudflareClient)->request('PATCH', '/zones/zone-1/dns_records/record-1', []);
    }
}
