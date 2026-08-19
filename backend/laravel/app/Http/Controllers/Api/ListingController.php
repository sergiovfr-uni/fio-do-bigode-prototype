<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\ListingEntitlementService;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        return Listing::query()
            ->with('seller:id,name,kyc_status,reputation_score')
            ->where('status','published')
            ->latest('published_at')
            ->paginate(20);
    }

    public function show(Listing $listing)
    {
        abort_unless($listing->status === 'published', 404);
        return $listing->load('seller:id,name,kyc_status,reputation_score');
    }

    public function store(Request $request, ListingEntitlementService $entitlements)
    {
        $user = $request->user();
        abort_unless($user->kyc_status === 'verified', 403, 'Conclua a validação de identidade antes de publicar.');
        $entitlements->assertCanPublish($user);

        $data = $request->validate([
            'category'=>['required','string','max:60'],
            'title'=>['required','string','max:180'],
            'description'=>['required','string','max:5000'],
            'price'=>['required','numeric','min:0.01'],
        ]);

        $listing = $user->listings()->create([
            ...$data,
            'status'=>'published',
            'published_at'=>now(),
            'expires_at'=>now()->addDays(30),
        ]);

        return response()->json($listing->load('seller:id,name,kyc_status,reputation_score'), 201);
    }
}
