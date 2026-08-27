<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CommunityPartnerController extends Controller
{
    public function index()
    {
        return DB::table('community_partners')
            ->where('active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'platform', 'profile_url', 'avatar_url', 'audience_label', 'description']);
    }
}
