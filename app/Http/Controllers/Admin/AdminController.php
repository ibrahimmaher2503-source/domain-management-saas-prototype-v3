<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\AdminQueryService;
use App\Domain\Domains\Exceptions\DomainImportException;
use App\Domain\Domains\Services\ImportDomainForCustomer;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationSettings;
use App\Integrations\OnlineNic\OnlineNicAccountService;
use App\Integrations\OnlineNic\OnlineNicSettings;
use App\Models\AdminAuditLog;
use App\Models\Domain;
use App\Models\Order;
use App\Models\SslCertificate;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

final class AdminController extends Controller
{
    public function __construct(private readonly AdminQueryService $queries) {}

    public function overview(): Response
    {
        return Inertia::render('Admin/Overview', $this->queries->overview());
    }

    public function customers(Request $request): Response
    {
        return Inertia::render('Admin/Table', $this->queries->customers($request) + ['resource' => 'customers', 'title' => 'Customers']);
    }

    public function createCustomer(): Response
    {
        return Inertia::render('Admin/CreateCustomer');
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email']]);
        $customer = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make(Str::random(64)), 'activation_pending' => true]);
        AdminAuditLog::create(['admin_user_id' => $request->user()->id, 'action' => 'customer.created', 'resource_type' => 'customer', 'resource_id' => $customer->id, 'safe_metadata' => ['customer_id' => $customer->id]]);
        $link = $this->activationLink($customer, $request->user());

        return redirect()->route('admin.customers.show', $customer)->with(['activation_link' => $link, 'activation_expires_at' => now()->addMinutes((int) config('auth.passwords.users.expire', 60))->toIso8601String()]);
    }

    public function customer(User $customer): Response
    {
        abort_if($customer->is_admin, 404);

        return Inertia::render('Admin/Detail', $this->queries->customer($customer) + ['resource' => 'customer', 'title' => $customer->name, 'activation_link' => session('activation_link'), 'activation_expires_at' => session('activation_expires_at')]);
    }

    public function regenerateActivation(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->is_admin, 404);
        $link = $this->activationLink($customer, $request->user());

        return back()->with(['activation_link' => $link, 'activation_expires_at' => now()->addMinutes((int) config('auth.passwords.users.expire', 60))->toIso8601String()]);
    }

    public function domains(Request $request): Response
    {
        return Inertia::render('Admin/Table', $this->queries->domains($request) + ['resource' => 'domains', 'title' => 'Domains']);
    }

    public function importDomain(Request $request): Response
    {
        return Inertia::render('Admin/ImportDomain', ['customers' => User::query()->where('is_admin', false)->orderBy('name')->get(['id', 'name', 'email']), 'selected_customer_id' => $request->integer('customer_id') ?: null]);
    }

    public function storeImportedDomain(Request $request, ImportDomainForCustomer $importer): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253'],
            'provider' => ['required', Rule::in(['onlinenic'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_admin', false))],
            'new_customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:120'],
            'new_customer_email' => ['required_without:customer_id', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'note' => ['nullable', 'string', 'max:500', 'not_regex:/password|epp|auth.?code|credential/i'],
        ]);
        $customer = isset($data['customer_id']) ? User::findOrFail($data['customer_id']) : null;
        $newCustomer = $customer ? null : ['name' => $data['new_customer_name'], 'email' => $data['new_customer_email']];
        try {
            $result = $importer->import($data['domain'], $customer, $newCustomer, $request->user(), $data['note'] ?? null);
        } catch (DomainImportException|InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['domain' => $exception->getMessage()]);
        }

        return redirect()->route('admin.domains.show', $result['domain'])->with(['activation_link' => $result['activationLink']]);
    }

    public function domain(Domain $domain): Response
    {
        return Inertia::render('Admin/Detail', $this->queries->domain($domain) + ['resource' => 'domain', 'title' => $domain->name, 'activation_link' => session('activation_link')]);
    }

    public function orders(Request $request): Response
    {
        return Inertia::render('Admin/Table', $this->queries->orders($request) + ['resource' => 'orders', 'title' => 'Orders']);
    }

    public function order(Order $order): Response
    {
        return Inertia::render('Admin/Detail', $this->queries->order($order) + ['resource' => 'order', 'title' => 'Order #'.$order->id]);
    }

    public function payments(Request $request): Response
    {
        return Inertia::render('Admin/Table', $this->queries->payments($request) + ['resource' => 'payments', 'title' => 'Payments']);
    }

    public function transfers(Request $request): Response
    {
        return Inertia::render('Admin/Table', $this->queries->transfers($request) + ['resource' => 'transfers', 'title' => 'Transfers']);
    }

    public function transfer(Transfer $transfer): Response
    {
        return Inertia::render('Admin/Detail', $this->queries->transfer($transfer) + ['resource' => 'transfer', 'title' => $transfer->domain]);
    }

    public function ssl(Request $request): Response
    {
        return Inertia::render('Admin/Table', $this->queries->ssl($request) + ['resource' => 'ssl', 'title' => 'SSL Certificates']);
    }

    public function certificate(SslCertificate $certificate): Response
    {
        return Inertia::render('Admin/Detail', $this->queries->certificate($certificate) + ['resource' => 'certificate', 'title' => $certificate->domain->name]);
    }

    public function operations(Request $request): Response
    {
        return Inertia::render('Admin/Operations', $this->queries->operations($request));
    }

    public function providers(OnlineNicAccountService $account): Response
    {
        $data = $this->queries->providers();
        $data['balance'] = null;
        if ($data['providers']['onlinenic']['configured']) {
            try {
                $data['balance'] = $account->balance();
            } catch (Throwable) {
                $data['balance'] = ['unavailable' => true];
            }
        }

        return Inertia::render('Admin/Providers', $data);
    }

    public function updateProviderSettings(Request $request, OnlineNicSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255', 'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'client_id' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'account_currency' => ['required', 'string', 'size:3'],
            'customer_billing_currency' => ['required', 'string', 'size:3'],
            'registrant_contact_id' => ['required', 'string', 'max:255'],
            'admin_contact_id' => ['required', 'string', 'max:255'],
            'tech_contact_id' => ['required', 'string', 'max:255'],
            'billing_contact_id' => ['required', 'string', 'max:255'],
        ]);
        $settings->update($data);
        AdminAuditLog::create(['admin_user_id' => $request->user()->id, 'action' => 'provider.settings.updated', 'resource_type' => 'provider', 'resource_id' => null, 'safe_metadata' => ['provider' => 'onlinenic', 'secret_changed' => filled($data['password'] ?? null)]]);

        return back()->with('status', 'OnlineNIC settings saved.');
    }

    public function updatePaymobSettings(Request $request, IntegrationSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['required', 'url', 'max:255'], 'secret_key' => ['nullable', 'string', 'max:500'], 'public_key' => ['nullable', 'string', 'max:500'],
            'hmac_secret' => ['nullable', 'string', 'max:500'], 'card_integration_id' => ['required', 'integer', 'min:1'], 'mode' => ['required', Rule::in(['test', 'live'])],
            'payment_expiration' => ['required', 'integer', 'min:60', 'max:86400'], 'notification_url' => ['nullable', 'url', 'max:500'], 'redirection_url' => ['nullable', 'url', 'max:500'],
        ]);
        $settings->update('paymob', $data, ['secret_key', 'public_key', 'hmac_secret']);
        $this->auditProviderSettings($request, 'paymob', $data);

        return back()->with('status', 'Paymob settings saved.');
    }

    public function updateCloudflareSettings(Request $request, IntegrationSettings $settings): RedirectResponse
    {
        $data = $request->validate(['api_base' => ['required', 'url', 'max:255'], 'api_token' => ['nullable', 'string', 'max:500'], 'account_id' => ['required', 'string', 'max:255']]);
        $settings->update('cloudflare', $data, ['api_token']);
        $this->auditProviderSettings($request, 'cloudflare', $data);

        return back()->with('status', 'Cloudflare settings saved.');
    }

    private function auditProviderSettings(Request $request, string $provider, array $data): void
    {
        AdminAuditLog::create(['admin_user_id' => $request->user()->id, 'action' => 'provider.settings.updated', 'resource_type' => 'provider', 'resource_id' => null, 'safe_metadata' => ['provider' => $provider, 'secret_changed' => collect(['secret_key', 'public_key', 'hmac_secret', 'api_token'])->some(fn (string $key): bool => filled($data[$key] ?? null))]]);
    }

    public function pricing(Request $request, RegistrarGateway $registrar): Response
    {
        return Inertia::render('Admin/Pricing', $this->queries->pricing($request, $registrar));
    }

    private function activationLink(User $customer, User $admin): string
    {
        $token = Password::broker()->createToken($customer);
        AdminAuditLog::create(['admin_user_id' => $admin->id, 'action' => 'customer.activation_link.generated', 'resource_type' => 'customer', 'resource_id' => $customer->id, 'safe_metadata' => ['customer_id' => $customer->id]]);

        return URL::route('password.reset', ['token' => $token, 'email' => $customer->email]);
    }
}
