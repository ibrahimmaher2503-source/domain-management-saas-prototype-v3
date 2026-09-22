<?php

namespace App\Domain\Domains\Services;

use App\Domain\Domains\Exceptions\DomainImportException;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Models\AdminAuditLog;
use App\Models\Domain;
use App\Models\RegistrarOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

final class ImportDomainForCustomer
{
    public function __construct(private readonly RegistrarGateway $registrar, private readonly DomainSearchService $domains) {}

    /** @return array{domain: Domain, customer: User, activation_link: string|null} */
    public function import(string $input, ?User $customer, ?array $newCustomer, User $admin, ?string $note = null): array
    {
        $name = $this->domains->normalize($input);
        if (Domain::query()->whereRaw('lower(name) = ?', [$name])->exists()) {
            throw new DomainImportException('This domain already exists locally and cannot be reassigned.');
        }
        if ($customer?->is_admin) {
            throw new DomainImportException('An administrator cannot own customer domains.');
        }

        try {
            $info = $this->registrar->getDomainInfo($name);
        } catch (Throwable) {
            throw new DomainImportException('This domain is not available for full management under the configured OnlineNIC account.');
        }
        if (strcasecmp($info->domain, $name) !== 0) {
            throw new DomainImportException('This domain is not available for full management under the configured OnlineNIC account.');
        }

        $activationLink = null;
        [$domain, $customer] = DB::transaction(function () use ($info, $name, $customer, $newCustomer, $admin, $note, &$activationLink): array {
            $customer = $customer ? User::query()->lockForUpdate()->findOrFail($customer->id) : User::create([
                'name' => $newCustomer['name'],
                'email' => $newCustomer['email'],
                'password' => Hash::make(Str::random(64)),
                'activation_pending' => true,
            ]);
            if ($customer->is_admin) {
                throw new DomainImportException('An administrator cannot own customer domains.');
            }
            if ($newCustomer !== null) {
                AdminAuditLog::create(['admin_user_id' => $admin->id, 'action' => 'customer.created', 'resource_type' => 'customer', 'resource_id' => $customer->id, 'safe_metadata' => ['customer_id' => $customer->id]]);
                $token = Password::broker()->createToken($customer);
                $activationLink = URL::route('password.reset', ['token' => $token, 'email' => $customer->email]);
                AdminAuditLog::create(['admin_user_id' => $admin->id, 'action' => 'customer.activation_link.generated', 'resource_type' => 'customer', 'resource_id' => $customer->id, 'safe_metadata' => ['customer_id' => $customer->id]]);
            }
            $domain = Domain::create([
                'user_id' => $customer->id,
                'order_id' => null,
                'name' => $name,
                'tld' => strtolower((string) ltrim(strrchr($name, '.'), '.')),
                'provider' => 'onlinenic',
                'acquisition_source' => 'admin_import',
                'imported_by_admin_id' => $admin->id,
                'imported_at' => now(),
                'internal_note' => $note,
                'status' => 'active',
                'registered_at' => $this->date($info->registeredAt),
                'expires_at' => $this->date($info->expiresAt),
                'nameservers' => $info->nameservers,
                'transfer_locked' => $info->transferLocked,
                'provider_status' => $info->providerStatus,
                'provider_synced_at' => now(),
            ]);
            RegistrarOperation::create(['user_id' => $customer->id, 'domain_id' => $domain->id, 'provider' => 'onlinenic', 'operation' => 'domain_import', 'cltrid' => $info->cltrid, 'svtrid' => $info->svtrid, 'status' => 'completed', 'provider_code' => $info->providerCode, 'safe_request_metadata' => ['domain' => $name], 'started_at' => now(), 'completed_at' => now()]);
            AdminAuditLog::create(['admin_user_id' => $admin->id, 'action' => 'domain.imported', 'resource_type' => 'domain', 'resource_id' => $domain->id, 'safe_metadata' => ['domain_id' => $domain->id, 'domain' => $name, 'customer_id' => $customer->id]]);
            AdminAuditLog::create(['admin_user_id' => $admin->id, 'action' => 'domain.assigned', 'resource_type' => 'domain', 'resource_id' => $domain->id, 'safe_metadata' => ['domain_id' => $domain->id, 'customer_id' => $customer->id]]);

            return [$domain, $customer];
        });

        return compact('domain', 'customer', 'activationLink');
    }

    private function date(?string $value): ?string
    {
        if ($value !== null && preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return sprintf('%04d-%02d-%02d', (int) $parts[1], (int) $parts[2], (int) $parts[3]);
        }

        return null;
    }
}
