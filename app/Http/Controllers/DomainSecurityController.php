<?php

namespace App\Http\Controllers;

use App\Domain\Domains\Services\ChangeTransferLock;
use App\Domain\Domains\Services\RetrieveAuthCode;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class DomainSecurityController extends Controller
{
    public function updateTransferLock(Request $request, Domain $domain, ChangeTransferLock $change): RedirectResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 404);
        $validated = $request->validate(['locked' => ['required', 'boolean']]);
        try {
            $result = $change->change($domain, (bool) $validated['locked']);
        } catch (InvalidArgumentException $exception) {
            return back()->with('domain_error', $exception->getMessage());
        }

        return match ($result) {
            'completed' => back()->with('domain_notice', $validated['locked'] ? 'Transfer lock enabled.' : 'Transfer lock disabled.'),
            'unchanged' => back()->with('domain_notice', 'Transfer lock is unchanged.'),
            'ambiguous', 'blocked' => back()->with('domain_error', 'The registrar is still confirming this security change.'),
            default => back()->with('domain_error', 'Transfer lock could not be changed. Please try again later.'),
        };
    }

    public function authCode(Request $request, Domain $domain, RetrieveAuthCode $retrieve): JsonResponse
    {
        abort_unless($domain->user_id === $request->user()->id, 404);
        $request->validate(['password' => ['required', 'string', 'current_password']]);
        try {
            $authCode = $retrieve->retrieve($domain);
        } catch (OnlineNicException|InvalidArgumentException) {
            return response()->json(['message' => 'Transfer code could not be retrieved.'], 503)->withHeaders(['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache']);
        }

        return response()->json(['auth_code' => $authCode])->withHeaders(['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache']);
    }
}
