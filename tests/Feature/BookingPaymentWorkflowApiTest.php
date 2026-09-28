<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingPaymentWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_is_approved_paid_and_confirmed_by_provider(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create(['user_type' => 'customer']);
        $provider = User::factory()->create(['user_type' => 'provider']);
        ProviderProfile::create([
            'user_id' => $provider->id,
            'business_name' => 'صالة أورنا',
            'status' => 'approved',
            'registration_status' => 'approved',
            'payment_instructions' => 'بنك فلسطين - اسم الحساب: صالة أورنا - رقم الحساب: 123456',
        ]);
        $service = Service::create([
            'provider_id' => $provider->id,
            'title' => 'صالة أورنا',
            'price' => 1400,
            'currency' => 'ILS',
            'status' => 'active',
            'booking_slots' => [[
                'label' => 'الفترة المسائية',
                'start_time' => '17:00',
                'end_time' => '22:00',
                'price' => 1500,
            ]],
        ]);

        $create = $this->actingAs($customer, 'sanctum')->postJson("/api/services/{$service->id}/bookings", [
            'booking_date' => now()->addDays(2)->toDateString(),
            'booking_time' => '17:00',
            'booking_slot' => 'الفترة المسائية',
        ]);
        $create->assertCreated()->assertJsonPath('data.status', 'provider_pending');
        $booking = Booking::firstOrFail();
        $this->assertSame(1500.0, (float) $booking->selected_price);

        $this->actingAs($provider, 'sanctum')->postJson("/api/provider/bookings/{$booking->id}/decision", [
            'decision' => 'accepted',
            'deposit_amount' => 500,
        ])->assertOk()->assertJsonPath('data.status', 'payment_awaiting');

        $this->actingAs($customer, 'sanctum')->post("/api/bookings/{$booking->id}/payment-proofs", [
            'amount' => 500,
            'method' => 'bank_transfer',
            'proof' => UploadedFile::fake()->create('transfer.png', 100, 'image/png'),
        ])->assertCreated()->assertJsonPath('data.status', 'review_under_proof');

        $paymentId = $booking->fresh()->payments()->firstOrFail()->id;
        $this->actingAs($provider, 'sanctum')->postJson("/api/provider/bookings/{$booking->id}/payments/{$paymentId}/review", [
            'decision' => 'confirmed',
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'workflow_status' => 'confirmed', 'status' => 'confirmed']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $customer->id, 'type' => 'payment_confirmed']);
    }
}
