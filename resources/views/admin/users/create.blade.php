@extends('layouts.admin')
@section('title', 'Create User')
@section('page-title', 'Create User')
@section('topbar-actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">← Users</a>
@endsection

@section('content')
<div class="card" style="max-width:680px">
    <div class="card-header"><span class="card-title">New User</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username') }}" placeholder="username">
                    @error('username')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="user@example.com">
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Country</label>
                    <select name="country_id" class="form-control">
                        <option value="">— Select Country —</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ old('country_id') === $country->id ? 'selected' : '' }}>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('country_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 8 characters">
                    @error('password')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="admin" value="1" {{ old('admin') ? 'checked' : '' }}>
                    <span class="form-label" style="margin:0">Grant admin access</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
</div>
@endsection