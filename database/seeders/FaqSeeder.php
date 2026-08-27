<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['How do I find and book a ride?', 'Open Find a Ride, search for a suitable trip, select it, choose your pickup point and seats, then submit the booking request. The driver must accept it before the ride is confirmed.', ['find ride', 'book ride', 'booking', 'request ride', 'passenger'], 'Bookings', true, 1],
            ['How do I create a trip as a driver?', 'Open My Trips and choose Create Trip. Select your verified vehicle, choose departure and destination from the location suggestions, set the date, time, available seats and fare, then publish the trip.', ['create trip', 'publish trip', 'driver trip', 'post trip'], 'Trips', true, 2],
            ['How do I cancel a booking?', 'Open My Bookings, select the booking and choose Cancel Booking. Cancellation availability depends on the booking and trip status.', ['cancel booking', 'cancel ride', 'booking cancellation'], 'Bookings', false, 3],
            ['When do I need to pay?', 'Payment becomes available after the driver marks an accepted trip as completed. Open the payment notification, My Bookings, or Payments and select Pay Now.', ['payment', 'pay', 'pay now', 'checkout', 'fare'], 'Payments', true, 4],
            ['How do ratings work?', 'After a completed trip, the driver can rate the passenger. Passengers can rate the driver after their payment is completed. Ratings are available for seven days after the trip.', ['rating', 'rate driver', 'rate passenger', 'review'], 'Ratings', false, 5],
            ['How do I send a message to my driver or passenger?', 'Open Messages and select the relevant booking conversation. Chat is available while the booking is pending or accepted, and closes after a trip is completed or cancelled.', ['message', 'chat', 'contact driver', 'contact passenger'], 'Messages', true, 6],
            ['How do notifications work?', 'Use the bell icon at the top of the page. You can view all notifications, filter unread items or notification types, mark them as read, and delete old items.', ['notification', 'bell', 'unread', 'mark read'], 'Notifications', false, 7],
            ['How do I explore tourist attractions?', 'Open Tourist Attractions to browse featured Malaysian places, filter by state, search saved places or choose a Google suggestion, then open an attraction for more details or save it to Favourites.', ['attraction', 'tourist', 'tourism', 'favourite attraction', 'explore malaysia'], 'Tourist Attractions', true, 8],
            ['Where do attraction details and photos come from?', 'GreenPool imports selected Malaysian tourist attraction details and photos from Google Places into its own database. The attraction page then reads local saved data so it does not need a live search every time.', ['google places', 'attraction photo', 'attraction data', 'api', 'image'], 'Tourist Attractions', false, 9],
            ['How do I save or remove a favourite attraction?', 'Open an attraction and press Save to Favourites. Press Saved again, or open the Favourites tab and remove it there, to delete it from your personal list.', ['favourite', 'favorite', 'save attraction', 'remove favourite'], 'Tourist Attractions', false, 10],
            ['Why can I not create a trip?', 'Drivers need an uploaded valid driving licence and an active vehicle before they can create a trip. Check My Profile and My Vehicles, then correct any missing information.', ['cannot create trip', 'create trip failed', 'driving licence', 'active vehicle'], 'Driver setup', false, 11],
            ['How can I update my profile?', 'Open Profile from the sidebar to update your name, phone number, profile photo and other available account information.', ['profile', 'change name', 'phone number', 'account'], 'Account', false, 12],
        ] as [$question, $answer, $keywords, $category, $featured, $sortOrder]) {
            Faq::updateOrCreate(['question' => $question], [
                'answer' => $answer,
                'keywords' => $keywords,
                'category' => $category,
                'is_featured' => $featured,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
