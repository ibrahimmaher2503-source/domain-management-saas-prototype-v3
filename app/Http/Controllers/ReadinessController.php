<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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

        $redisRequired = in_array('redis', [config('cache.default'), config('session.driver'), config('queue.default')], true);
        if ($redisRequired) {
            try {
                Redis::connection()->command('ping');
                $checks['redis'] = 'ready';
            } catch (Throwable) {
                $checks['redis'] = 'unavailable';
            }
        } else {
            $checks['redis'] = 'not_required';
        }

        $ready = ! in_array('unavailable', $checks, true);

        return response()->json(['status' => $ready ? 'ready' : 'unavailable', 'checks' => $checks], $ready ? 200 : 503);
    }
}
