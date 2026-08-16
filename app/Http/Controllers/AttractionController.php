<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use App\Models\Favourite;
use App\Services\Attractions\GooglePlacesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AttractionController extends Controller
{
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
            ->when($tab === 'discover' && $search === '' && $state === '', function ($query) {
                $query->orderByRaw("CASE
                    WHEN attraction_name LIKE '%Petronas%' THEN 1
                    WHEN attraction_name LIKE '%Batu Cave%' THEN 2
                    WHEN attraction_name LIKE '%Penang Hill%' THEN 3
                    WHEN attraction_name LIKE '%Kinabalu%' THEN 4
                    WHEN attraction_name LIKE '%A Famosa%' THEN 5
                    WHEN attraction_name LIKE '%Langkawi%' THEN 6
                    ELSE 99
                END");
            })
            ->orderBy('attraction_name')
            ->paginate(9)
            ->withQueryString();

        $quickStates = ['Kuala Lumpur', 'Penang', 'Melaka', 'Sabah', 'Selangor', 'Pahang', 'Kedah'];
        $moreStates = ['Johor', 'Kelantan', 'Labuan', 'Negeri Sembilan', 'Perak', 'Perlis', 'Putrajaya', 'Sarawak', 'Terengganu'];

        $featured = collect();

        if ($tab === 'discover' && $search === '' && $state === '') {
            // The order gives the All States landing view a recognisable
            // Malaysian travel-first starting set.
            $featuredNames = ['Kota A Famosa', 'Pulau Langkawi', 'Kinabalu Earthquake Monument'];
            $featured = Attraction::query()
                ->whereIn('attraction_name', $featuredNames)
                ->with('detail')
                ->withExists([
                    'favourites as is_favourited' => fn ($query) => $query->where('user_id', $request->user()->getKey()),
                ])
                ->get()
                ->sortBy(fn (Attraction $attraction) => array_search($attraction->attraction_name, $featuredNames, true))
                ->values();
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
}
