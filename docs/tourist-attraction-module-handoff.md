# GreenPool Tourist Attraction Discovery — Sprint 1 & Sprint 2 Handoff

Use this document as project context before changing the Tourist Attraction Discovery module. Read the existing implementation first and keep changes scoped to this module.

## Project constraints

- Project: **GreenPool**, a Malaysian Community Carpool Management System aligned with UN SDG 11.
- Framework: Laravel / PHP MVC.
- Frontend: Blade, Tailwind CSS v4, vanilla JavaScript only.
- Database: **MySQL only**. Do not use Supabase, microservices, or a multi-server design.
- Local environment: XAMPP. Production target: Hostinger single server.
- Module owner: Chia, branch `tourist-attraction`.
- Existing users use Laravel's default primary key `users.id`; the `favourites.user_id` column correctly refers to it.

## Module purpose and fixed use-case names

The module helps Malaysian users discover tourist attractions while planning a carpool trip.

Required use cases:

1. **Browse & Search attractions** — includes attraction details.
2. **Filter attractions by state**.
3. **Manage Favourite Attractions** — save and remove sub-flows.

## Sprint 1 completed work

### Database and Eloquent

Original tables:

- `tourist_attractions`
  - `attraction_id`, `attraction_name`, `state`, `description`, `location`, `image_url`, timestamps.
- `favourites`
  - `favourite_id`, `attraction_id`, `user_id`, timestamps.
  - Unique `(attraction_id, user_id)`.

Models and relationships:

- `App\Models\Attraction`
  - `hasMany(Favourite::class, 'attraction_id', 'attraction_id')`
  - `hasOne(AttractionDetail::class, 'attraction_id', 'attraction_id')`
- `App\Models\Favourite`
  - belongs to `Attraction` and `User`.
- `App\Models\User`
  - has favourites relationship using `users.id`.

### Controller and routes

`App\Http\Controllers\AttractionController` provides:

- `index()` — browse, search, state filter, favourites tab.
- `show()` — attraction details.
- `storeFavourite()` and `destroyFavourite()`.

Routes are under `auth` and `verified` middleware:

- `attractions.index`
- `attractions.show`
- `attractions.favourites.store`
- `attractions.favourites.destroy`

### UI

Blade views:

- `resources/views/attractions/index.blade.php`
- `resources/views/attractions/show.blade.php`

They follow the supplied Figma styling:

- Discover / Favourites tabs.
- Search bar and Malaysian state chips.
- Featured cards and standard attraction cards.
- Favourite heart buttons.
- Figma-style detail page with About, Essential Information, and Find a Ride area.
- Local category illustrations are used only if no real image is available.

### Role-sensitive Find a Ride action

The Ride Booking module is passenger-only. Do **not** bypass its authorization.

- Passenger sees `Find a Ride` and goes to `passenger.booking`.
- Driver sees `View My Trips` and goes to `driver.trips.index`.

This avoids a 403 when a Driver visits an attraction detail page.

### Tests

`tests/Feature/AttractionDiscoveryTest.php` verifies:

- Browse/search/filter behavior.
- Save and remove favourites.

Run with:

```powershell
php artisan test --filter=AttractionDiscoveryTest
```

## Sprint 2 completed work — final architecture

### Current API decision

**Google Places API (New)** is the final runtime provider for live attraction enrichment.

The earlier Geoapify, OpenTripMap, and Wikimedia code has been removed intentionally. Do not reintroduce it unless the project owner explicitly changes the architecture.

Current MySQL rows were originally imported during development and provide the initial attraction list, Malaysian state, address, and coordinates. The old importer was removed, so a new developer with an empty database must import `database/seed-data/tourist-attractions.sql`; `php artisan migrate` creates tables but does not recreate the 240 attraction records.

### Google Places policy-safe design

Google content must not be permanently cached in MySQL. The application:

- Stores only `google_place_id`, which Google permits to be retained.
- Gets Google photos, description, opening hours, phone number, and website at display time.
- Does **not** save Google photo URLs, photo names, summaries, hours, or contact details permanently.
- Verifies that a Text Search result has a `country` address component with code `MY` before storing a Google Place ID.

### Google data flow

```text
Existing MySQL attraction (name + state + coordinates)
  -> Google Text Search: "name, state, Malaysia"
  -> validate country component = MY and name similarity
  -> save only google_place_id
  -> Google Place Details at render time
  -> Google Place Photo through a Laravel image proxy
  -> fallback to existing local image / illustration if unavailable or rate limited
```

### Google implementation files

- `app/Services/Attractions/GooglePlacesService.php`
  - Finds and persists only a verified Malaysian Google Place ID.
  - Retrieves live Place Details and photo media.
  - Applies daily and per-minute request limits.
- `app/Models/GooglePlacesUsage.php`
  - Tracks request count per day in MySQL.
- `app/Http/Controllers/AttractionController.php`
  - Automatic Google details on attraction detail pages.
  - Google image proxy endpoints for detail and card images.
- `database/migrations/2026_08_12_000001_add_google_place_id_to_attraction_details_table.php`
- `database/migrations/2026_08_12_000002_create_google_places_usage_table.php`

Relevant routes:

- `attractions.google-photo` — short-lived signed detail-page photo URL.
- `attractions.google-card-photo` — automatic card photo request.

### Automatic image behavior

- Featured cards and regular list cards automatically attempt to fetch a live Google photo if `image_url` is empty.
- Existing real local image URLs are used directly and do not trigger a Google request.
- If Google cannot match the place, has no photo, or the request cap is reached, the card shows its category illustration.
- Attraction detail pages automatically load live Google details; there is no longer a manual “Load live Google details & photo” button.

## Database additions approved for Sprint 2

`attraction_details` is an approved extension table. It keeps richer attraction information separate from the original ERD table.

Important fields still used by the Google flow:

- `attraction_id`
- `latitude`, `longitude` — assist matching with Google.
- `google_place_id` — only persistent Google identifier.

`google_places_usage` contains:

- `usage_date` — unique date.
- `request_count` — total Google Places requests reserved by the application that day.

Do not delete these tables or fields.

## Required environment configuration

Never commit API keys or paste them into chat.

Each developer using Google needs a private `.env` entry:

```env
GOOGLE_MAPS_API_KEY=their_private_key
GOOGLE_PLACES_MAX_CALLS_PER_DAY=100
GOOGLE_PLACES_MAX_CALLS_PER_MINUTE=20
GOOGLE_PLACES_AUTO_LOAD_FEATURED=true
GOOGLE_PLACES_AUTO_LOAD_DETAILS=true
GOOGLE_PLACES_AUTO_LOAD_CARDS=true
```

Google Cloud requirements:

- Enable **Places API (New)** only.
- Restrict each API key to Places API (New).
- Use a separate key per developer in the same Google Cloud project.
- For Hostinger production, use a separate production key restricted to Hostinger's public outbound IP.
- Do not place a Google key in Blade, JavaScript, or GitHub.

### Budget safety

The Laravel `100/day` limit is a per-database limit. It is useful on one production server, but each teammate's local database tracks its own count.

For team development, use a lower individual daily limit such as `20/day`. Use Google Cloud budget alerts and project quotas as an additional safeguard.

At 100 requests/day, the maximum over 45 days is 4,500 requests. A first-time card normally needs up to Text Search + Details + Photo; subsequent renders normally need Details + Photo. Google pricing and free usage caps can change, so always check the current Google Cloud pricing page before raising the limit.

## Setup for a teammate pulling this branch

```powershell
git checkout tourist-attraction
git pull origin tourist-attraction
composer install
npm install
php artisan migrate
php artisan config:clear
npm run dev
```

Also required:

1. Configure MySQL in `.env`.
2. Import `database/seed-data/tourist-attractions.sql` if the local database is empty. It contains 240 Malaysian attractions and their related `attraction_details` records. There is intentionally no `AttractionSeeder`; do not use `php artisan db:seed` to populate this module.
3. Add a private Google key to `.env` only if Google live images/details are needed.

## Change rules for future work

- Keep standard Laravel MVC; do not introduce Supabase, microservices, or a second backend.
- Preserve the documented use-case names.
- Do not change existing primary key names.
- Do not weaken Passenger/Driver authorization to solve a Tourist Attraction UI issue.
- Do not persist Google photo URLs or place content in MySQL.
- Do not auto-load Google images for every possible record without considering daily limits, request concurrency, and cost.
- Before editing, inspect routes, models, migrations, controller, and Blade files to follow the existing style.
- Keep commits focused using: `type(scope): subject`.

## Useful verification commands

```powershell
php artisan migrate
php artisan config:clear
php artisan route:list --name=attractions
php artisan test --filter=AttractionDiscoveryTest
php vendor\bin\pint --dirty
```
