<?php

namespace App\Http\Controllers;

use App\Models\Attraction;
use App\Models\Favourite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttractionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $state = trim((string) $request->query('state', ''));
        $tab = $request->query('tab') === 'favourites' ? 'favourites' : 'discover';

        $attractions = Attraction::query()
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
            ->orderBy('attraction_name')
            ->paginate(9)
            ->withQueryString();

        $quickStates = ['Kuala Lumpur', 'Penang', 'Melaka', 'Sabah', 'Selangor', 'Pahang', 'Kedah'];
        $moreStates = ['Johor', 'Kelantan', 'Labuan', 'Negeri Sembilan', 'Perak', 'Perlis', 'Putrajaya', 'Sarawak', 'Terengganu'];

        $featured = collect();

        if ($tab === 'discover' && $search === '' && $state === '') {
            $featuredNames = ['Petronas Twin Towers', 'Penang Hill', 'Mount Kinabalu'];
            $featured = Attraction::query()
                ->whereIn('attraction_name', $featuredNames)
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

        return view('attractions.index', compact(
            'attractions',
            'search',
            'state',
            'quickStates',
            'moreStates',
            'featured',
            'tab',
            'favouritesCount',
        ));
    }

    public function show(Request $request, Attraction $attraction): View
    {
        $attraction->loadCount('favourites');

        $isFavourited = $attraction->favourites()
            ->where('user_id', $request->user()->getKey())
            ->exists();

        return view('attractions.show', compact('attraction', 'isFavourited'));
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
