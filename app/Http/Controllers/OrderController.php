<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class OrderController extends Controller
{
    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return Inertia::render('Orders/Show', ['order' => [
            'id' => $order->id, 'domain' => $order->domain, 'status' => $order->status, 'registration_period' => $order->registration_period,
            'customer_price' => $order->customer_price, 'currency' => $order->currency, 'registrant_name' => data_get($order->registration_data, 'registrant.name'),
            'nameservers' => $order->nameservers, 'created_at' => $order->created_at,
            'payment_error' => session('errors')?->first('payment'),
        ]]);
    }
}
