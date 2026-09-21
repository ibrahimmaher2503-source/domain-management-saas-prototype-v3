<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DomainController extends Controller
{
    public function index(Request $request): Response
    {
        $domains = $request->user()->domains()->latest()->get()->map(fn (Domain $domain) => $this->summary($domain))->values();

        return Inertia::render('Domains/Index', ['domains' => $domains]);
    }

    public function show(Request $request, Domain $domain): Response
    {
        abort_unless($domain->user_id === $request->user()->id, 404);

        return Inertia::render('Domains/Show', ['domain' => $this->summary($domain)]);
    }

    private function summary(Domain $domain): array
    {
        return ['id' => $domain->id, 'name' => $domain->name, 'status' => $domain->status, 'registered_at' => $domain->registered_at?->toDateString(), 'expires_at' => $domain->expires_at?->toDateString(), 'nameservers' => $domain->nameservers, 'provider_status' => $domain->provider_status];
    }
}
