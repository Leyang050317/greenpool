<?php

namespace Tests\Feature;

use App\Events\PaymentReceived;
use App\Mail\PaymentReceiptMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\CashPaymentSelectedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Services\PaymentCompletionService;
use App\Services\PaymentService;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_passenger_can_choose_online_payment_and_continue_to_stripe_checkout(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking(price: 12.50, seats: 2);
        $this->mock(StripeCheckoutService::class, function (MockInterface $mock) use ($driver): void {
            $mock->shouldReceive('createCheckout')->once()->withArgs(fn (Payment $payment) => $payment->payee_id === $driver->id && (float) $payment->amount === 25.0
            )->andReturn('https://checkout.stripe.com/c/pay_test_greenpool');
        });

        $this->actingAs($passenger)->get(route('payments.checkout', $booking))
            ->assertOk()
            ->assertSee('How would you like to pay?')
            ->assertSee('Pay Online')
            ->assertSee('Pay Cash');

        $this->actingAs($passenger)->post(route('payments.checkout.initiate', $booking), ['method' => 'stripe'])
            ->assertRedirect('https://checkout.stripe.com/c/pay_test_greenpool');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id, 'payer_id' => $passenger->id,
            'payee_id' => $driver->id, 'amount' => 25.00, 'payment_status' => 'Pending',
        ]);
    }

    public function test_cash_stays_pending_until_the_driver_confirms_receipt(): void
    {
        Event::fake([PaymentReceived::class]);
        [$driver, $passenger, $booking] = $this->completedBooking(price: 12.50, seats: 2);

        $this->actingAs($passenger)
            ->post(route('payments.checkout.initiate', $booking), ['method' => 'cash'])
            ->assertRedirect();

        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('Pending', $payment->payment_status);
        $this->assertNull($payment->paid_at);
        $cashNotification = $driver->notifications()->where('type', CashPaymentSelectedNotification::class)->first();
        $this->assertNotNull($cashNotification);
        $this->assertStringContainsString('#cash-confirmation', $cashNotification->data['url']);

        $this->actingAs($passenger)->get(route('payments.show', $payment))
            ->assertOk()
            ->assertSee('Your driver will confirm after receiving it.')
            ->assertDontSee('Confirm Cash Received');

        $this->actingAs($driver)->post(route('payments.cash.confirm', $payment))
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame('Paid', $payment->payment_status);
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('CASH-'.$payment->id, $payment->transaction_reference);
        $this->assertNotNull($payment->paid_at);
        Event::assertDispatched(PaymentReceived::class);

        $this->actingAs($driver)->get(route('driver.home'))
            ->assertOk()
            ->assertSee('RM 25.00');
    }

    public function test_only_the_assigned_driver_can_confirm_a_cash_payment(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();
        $this->actingAs($passenger)->post(route('payments.checkout.initiate', $booking), ['method' => 'cash']);
        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
        $otherDriver = User::factory()->create(['role' => 'driver', 'email_verified_at' => now()]);

        $this->actingAs($passenger)->post(route('payments.cash.confirm', $payment))->assertForbidden();
        $this->actingAs($otherDriver)->post(route('payments.cash.confirm', $payment))->assertForbidden();
        $this->assertSame('Pending', $payment->refresh()->payment_status);
        $this->assertNotSame($driver->id, $otherDriver->id);
    }

    public function test_payment_requires_a_completed_accepted_booking(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $booking->trip->update(['status' => 'In Progress', 'completed_at' => null]);

        $this->actingAs($passenger)->get(route('payments.checkout', $booking))->assertStatus(422);
        $this->assertDatabaseMissing('payments', ['booking_id' => $booking->id]);
    }

    public function test_confirmed_stripe_payment_is_completed_once_and_driver_is_notified(): void
    {
        Event::fake([PaymentReceived::class]);
        [$driver, $passenger, $booking] = $this->completedBooking();
        $payment = app(PaymentService::class)->createPendingForBooking($booking);

        $completed = app(PaymentCompletionService::class)->complete(
            $payment,
            'stripe',
            'pi_test_greenpool',
            'cs_test_greenpool',
            'pi_test_greenpool',
        );

        $payment->refresh();
        $this->assertTrue($completed);
        $this->assertSame('Paid', $payment->payment_status);
        $this->assertSame('stripe', $payment->payment_method);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('pi_test_greenpool', $payment->transaction_reference);
        $this->assertSame('cs_test_greenpool', $payment->stripe_checkout_session_id);
        $this->assertNotNull($driver->unreadNotifications()->where('type', PaymentReceivedNotification::class)->first());
        Event::assertDispatched(PaymentReceived::class, fn (PaymentReceived $event) => $event->payment->is($payment) && (string) $event->broadcastOn() === 'private-driver.'.$driver->id
        );
        $broadcastPayload = (new PaymentReceived($payment))->broadcastWith();
        $this->assertSame(10.0, $broadcastPayload['amount']);
        $this->assertSame(10.0, $broadcastPayload['total_earnings']);

        $this->assertFalse(app(PaymentCompletionService::class)->complete($payment, 'stripe', 'pi_duplicate'));
    }

    public function test_unrelated_user_cannot_open_or_pay_another_passengers_payment(): void
    {
        [, , $booking] = $this->completedBooking();
        $payment = app(PaymentService::class)->createPendingForBooking($booking);
        $outsider = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);

        $this->actingAs($outsider)->get(route('payments.show', $payment))->assertForbidden();
        $this->actingAs($outsider)->get(route('payments.checkout', $booking))->assertForbidden();
    }

    public function test_payment_history_is_scoped_to_the_passenger_and_driver(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking();
        $payment = app(PaymentService::class)->createPendingForBooking($booking);

        $this->actingAs($passenger)->get(route('payments.index'))
            ->assertOk()->assertSee('Pay Now')->assertSee('RM '.number_format((float) $payment->amount, 2));
        $this->actingAs($driver)->get(route('payments.index'))
            ->assertOk()->assertSee($passenger->name)->assertSee('View Details');
    }

    public function test_paid_payment_receipt_contains_fare_details_and_is_private(): void
    {
        [, $passenger, $booking] = $this->completedBooking(price: 7.50, seats: 2);
        $payment = app(PaymentService::class)->createPendingForBooking($booking);
        app(PaymentCompletionService::class)->complete($payment, 'stripe', 'pi_receipt');

        $this->actingAs($passenger)->get(route('payments.receipt', $payment))
            ->assertOk()->assertSee('E-Receipt')->assertSee('RM 15.00')
            ->assertSee('12.35 km')->assertSee($payment->refresh()->transaction_reference);

        $outsider = User::factory()->create(['role' => 'passenger', 'email_verified_at' => now()]);
        $this->actingAs($outsider)->get(route('payments.receipt', $payment))->assertForbidden();
    }

    public function test_successful_payment_automatically_emails_the_passenger_e_receipt(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $payment = app(PaymentService::class)->createPendingForBooking($booking);
        app(PaymentCompletionService::class)->complete($payment, 'stripe', 'pi_email');

        Mail::assertSent(PaymentReceiptMail::class, fn (PaymentReceiptMail $mail) => $mail->hasTo($passenger->email) && $mail->payment->is($payment)
        );
    }

    public function test_stripe_success_return_verifies_the_session_for_its_passenger(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $payment = app(PaymentService::class)->createPendingForBooking($booking);
        $payment->update(['stripe_checkout_session_id' => 'cs_test_success']);

        $this->mock(StripeCheckoutService::class, function (MockInterface $mock) use ($payment): void {
            $mock->shouldReceive('fulfillCheckout')
                ->once()
                ->with('cs_test_success')
                ->andReturnUsing(function () use ($payment): Payment {
                    $payment->update([
                        'payment_status' => 'Paid',
                        'payment_method' => 'stripe',
                        'transaction_reference' => 'pi_test_success',
                        'paid_at' => now(),
                    ]);

                    return $payment->refresh();
                });
        });

        $this->actingAs($passenger)
            ->get(route('payments.stripe.success', ['session_id' => 'cs_test_success']))
            ->assertRedirect(route('payments.show', $payment))
            ->assertSessionHas('success');
    }

    public function test_stripe_webhook_endpoint_accepts_a_verified_event_handler(): void
    {
        $this->mock(StripeCheckoutService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handleWebhook')
                ->once()
                ->withArgs(fn (string $payload, string $signature) => str_contains($payload, 'checkout.session.completed') && $signature === 'test_signature'
                );
        });

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 'test_signature', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'checkout.session.completed'], JSON_THROW_ON_ERROR),
        )->assertOk()->assertJson(['received' => true]);
    }

    public function test_passenger_is_prompted_to_pay_before_rating_the_driver(): void
    {
        [, $passenger, $booking] = $this->completedBooking();
        $payment = app(PaymentService::class)->createPendingForBooking($booking);

        $this->actingAs($passenger)->get(route('passenger.home'))
            ->assertOk()->assertSee('Complete your payment')->assertSee('Pay Now')->assertDontSee('How was your ride?');
        $this->actingAs($passenger)->get(route('ratings.create', $booking))->assertStatus(402);

        app(PaymentCompletionService::class)->complete($payment, 'stripe', 'pi_rating');
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

    public function test_driver_total_earnings_only_include_successful_payments(): void
    {
        [$driver, $passenger, $booking] = $this->completedBooking(price: 12.50, seats: 2);
        $payment = app(PaymentService::class)->createPendingForBooking($booking);

        $this->actingAs($driver)->get(route('driver.home'))
            ->assertOk()
            ->assertSee('Total Earnings')
            ->assertSee('RM 0.00')
            ->assertSee('Paid passenger payments');

        app(PaymentCompletionService::class)->complete($payment, 'stripe', 'pi_earnings');

        $this->actingAs($driver)->get(route('driver.home'))
            ->assertOk()
            ->assertSee('RM 25.00');
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
