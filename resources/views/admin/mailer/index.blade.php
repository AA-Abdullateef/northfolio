@extends('layouts.admin')
@section('title', 'Mailer')
@section('page-title', 'Mailer')

@section('content')
<div class="card" style="max-width:700px">
    <div class="card-header"><span class="card-title">Send Email</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.mailer.send') }}">
            @csrf

            <div class="form-group">
                <label class="form-label">Recipient</label>
                <select name="user_id" class="form-control">
                    <option value="">— All Customers (bulk) —</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ old('user_id') === $user->id ? 'selected' : '' }}>
                            {{ $user->username }} — {{ $user->email }}
                        </option>
                    @endforeach
                </select>
                <div class="td-muted" style="margin-top:6px;font-size:12px">
                    Leave blank to send to all customers. Emails are queued — delivery may be delayed by a few seconds.
                </div>
                @error('user_id')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" placeholder="Email subject line">
                @error('subject')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="10" placeholder="Write your message here...">{{ old('message') }}</textarea>
                @error('message')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary"
                onclick="return confirm('Send this email? This cannot be undone.')">
                Send Email
            </button>
        </form>
    </div>
</div>
@endsection