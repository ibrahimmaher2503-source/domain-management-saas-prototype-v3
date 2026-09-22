<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\AdminQueryService;
use App\Domain\Registrar\Contracts\RegistrarGateway;
use App\Http\Controllers\Controller;
use App\Integrations\OnlineNic\OnlineNicAccountService;
use App\Models\Domain;
use App\Models\Order;
use App\Models\SslCertificate;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
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

    public function customer(User $customer): Response
    {
        abort_if($customer->is_admin, 404);

        return Inertia::render('Admin/Detail', $this->queries->customer($customer) + ['resource' => 'customer', 'title' => $customer->name]);
    }

    public function domains(Request $request): Response
    {
        return Inertia::render('Admin/Table', $this->queries->domains($request) + ['resource' => 'domains', 'title' => 'Domains']);
    }

    public function domain(Domain $domain): Response
    {
        return Inertia::render('Admin/Detail', $this->queries->domain($domain) + ['resource' => 'domain', 'title' => $domain->name]);
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

    public function pricing(Request $request, RegistrarGateway $registrar): Response
    {
        return Inertia::render('Admin/Pricing', $this->queries->pricing($request, $registrar));
    }
}
