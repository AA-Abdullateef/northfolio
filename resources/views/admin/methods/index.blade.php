@extends('layouts.admin')
@section('title', 'Payment Methods')
@section('page-title', 'Payment Methods')
@section('topbar-actions')
    <a href="{{ route('admin.methods.create') }}" class="btn btn-primary btn-sm">+ New Method</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Methods</span></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Sub-Methods</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($methods as $method)
                <tr>
                    <td style="font-weight:500">{{ $method->name }}</td>
                    <td class="td-mono">{{ $method->sub_methods_count }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.methods.edit', $method) }}" class="btn btn-ghost btn-sm">Manage</a>
                            <form method="POST" action="{{ route('admin.methods.destroy', $method) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Delete {{ $method->name }} and all its sub-methods?')">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:var(--text-faint);padding:32px">No methods found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection