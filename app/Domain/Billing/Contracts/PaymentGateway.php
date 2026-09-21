<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\DTOs\PaymentSession;
use App\Models\Order;

interface PaymentGateway
{
    public function createPayment(Order $order): PaymentSession;
}
