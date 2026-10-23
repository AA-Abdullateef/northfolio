<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * List all wallets (mnemonic accounts) with their associated user.
     */
    public function index(Request $request)
    {
        $query = Wallet::with('user')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('fingerprint', 'like', "%{$s}%")
                  ->orWhere('public_id', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($userQuery) =>
                      $userQuery->where('username', 'like', "%{$s}%")
                          ->orWhere('email', 'like', "%{$s}%")
                  );
            });
        }

        $wallets = $query->paginate(25)->withQueryString();

        return view('admin.wallets.index', compact('wallets'));
    }

    /**
     * Show a single wallet with user details.
     * Recovery phrases are never stored — only a one-way hash used for sign-in.
     */
    public function show(Wallet $wallet)
    {
        $wallet->load('user.profile');

        return view('admin.wallets.show', compact('wallet'));
    }

    /**
     * Detach a wallet from its user — does NOT delete the user account.
     */
    public function destroy(Wallet $wallet)
    {
        AuditLog::record(
            action:      'wallet.deleted',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'wallet',
            subjectId:   $wallet->id,
            before:      ['user_id' => $wallet->user_id],
        );

        $wallet->delete();

        return redirect()->route('admin.wallets.index')
                         ->with('success', 'Wallet record deleted.');
    }
}
