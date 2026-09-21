<?php

namespace App\Http\Controllers;

use App\Domain\Domains\Services\DomainSearchService;
use App\Integrations\OnlineNic\Exceptions\OnlineNicException;
use App\Integrations\OnlineNic\Exceptions\UnsupportedCapability;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;

final class DomainSearchController extends Controller
{
    public function __construct(private readonly DomainSearchService $search) {}

    public function show(Request $request)
    {
        $domain = $request->query('domain');
        if ($domain === null || $domain === '') {
            return Inertia::render('Domains/Search', ['result' => null, 'error' => null, 'searchedDomain' => null]);
        }

        try {
            $result = $this->search->search((string) $domain);

            return Inertia::render('Domains/Search', ['result' => $result, 'error' => null, 'searchedDomain' => (string) $domain]);
        } catch (InvalidArgumentException $exception) {
            return Inertia::render('Domains/Search', ['result' => null, 'error' => ['type' => 'validation', 'message' => $exception->getMessage()], 'searchedDomain' => (string) $domain]);
        } catch (UnsupportedCapability $exception) {
            return Inertia::render('Domains/Search', ['result' => null, 'error' => ['type' => 'unsupported', 'message' => $exception->getMessage()], 'searchedDomain' => (string) $domain]);
        } catch (OnlineNicException) {
            return Inertia::render('Domains/Search', ['result' => null, 'error' => ['type' => 'provider', 'message' => 'Domain search is temporarily unavailable. Please try again.'], 'searchedDomain' => (string) $domain]);
        }
    }
}
