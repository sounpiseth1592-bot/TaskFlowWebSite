<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_landing_page_and_start_registration(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Make room for')
            ->assertSee('About')
            ->assertSee('Features')
            ->assertSee('How it works')
            ->assertSee('Contact')
            ->assertSee('Soun Piseth')
            ->assertSee('Cambodia')
            ->assertSee('ជួបជាមួយអ្នកបង្កើត')
            ->assertSee('សួន ពិសិដ្ឋ')
            ->assertSee('/images/icons/englishs.jpg')
            ->assertSee('/images/icons/cambodias.png')
            ->assertSee('/taskflow-icon.svg')
            ->assertSee(route('login'))
            ->assertSee(route('register'));

        $this->assertGuest();
    }

    public function test_signed_in_users_open_the_task_dashboard_from_the_home_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('All tasks')
            ->assertSee($user->email)
            ->assertSee('/taskflow-icon.svg')
            ->assertSee(route('profile.edit'))
            ->assertDontSee('id="settings-modal"', false);
    }
}
