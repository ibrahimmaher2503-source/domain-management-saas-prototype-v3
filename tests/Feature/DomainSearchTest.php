<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DomainSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_open_domain_search(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('domains.search'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Domains/Search')->where('result', null));
    }

    public function test_guest_is_redirected_from_domain_search(): void
    {
        $this->get(route('domains.search'))->assertRedirect(route('login'));
    }
}
