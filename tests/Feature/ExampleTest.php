<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_from_the_home_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_authenticated_customer_pages_render_the_expected_inertia_components(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/overview')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Overview/Index'));
        $this->actingAs($user)->get('/domains')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Domains/Index'));
        $domain = Domain::create(['user_id' => $user->id, 'name' => 'example.com', 'tld' => 'com', 'provider' => 'onlinenic', 'status' => 'active', 'nameservers' => ['ns1.example.net', 'ns2.example.net']]);
        config(['onlinenic.registrant_contact_id' => 'private-platform-contact']);
        $this->actingAs($user)->get('/domains/'.$domain->id)->assertOk()->assertDontSee('private-platform-contact')->assertInertia(fn (Assert $page) => $page->component('Domains/Show')->where('domain.id', $domain->id)->missing('domain.registration_contacts'));
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($other)->get('/domains/'.$domain->id)->assertNotFound();
        $this->actingAs($other)->get('/domains')->assertInertia(fn (Assert $page) => $page->component('Domains/Index')->has('domains', 0));
    }
}
