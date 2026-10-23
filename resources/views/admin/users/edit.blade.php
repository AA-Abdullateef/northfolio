@extends('layouts.admin')
@section('title', 'Edit ' . $user->username)
@section('page-title', 'Edit User')
@section('topbar-actions')
    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-ghost btn-sm">← Back</a>
@endsection

@section('content')
<div class="card" style="max-width:780px">
    <div class="card-header"><span class="card-title">Edit — {{ $user->username }}</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf @method('PUT')

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username', $user->username) }}">
                    @error('username')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}">
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Country</label>
                    <select name="country_id" class="form-control">
                        <option value="">— Select Country —</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}"
                                {{ old('country_id', $user->country_id) === $country->id ? 'selected' : '' }}>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('country_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">— None —</option>
                        @foreach(['active','suspended','deactivated'] as $s)
                            <option value="{{ $s }}" {{ old('status', $user->status) === $s ? 'selected' : '' }}>
                                {{ ucfirst($s) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="divider"></div>
            <div style="font-size:11px;font-family:var(--font-mono);color:var(--text-faint);letter-spacing:.1em;text-transform:uppercase;margin-bottom:14px">Profile</div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $user->profile?->first_name) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $user->profile?->last_name) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->profile?->phone) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">— Select —</option>
                        @foreach(['Male','Female','Other'] as $g)
                            <option value="{{ $g }}" {{ old('gender', $user->profile?->gender) === $g ? 'selected' : '' }}>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="{{ old('state', $user->profile?->state) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Zip</label>
                    <input type="text" name="zip" class="form-control" value="{{ old('zip', $user->profile?->zip) }}">
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $user->profile?->address) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" class="form-control" value="{{ old('dob', $user->profile?->dob?->toDateString()) }}">
                </div>
            </div>

            <div class="flex gap-2" style="margin-top:8px">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection