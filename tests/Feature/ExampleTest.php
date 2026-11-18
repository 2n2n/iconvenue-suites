<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The home page is the staff sign-in (no public website, see docs/decisions/2026-11-18-disable-public-website.md).
     */
    public function test_guest_opens_the_home_page_to_be_sent_to_the_sign_in_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
