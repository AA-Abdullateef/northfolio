<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserToken;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function index(Request $request)
    {
        $query = UserToken::with('user.profile')->latest();

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->purpose);
        }

        $tokens = $query->paginate(25)->withQueryString();

        return view('admin.tokens.index', compact('tokens'));
    }

    public function destroy(UserToken $token)
    {
        $token->delete();
        return back()->with('success', 'Token revoked.');
    }
}