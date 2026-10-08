<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_dashboard()
    {
        $response = $this->get(route('home'));

        // L'accueil mène au tableau de bord.
        $response->assertRedirect(route('dashboard'));
    }
}
