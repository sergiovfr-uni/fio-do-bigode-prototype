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

    public function mine(Request $request)
    {
        return $request->user()->listings()
            ->whereIn('status', ['published', 'paused'])
            ->latest()
            ->paginate(50);
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
            'cover_image'=>['required','string','max:900000'],
            'price'=>['required','numeric','min:0.01'],
            'accepts_trade'=>['sometimes','boolean'],
        ]);
        abort_unless(str_starts_with($data['cover_image'],'data:image/'), 422, 'A imagem principal é inválida.');

        $listing = $user->listings()->create([
            ...$data,
            'status'=>'published',
            'published_at'=>now(),
            'expires_at'=>now()->addDays(30),
        ]);

        return response()->json($listing->load('seller:id,name,kyc_status,reputation_score'), 201);
    }

    public function update(Request $request, Listing $listing)
    {
        $this->assertOwner($request, $listing);
        abort_if($listing->status === 'archived', 422, 'Um anúncio arquivado não pode ser editado.');

        $data = $request->validate([
            'category'=>['required','string','max:60'],
            'title'=>['required','string','max:180'],
            'description'=>['required','string','max:5000'],
            'cover_image'=>['sometimes','nullable','string','max:900000'],
            'price'=>['required','numeric','min:0.01'],
            'accepts_trade'=>['sometimes','boolean'],
        ]);
        if (!empty($data['cover_image'])) {
            abort_unless(str_starts_with($data['cover_image'], 'data:image/'), 422, 'A imagem principal é inválida.');
        } else {
            unset($data['cover_image']);
        }

        $listing->update($data);
        return response()->json($listing->fresh()->load('seller:id,name,kyc_status,reputation_score'));
    }

    public function changeStatus(Request $request, Listing $listing, ListingEntitlementService $entitlements)
    {
        $this->assertOwner($request, $listing);
        $data = $request->validate(['action'=>['required','in:pause,publish,archive']]);

        if ($data['action'] === 'publish') {
            abort_if($listing->status === 'archived', 422, 'Um anúncio excluído não pode ser republicado.');
            abort_unless($request->user()->kyc_status === 'verified', 403, 'Conclua a validação de identidade antes de publicar.');
            if ($listing->status !== 'published') $entitlements->assertCanPublish($request->user());
            $listing->update(['status'=>'published','published_at'=>now(),'expires_at'=>now()->addDays(30)]);
        } elseif ($data['action'] === 'pause') {
            abort_unless($listing->status === 'published', 422, 'Somente anúncios publicados podem ser pausados.');
            $listing->update(['status'=>'paused']);
        } else {
            $listing->update(['status'=>'archived']);
        }

        return response()->json([
            'message'=>match ($data['action']) {
                'publish'=>'Anúncio publicado.',
                'pause'=>'Anúncio pausado.',
                default=>'Anúncio excluído da sua área pública e preservado no histórico.',
            },
            'listing'=>$listing->fresh(),
        ]);
    }

    private function assertOwner(Request $request, Listing $listing): void
    {
        abort_unless((int) $listing->seller_id === (int) $request->user()->id, 403, 'Este anúncio pertence a outro usuário.');
    }
}
