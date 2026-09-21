<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_authenticated_customer_pages_render_the_expected_inertia_components(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/overview')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Overview/Index'));
        $this->actingAs($user)->get('/domains')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Domains/Index'));
        $this->actingAs($user)->get('/domains/1')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Domains/Show')->where('domain', 1));
    }
}
