<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['application' => 'ready'];
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ready';
        } catch (Throwable) {
            $checks['database'] = 'unavailable';
        }

        $ready = ! in_array('unavailable', $checks, true);

        return response()->json(['status' => $ready ? 'ready' : 'unavailable', 'checks' => $checks], $ready ? 200 : 503);
    }
}
