@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
<div class="login-card">
    <form method="POST" action="{{ route('admin.login.submit') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="user">Email or Username</label>
            <input
                type="text"
                id="user"
                name="user"
                class="form-control"
                value="{{ old('user') }}"
                placeholder="admin@example.com"
                autocomplete="username"
                autofocus
            >
            @error('user')
                <div class="form-error">{{ $message }}</div>
            @enderror
            @error('access')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="••••••••"
                autocomplete="current-password"
            >
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="remember-row">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Keep me signed in</label>
        </div>

        <button type="submit" class="btn-login">Sign In</button>
    </form>
</div>
@endsection