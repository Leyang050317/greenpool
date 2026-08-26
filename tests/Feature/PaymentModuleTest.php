<?php

namespace Tests\Feature;

use App\Events\PaymentReceived;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\PaymentReceivedNotification;
use App\Mail\PaymentReceiptMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_completed_booking_can_open_checkout_and_creates_pending_payment(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking(price: 12.50, seats: 2);

        $this->actingAs($passenger)->get(route('payments.checkout', $booking))
            ->assertOk()->assertSee($driver->name)->assertSee('RM 25.00')
            ->assertSee('Online Banking (FPX)')->assertSee('Credit / Debit Card')
            ->assertSee('E-Wallet')->assertSee('Cash');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id, 'payer_id' => $passenger->id,
            'payee_id' => $driver->id, 'amount' => 25.00, 'payment_status' => 'Pending',
        ]);
    }

    public function test_payment_requires_a_completed_accepted_booking(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $booking->trip->update(['status' => 'In Progress', 'completed_at' => null]);

        $this->actingAs($passenger)->get(route('payments.checkout', $booking))->assertStatus(422);
        $this->assertDatabaseMissing('payments', ['booking_id' => $booking->id]);
    }

    public function test_passenger_can_complete_simulated_payment_once_and_driver_is_notified(): void
    {
        Event::fake([PaymentReceived::class]);
        [$driver, $passenger, $booking] = $this->completedBooking();
        $payment = app(\App\Services\PaymentService::class)->createPendingForBooking($booking);

        $this->actingAs($passenger)->post(route('payments.store', $payment), ['payment_method' => 'e_wallet'])
            ->assertRedirect(route('payments.show', $payment));

        $payment->refresh();
        $this->assertSame('Paid', $payment->payment_status);
        $this->assertSame('e_wallet', $payment->payment_method);
        $this->assertNotNull($payment->paid_at);
        $this->assertStringStartsWith('GP-', $payment->transaction_reference);
        $this->assertNotNull($driver->unreadNotifications()->where('type', PaymentReceivedNotification::class)->first());
        Event::assertDispatched(PaymentReceived::class, fn (PaymentReceived $event) =>
            $event->payment->is($payment) && (string) $event->broadcastOn() === 'private-driver.'.$driver->id
        );

        $this->actingAs($passenger)->post(route('payments.store', $payment), ['payment_method' => 'card'])->assertStatus(409);
    }

    public function test_unrelated_user_cannot_open_or_pay_another_passengers_payment(): void
    {
        [, , $booking] = $this->completedBooking();
        $payment = app(\App\Services\PaymentService::class)->createPendingForBooking($booking);
        $outsider = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $this->actingAs($outsider)->get(route('payments.show', $payment))->assertForbidden();
        $this->actingAs($outsider)->post(route('payments.store', $payment), ['payment_method' => 'card'])->assertForbidden();
    }

    public function test_payment_history_is_scoped_to_the_passenger_and_driver(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();
        $payment = app(\App\Services\PaymentService::class)->createPendingForBooking($booking);

        $this->actingAs($passenger)->get(route('payments.index'))
            ->assertOk()->assertSee('Pay Now')->assertSee('RM '.number_format((float) $payment->amount, 2));
        $this->actingAs($driver)->get(route('payments.index'))
            ->assertOk()->assertSee($passenger->name)->assertSee('View Details');
    }

    public function test_paid_payment_receipt_contains_fare_details_and_is_private(): void
    {
        [, $passenger, $booking] = $this->completedBooking(price: 7.50, seats: 2);
        $payment = app(\App\Services\PaymentService::class)->createPendingForBooking($booking);
        $this->actingAs($passenger)->post(route('payments.store', $payment), ['payment_method' => 'card']);

        $this->actingAs($passenger)->get(route('payments.receipt', $payment))
            ->assertOk()->assertSee('E-Receipt')->assertSee('RM 15.00')
            ->assertSee('12.35 km')->assertSee($payment->refresh()->transaction_reference);

        $outsider = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $this->actingAs($outsider)->get(route('payments.receipt', $payment))->assertForbidden();
    }

    public function test_successful_payment_automatically_emails_the_passenger_e_receipt(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $payment = app(\App\Services\PaymentService::class)->createPendingForBooking($booking);
        $this->actingAs($passenger)->post(route('payments.store', $payment), ['payment_method' => 'fpx'])
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'E-Receipt was sent'));

        Mail::assertSent(PaymentReceiptMail::class, fn (PaymentReceiptMail $mail) =>
            $mail->hasTo($passenger->email) && $mail->payment->is($payment)
        );
    }

    public function test_passenger_is_prompted_to_pay_before_rating_the_driver(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $payment = app(\App\Services\PaymentService::class)->createPendingForBooking($booking);

        $this->actingAs($passenger)->get(route('passenger.home'))
            ->assertOk()->assertSee('Complete your payment')->assertSee('Pay Now')->assertDontSee('How was your ride?');
        $this->actingAs($passenger)->get(route('ratings.create', $booking))->assertStatus(402);

        $this->actingAs($passenger)->post(route('payments.store', $payment), ['payment_method' => 'fpx'])
            ->assertRedirect(route('payments.show', $payment));
        $this->actingAs($passenger)->get(route('passenger.home'))
            ->assertOk()->assertSee('How was your ride?')->assertSee('Rate Now');
    }

    public function test_completing_a_trip_generates_one_pending_payment_for_each_accepted_booking(): void
    {
        [$driver, , $firstBooking] = $this->completedBooking();
        $trip = $firstBooking->trip;
        $trip->update(['status' => 'In Progress', 'completed_at' => null, 'started_at' => now()->subHour()]);
        $secondPassenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $secondBooking = Booking::create([
            'trip_id' => $trip->trip_id, 'passenger_id' => $secondPassenger->id,
            'booking_status' => 'Accepted', 'number_of_seats' => 1, 'pickup_point' => 'TBS',
        ]);

        $this->actingAs($driver)->patch(route('driver.trips.complete', $trip))->assertRedirect();
        $this->assertDatabaseHas('payments', ['booking_id' => $firstBooking->id, 'payment_status' => 'Pending']);
        $this->assertDatabaseHas('payments', ['booking_id' => $secondBooking->id, 'payment_status' => 'Pending']);
        $this->assertSame(2, Payment::whereIn('booking_id', [$firstBooking->id, $secondBooking->id])->count());
    }

    private function completedBooking(float $price = 10, int $seats = 1): array
    {
        $driver = User::factory()->create(['role' => 'driver', 'email_verified_at' => now()]);
        $passenger = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $vehicle = Vehicle::factory()->create(['user_id' => $driver->id]);
        $trip = $driver->trips()->create([
            'vehicle_id' => $vehicle->vehicle_id, 'departure_location' => 'Kuala Lumpur',
            'destination' => 'Putrajaya', 'departure_at' => now()->subHour(),
            'available_seats' => 3, 'price_per_passenger' => $price,
            'estimated_distance_km' => 12.35, 'estimated_duration_seconds' => 988,
            'status' => 'Completed', 'completed_at' => now(),
        ]);
        $booking = Booking::create([
            'trip_id' => $trip->trip_id, 'passenger_id' => $passenger->id,
            'booking_status' => 'Accepted', 'number_of_seats' => $seats, 'pickup_point' => 'KL Sentral',
        ]);

        return [$driver, $passenger, $booking->load('trip')];
    }
}
