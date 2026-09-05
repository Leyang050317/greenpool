<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use App\Models\Favourite;
use App\Services\Attractions\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AttractionController extends Controller
{
    public function autocomplete(Request $request, GooglePlacesService $googlePlaces): JsonResponse
    {
        $request->validate(['input' => ['required', 'string', 'min:2', 'max:255']]);

        try {
            return response()->json([
                'data' => $googlePlaces->autocompleteTouristAttractions($request->string('input')->trim()->toString()),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function openGooglePlace(Request $request, GooglePlacesService $googlePlaces): RedirectResponse
    {
        $request->validate(['place_id' => ['required', 'string', 'max:255']]);

        try {
            $place = $googlePlaces->detailsByPlaceId($request->string('place_id')->toString());
        } catch (RuntimeException $exception) {
            return redirect()->route('attractions.index')->with('error', $exception->getMessage());
        }

        $placeId = (string) data_get($place, 'id');
        $name = trim((string) data_get($place, 'displayName.text'));
        $state = $googlePlaces->stateFor($place);
        if ($placeId === '' || $name === '' || ! $state) {
            return redirect()->route('attractions.index')->with('error', 'Google could not verify this attraction’s Malaysian location.');
        }

        $attraction = DB::transaction(function () use ($place, $placeId, $name, $state): Attraction {
            $existing = Attraction::query()->whereHas('detail', fn ($query) => $query->where('google_place_id', $placeId))->first();
            if ($existing) {
                return $existing;
            }

            $attraction = Attraction::query()->create([
                'attraction_name' => mb_substr($name, 0, 150),
                'state' => mb_substr($state, 0, 50),
                'description' => "Discover {$name} in {$state}, Malaysia.",
                'location' => mb_substr((string) data_get($place, 'formattedAddress'), 0, 255) ?: null,
                'image_url' => null,
            ]);
            $attraction->detail()->create([
                'category' => $this->categoryFor((string) data_get($place, 'primaryType')),
                'source_name' => 'Google Places',
                'source_place_id' => $placeId,
                'google_place_id' => $placeId,
                'latitude' => data_get($place, 'location.latitude'),
                'longitude' => data_get($place, 'location.longitude'),
            ]);

            return $attraction;
        });

        return redirect()->route('attractions.show', $attraction);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $state = trim((string) $request->query('state', ''));
        $tab = $request->query('tab') === 'favourites' ? 'favourites' : 'discover';

        $attractions = Attraction::query()
            ->with('detail')
            ->when($tab === 'favourites', fn ($query) => $query->whereHas(
                'favourites',
                fn ($query) => $query->where('user_id', $request->user()->getKey())
            ))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('attraction_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($state !== '', fn ($query) => $query->where('state', $state))
            ->withExists([
                'favourites as is_favourited' => fn ($query) => $query->where('user_id', $request->user()->getKey()),
            ])
            // Google importer inserts the most popular photo-ready places first.
            ->orderBy('attraction_id')
            ->paginate(9)
            ->withQueryString();

        $quickStates = ['Kuala Lumpur', 'Penang', 'Melaka', 'Sabah', 'Selangor', 'Pahang', 'Kedah'];
        $moreStates = ['Johor', 'Kelantan', 'Labuan', 'Negeri Sembilan', 'Perak', 'Perlis', 'Putrajaya', 'Sarawak', 'Terengganu'];
        $featured = collect();

        if ($tab === 'discover' && $search === '' && $state === '') {
            $featured = Attraction::query()
                ->with('detail')
                ->withExists([
                    'favourites as is_favourited' => fn ($query) => $query->where('user_id', $request->user()->getKey()),
                ])
                ->orderBy('attraction_id')
                ->limit(3)
                ->get();
        }

        $favouritesCount = Favourite::query()
            ->where('user_id', $request->user()->getKey())
            ->count();
        $googleAutoCards = config('services.google_places.auto_load_cards');

        return view('attractions.index', compact(
            'attractions',
            'search',
            'state',
            'quickStates',
            'moreStates',
            'featured',
            'googleAutoCards',
            'tab',
            'favouritesCount',
        ));
    }

    public function show(Request $request, Attraction $attraction, GooglePlacesService $googlePlaces): View
    {
        $attraction->load(['detail'])->loadCount('favourites');
        $googlePlace = null;
        $googlePhotoUrl = null;
        $googleError = null;

        if (config('services.google_places.auto_load_details')) {
            try {
                $googlePlace = $googlePlaces->detailsFor($attraction);
                if ($photoName = data_get($googlePlace, 'photos.0.name')) {
                    $googlePhotoUrl = URL::temporarySignedRoute('attractions.google-photo', now()->addMinutes(5), [
                        'attraction' => $attraction,
                        'photo' => $photoName,
                    ]);
                }
            } catch (RuntimeException $exception) {
                $googleError = $exception->getMessage();
            }
        }

        $isFavourited = $attraction->favourites()
            ->where('user_id', $request->user()->getKey())
            ->exists();

        $rideDestination = $attraction->attraction_name;
        $mapDestination = $attraction->location ?: "{$attraction->attraction_name}, {$attraction->state}, Malaysia";
        $googleDirectionsUrl = 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($mapDestination);

        return view('attractions.show', compact(
            'attraction',
            'isFavourited',
            'googlePlace',
            'googlePhotoUrl',
            'googleError',
            'rideDestination',
            'googleDirectionsUrl',
        ));
    }

    public function googlePhoto(Request $request, Attraction $attraction, GooglePlacesService $googlePlaces): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        try {
            $response = $googlePlaces->photo((string) $request->query('photo'));
        } catch (RuntimeException $exception) {
            abort(429, $exception->getMessage());
        }

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'image/jpeg'),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function googleCardPhoto(Attraction $attraction, GooglePlacesService $googlePlaces): Response
    {
        try {
            $response = $googlePlaces->photoFor($attraction);
        } catch (RuntimeException $exception) {
            abort(404, $exception->getMessage());
        }

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'image/jpeg'),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function storeFavourite(Request $request, Attraction $attraction): RedirectResponse
    {
        Favourite::query()->firstOrCreate([
            'attraction_id' => $attraction->attraction_id,
            'user_id' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Attraction saved to favourites.');
    }

    public function destroyFavourite(Request $request, Attraction $attraction): RedirectResponse
    {
        Favourite::query()
            ->where('attraction_id', $attraction->attraction_id)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail()
            ->delete();

        return back()->with('success', 'Attraction removed from favourites.');
    }

    private function categoryFor(string $primaryType): string
    {
        return match ($primaryType) {
            'museum' => 'Museum',
            'historical_landmark' => 'Historical Landmark',
            'national_park' => 'National Park',
            'amusement_park' => 'Theme Park',
            'zoo' => 'Wildlife & Zoo',
            'art_gallery' => 'Art & Culture',
            default => 'Tourist Attraction',
        };
    }
}
