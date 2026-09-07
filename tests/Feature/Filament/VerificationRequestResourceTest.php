<?php

namespace Tests\Feature\Filament;

use App\Enums\GraduateStatus;
use App\Enums\VerificationStatus;
use App\Filament\Resources\VerificationRequestResource\Pages\ListVerificationRequests;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VerificationRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_panel_login_page(): void
    {
        $this->get('/backend/verification-requests')->assertRedirect('/backend/login');
    }

    public function test_a_non_admin_user_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/backend/verification-requests')->assertForbidden();
    }

    public function test_an_admin_can_see_and_filter_requests_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        $pending = VerificationRequest::factory()->for(User::factory())->create(['status' => VerificationStatus::Pending]);
        $confirmed = VerificationRequest::factory()->for(User::factory())->create(['status' => VerificationStatus::Confirmed]);

        $this->actingAs($admin);

        Livewire::test(ListVerificationRequests::class)
            ->assertCanSeeTableRecords([$pending, $confirmed])
            ->filterTable('status', VerificationStatus::Pending->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$confirmed]);
    }

    public function test_confirming_a_request_updates_the_users_graduate_status(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        $request = VerificationRequest::factory()->for($applicant)->create(['status' => VerificationStatus::Pending]);

        $this->actingAs($admin);

        Livewire::test(ListVerificationRequests::class)
            ->callTableAction('confirm', $request)
            ->assertNotified();

        $this->assertSame(VerificationStatus::Confirmed, $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->reviewer_id);
        $this->assertSame(GraduateStatus::Confirmed, $applicant->fresh()->graduate_status);
    }

    public function test_rejecting_a_request_records_the_reason_and_updates_the_users_graduate_status(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->graduateStatus(GraduateStatus::Unverified)->create();
        $request = VerificationRequest::factory()->for($applicant)->create(['status' => VerificationStatus::Pending]);

        $this->actingAs($admin);

        Livewire::test(ListVerificationRequests::class)
            ->mountTableAction('reject', $request)
            ->setTableActionData(['rejection_reason' => 'Данные не совпадают со справочником'])
            ->callMountedTableAction()
            ->assertNotified();

        $fresh = $request->fresh();
        $this->assertSame(VerificationStatus::Rejected, $fresh->status);
        $this->assertSame('Данные не совпадают со справочником', $fresh->rejection_reason);
        $this->assertSame($admin->id, $fresh->reviewer_id);
        $this->assertSame(GraduateStatus::Rejected, $applicant->fresh()->graduate_status);
    }
}
