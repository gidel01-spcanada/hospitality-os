<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AdminFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_screens_display_validation_errors_after_redirect(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'is_active' => true, 'locale' => 'en']);
        $this->actingAs($admin);

        $cases = [
            ['admin.settings', 'post', 'admin.settings.update', ['contact_email' => 'invalid'], 'contact_email'],
            ['admin.reviews', 'post', 'admin.reviews.store', ['source' => 'booking', 'reviewer_name' => 'Guest', 'rating' => 6], 'rating'],
            ['admin.reviews', 'post', 'admin.reviews.import', ['csv' => ''], 'csv'],
            ['admin.reviews', 'get', 'admin.reviews', ['rating' => 6], 'rating'],
            ['admin.reports.index', 'get', 'admin.reports.index', ['date_from' => '2025-01-01', 'date_to' => '2027-01-01'], 'date_to'],
            ['admin.cleaning.index', 'get', 'admin.cleaning.index', ['assignee' => 'Unknown'], 'assignee'],
            ['admin.cleaning.index', 'post', 'admin.cleaning.share', ['expires_in_days' => 4], 'expires_in_days'],
            ['admin.audit-logs', 'get', 'admin.audit-logs', ['event' => 'invalid'], 'event'],
        ];

        foreach ($cases as [$page, $method, $action, $input, $field]) {
            $url = route($page);
            $target = $method === 'get' ? route($action, $input) : route($action);
            $response = $method === 'get'
                ? $this->from($url)->get($target)
                : $this->from($url)->post($target, $input);
            $response->assertRedirect($url)->assertSessionHasErrors($field);
            $message = session('errors')->first($field);

            $this->get($url)->assertOk()
                ->assertSee('class="alert alert-error admin-flash" id="validation-errors" role="alert"', false)
                ->assertSee($message);
        }
    }

    public function test_cleaning_schedule_displays_success_and_share_link_feedback(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Test tenant', 'slug' => 'test-tenant', 'status' => 'active']);
        $admin = User::factory()->create([
            'role' => 'admin', 'is_admin' => true, 'is_active' => true, 'locale' => 'fr',
            'tenant_id' => $tenant->id, 'is_platform_admin' => false,
        ]);
        $this->actingAs($admin);

        $response = $this->post(route('admin.cleaning.generate'), [
            'mode' => 'month', 'date' => '2034-05-01', 'assignee_name' => 'Team',
            'scheduled_time' => '11:30', 'duration_minutes' => 90,
        ]);
        $response->assertRedirect()->assertSessionHas('success', trans_choice('messages.cleaning.generated', 0, ['count' => 0]));
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee(trans_choice('messages.cleaning.generated', 0, ['count' => 0]));

        $response = $this->post(route('admin.cleaning.share'), [
            'mode' => 'month', 'date' => '2034-05-01', 'expires_in_days' => 7,
        ]);
        $response->assertRedirect()->assertSessionHas('success', __('messages.cleaning.share_created'));
        $shareUrl = session('cleaning_share_url');
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee(__('messages.cleaning.share_created'))
            ->assertSee($shareUrl);
    }

    public function test_existing_status_feedback_remains_visible_and_validation_messages_are_escaped(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'is_active' => true, 'locale' => 'fr']);
        $this->actingAs($admin);

        foreach (['admin.settings', 'admin.reviews'] as $page) {
            $errors = (new ViewErrorBag)->put('default', new MessageBag(['field' => '<script>invalid</script>']));
            $this->withSession(['status' => 'Saved successfully', 'errors' => $errors])
                ->get(route($page))->assertOk()
                ->assertSee('Saved successfully')
                ->assertSee('id="validation-errors"', false)
                ->assertSee('&lt;script&gt;invalid&lt;/script&gt;', false)
                ->assertDontSee('<script>invalid</script>', false);
        }
    }

    public function test_staff_portal_displays_cleaning_and_report_feedback(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Staff tenant', 'slug' => 'staff-tenant', 'status' => 'active']);

        foreach (['host', 'concierge'] as $role) {
            $staff = User::factory()->create([
                'role' => $role, 'is_admin' => false, 'is_active' => true,
                'is_platform_admin' => false, 'tenant_id' => $tenant->id, 'locale' => 'en',
            ]);
            $this->actingAs($staff);
            $url = route('admin.cleaning.index');
            $this->from($url)->get(route('admin.cleaning.index', ['assignee' => 'Unknown']))
                ->assertRedirect($url)->assertSessionHasErrors('assignee');
            $message = session('errors')->first('assignee');
            $this->get($url)->assertOk()->assertSee('id="validation-errors"', false)->assertSee($message);

            $response = $this->post(route('admin.cleaning.generate'), [
                'mode' => 'month', 'date' => '2034-05-01', 'assignee_name' => 'Team',
                'scheduled_time' => '11:30', 'duration_minutes' => 90,
            ]);
            $response->assertRedirect()->assertSessionHas('success');
            $message = session('success');
            $this->get($response->headers->get('Location'))->assertOk()->assertSee($message);

            if ($role === 'host') {
                $url = route('admin.reports.index');
                $this->from($url)->get(route('admin.reports.index', [
                    'date_from' => '2025-01-01', 'date_to' => '2027-01-01',
                ]))->assertRedirect($url)->assertSessionHasErrors('date_to');
                $message = session('errors')->first('date_to');
                $this->get($url)->assertOk()->assertSee('id="validation-errors"', false)->assertSee($message);
            }
        }
    }
}
