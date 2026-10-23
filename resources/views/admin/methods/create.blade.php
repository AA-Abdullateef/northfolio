@extends('layouts.admin')
@section('title', 'New Method')
@section('page-title', 'New Payment Method')
@section('topbar-actions')
    <a href="{{ route('admin.methods.index') }}" class="btn btn-ghost btn-sm">← Methods</a>
@endsection

@section('content')
<div class="card" style="max-width:480px">
    <div class="card-header"><span class="card-title">Create Method</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.methods.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Name <span style="color:var(--text-faint)">(e.g. Bitcoin, Bank Transfer)</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Bitcoin">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Create Method</button>
        </form>
    </div>
</div>
@endsection
