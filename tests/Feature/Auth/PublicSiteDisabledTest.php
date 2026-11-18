<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public website is off: the home page is the staff sign-in and guests have no booking path.
 * See docs/decisions/2026-11-18-disable-public-website.md.
 */
final class PublicSiteDisabledTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $role = Role::create(['name' => 'staff', 'description' => 'Staff']);

        return User::create(['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'secret-password', 'role_id' => $role->id, 'is_active' => true]);
    }

    public function test_guest_opens_the_website_to_land_on_the_staff_sign_in_page(): void
    {
        $this->get('/')->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertSee('Sign In')->assertDontSee('Back to Website');
    }

    public function test_guest_opens_an_old_venue_page_to_find_there_is_no_public_booking_path(): void
    {
        $venue = Venue::create([
            'name' => 'Grand Hall', 'type' => 'venue', 'description' => 'Hall', 'capacity' => 100,
            'price_per_day' => 10000, 'amenities' => [], 'images' => [], 'is_active' => true,
        ]);

        foreach (['/venues', '/suites', "/venue/{$venue->id}", "/venue/{$venue->id}/addons",
            "/venue/{$venue->id}/addons-data", "/venue/{$venue->id}/calendar-data", '/contact'] as $url) {
            $this->get($url)->assertNotFound();
        }

        $this->post('/check-availability', ['venue_id' => $venue->id, 'booking_date' => now()->addWeek()->toDateString()])
            ->assertNotFound();
    }

    public function test_staff_opens_the_website_to_go_straight_to_the_dashboard(): void
    {
        $this->actingAs($this->staff());

        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_staff_signs_out_to_return_to_the_sign_in_page(): void
    {
        $this->actingAs($this->staff())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
