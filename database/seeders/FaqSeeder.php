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
            ['Why do I need to verify my email or phone number?', 'Verification helps protect GreenPool accounts and confirms that important trip, booking, payment, and safety updates can reach the right user. Follow the verification prompt in your account or profile page.', ['verify email', 'email verification', 'verify phone', 'phone verification', 'otp'], 'Account', false, 13],
            ['How do I change my password?', 'Open Profile, then Account Settings or Security. Choose Change Password, enter your current password, and save a new strong password.', ['password', 'change password', 'forgot password', 'security'], 'Account', false, 14],
            ['How do I add a vehicle?', 'Drivers can open My Vehicles and choose Add Vehicle. Upload clear front, rear, and side photos, then add the Vehicle Geran/VOC details and submit the vehicle for verification.', ['add vehicle', 'new vehicle', 'car photo', 'geran', 'voc'], 'Vehicles', false, 15],
            ['Why was my vehicle photo or document not accepted?', 'Use a clear JPG or PNG image with all details visible. Vehicle photos should show the whole car and clear plates; Geran/VOC images should show all four corners and printed information. Review any highlighted field before resubmitting.', ['vehicle rejected', 'vehicle photo', 'document rejected', 'ocr', 'scan geran', 'voc error'], 'Vehicles', false, 16],
            ['How do I update or remove my vehicle?', 'Open My Vehicles, select the vehicle, and choose Edit to update its details. Use the remove option only for a vehicle that is no longer used; active trip requirements may prevent removal.', ['edit vehicle', 'delete vehicle', 'remove vehicle', 'update vehicle'], 'Vehicles', false, 17],
            ['Where do I choose pickup and destination locations?', 'When searching or creating a trip, type the location and select one of the displayed location suggestions. Selecting a suggestion helps GreenPool use a valid map location for the route.', ['pickup', 'destination', 'location', 'map', 'route', 'address'], 'Trips', false, 18],
            ['What happens after I submit a booking request?', 'The driver receives your request and can accept or reject it. You can check the latest status in My Bookings and Notifications. Do not assume the ride is confirmed until it is accepted.', ['booking pending', 'booking request', 'after booking', 'driver accept', 'booking status'], 'Bookings', false, 19],
            ['How do I see or reply to a message?', 'Open Messages, choose the booking conversation, type your message, and send it. Messaging is available only for the relevant active booking and closes after cancellation or trip completion.', ['reply message', 'inbox', 'message not send', 'chat'], 'Messages', false, 20],
            ['What payment methods are available?', 'For a completed trip, GreenPool may offer the payment methods shown on the checkout page, such as online payment or cash confirmation. The available method and payment status are shown in Payments and My Bookings.', ['payment method', 'cash', 'online payment', 'stripe', 'checkout'], 'Payments', false, 21],
            ['Where can I find my payment receipt?', 'Open Payments, select the relevant payment, and choose the receipt option when it is available. Keep the receipt for your own trip record.', ['receipt', 'payment receipt', 'proof of payment', 'invoice'], 'Payments', false, 22],
            ['How do notification settings work?', 'Open Settings and turn a notification category on or off, then Save preferences. Optional trip, booking, payment, message, and rating notifications follow these settings. Emergency and account-security notices remain enabled for safety.', ['notification setting', 'turn off notifications', 'mute notification', 'settings notification'], 'Notifications', false, 23],
            ['How do I report a safety issue during a trip?', 'Use the emergency or report function available for the relevant trip or booking. Provide a clear description. Emergency-related notifications are always delivered even when optional notification categories are turned off.', ['emergency', 'safety', 'report issue', 'panic', 'help during trip'], 'Safety', false, 24],
            ['Why can I not rate another user yet?', 'Ratings are available only after an eligible completed trip. Depending on the payment and trip status, the rating option may appear in Ratings, My Bookings, or a notification.', ['cannot rate', 'rating unavailable', 'review unavailable', 'rate'], 'Ratings', false, 25],
            ['How do I search for an attraction?', 'Open Tourist Attractions and type the attraction name in the search bar. You can choose a Google suggestion or search the saved Malaysian attraction records, then open a result for details.', ['search attraction', 'attraction search', 'google suggestion', 'find place'], 'Tourist Attractions', false, 26],
            ['Why does an attraction have no photo or some details missing?', 'Attraction records are imported from Google Places. Information can vary by place. GreenPool shows the available saved photo and details; where a provider does not supply a value, the attraction page shows the available information instead of inventing it.', ['missing attraction photo', 'no photo', 'attraction details', 'missing details'], 'Tourist Attractions', false, 27],
            ['What can the GreenPool Help Bot answer?', 'The Help Bot answers approved GreenPool FAQ topics such as trips, bookings, payments, messages, ratings, notifications, vehicles, account setup, and attractions. It does not access private account details or make bookings for you.', ['help bot', 'faq bot', 'chatbot', 'bot privacy'], 'Help', false, 28],
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
