<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LimitPaymobCallbackSize
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('paymob.callback_max_bytes', 65536);
        $length = (int) $request->server('CONTENT_LENGTH', 0);
        abort_if($length > $limit || strlen($request->getContent()) > $limit, 413);

        return $next($request);
    }
}
