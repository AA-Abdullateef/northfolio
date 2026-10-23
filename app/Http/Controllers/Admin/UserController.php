<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Country;
use App\Models\Profile;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request)
    {
        $query = User::customers()->with('profile')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('username', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load('profile', 'photo', 'wallet');
        $assets   = Asset::active()->get();
        $balances = $user->wallet
            ? $this->ledger->allBalancesFor($user->wallet->id)
            : [];

        return view('admin.users.show', compact('user', 'assets', 'balances'));
    }

    public function create()
    {
        $countries = Country::orderBy('name')->get();
        return view('admin.users.create', compact('countries'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username'   => ['required', 'string', 'max:30', 'unique:users,username'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'password'   => ['required', 'string', 'min:8'],
            'admin'      => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'username'          => $data['username'],
            'email'             => strtolower($data['email']),
            'country_id'        => $data['country_id'],
            'password'          => $data['password'],
            'admin'             => $data['admin'] ?? false,
            'email_verified_at' => now(),
            'profile_completed' => true,
        ]);

        Profile::create(['user_id' => $user->id]);

        AuditLog::record(
            action:      'user.created',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'user',
            subjectId:   $user->id,
            after:       ['username' => $user->username, 'email' => $user->email],
        );

        return redirect()->route('admin.users.show', $user)
                         ->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        $user->load('profile');
        $countries = Country::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'countries'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'username'   => ['required', 'string', 'max:30', 'unique:users,username,' . $user->id],
            'email'      => ['required', 'email', 'unique:users,email,' . $user->id],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'status'     => ['nullable', 'string', 'in:active,suspended,deactivated'],
            'first_name' => ['nullable', 'string', 'max:55'],
            'last_name'  => ['nullable', 'string', 'max:55'],
            'phone'      => ['nullable', 'string', 'max:25'],
            'gender'     => ['nullable', 'string', 'in:Male,Female,Other'],
            'state'      => ['nullable', 'string', 'max:50'],
            'address'    => ['nullable', 'string', 'max:100'],
            'zip'        => ['nullable', 'string', 'max:25'],
            'dob'        => ['nullable', 'date'],
        ]);

        $before = $user->only(['username', 'email', 'country_id', 'status']);

        $user->update([
            'username'   => $data['username'],
            'email'      => strtolower($data['email']),
            'country_id' => $data['country_id'],
            'status'     => $data['status'] ?? $user->status,
            'profile_completed' => true,
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            collect($data)->only([
                'first_name', 'last_name', 'gender',
                'phone', 'state', 'address', 'zip', 'dob',
            ])->toArray()
        );

        AuditLog::record(
            action:      'user.updated',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'user',
            subjectId:   $user->id,
            before:      $before,
            after:       $user->fresh()->only(['username', 'email', 'country_id', 'status']),
        );

        return redirect()->route('admin.users.show', $user)
                         ->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        AuditLog::record(
            action:      'user.deleted',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'user',
            subjectId:   $user->id,
            before:      $user->only(['username', 'email']),
        );

        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $request->password]);
        $user->tokens()->delete();

        AuditLog::record(
            action:      'user.password_reset',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'user',
            subjectId:   $user->id,
        );

        return back()->with('success', 'Password reset.');
    }

    public function toggleTwoFactor(User $user)
    {
        $before = ['two_factor' => $user->two_factor];
        $user->update(['two_factor' => ! $user->two_factor]);

        AuditLog::record(
            action:      'user.two_factor_toggled',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'user',
            subjectId:   $user->id,
            before:      $before,
            after:       ['two_factor' => $user->two_factor],
        );

        return back()->with('success', 'Two-factor updated.');
    }

    public function verifyEmail(User $user)
    {
        if (! $user->email_verified_at) {
            $user->update([
                'email_verified_at' => now(),
            ]);

            AuditLog::record(
                action: 'user.email_verified',
                actorId: auth()->id(),
                actorType: 'admin',
                subjectType: 'user',
                subjectId: $user->id,
                after: [
                    'email_verified_at' => $user->fresh()->email_verified_at,
                ],
            );
        }

        return back()->with('success', 'Email verified successfully.');
    }

    /**
     * Per-asset transaction history for a specific user.
     */
    public function assetDetails(User $user, Asset $asset)
    {
        $transactions = Transaction::forUser($user->id)
                                   ->where('asset_id', $asset->id)
                                   ->with('subMethod')
                                   ->latest()
                                   ->paginate(30);

        $balance = $user->wallet
            ? $this->ledger->balanceFor($user->wallet->id, $asset->id)
            : 0;

        return view('admin.users.asset_details', compact(
            'user', 'asset', 'balance', 'transactions'
        ));
    }
}
