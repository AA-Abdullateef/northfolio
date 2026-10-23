<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Method;
use App\Models\SubMethod;
use Illuminate\Http\Request;

class MethodController extends Controller
{
    // ─── Methods ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $methods = Method::withCount('subMethods')->latest()->paginate(20);
        return view('admin.methods.index', compact('methods'));
    }

    public function create()
    {
        return view('admin.methods.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:methods,name'],
        ]);

        $method = Method::create($data);

        AuditLog::record('method.created', auth()->id(), 'admin', 'method', $method->id, null, $method->toArray());

        return redirect()->route('admin.methods.index')->with('success', 'Payment method created.');
    }

    public function edit(Method $method)
    {
        $method->load('subMethods');
        return view('admin.methods.edit', compact('method'));
    }

    public function update(Request $request, Method $method)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:methods,name,' . $method->id],
        ]);

        $before = $method->toArray();
        $method->update($data);

        AuditLog::record('method.updated', auth()->id(), 'admin', 'method', $method->id, $before, $method->fresh()->toArray());

        return redirect()->route('admin.methods.index')->with('success', 'Payment method updated.');
    }

    public function destroy(Method $method)
    {
        AuditLog::record('method.deleted', auth()->id(), 'admin', 'method', $method->id, $method->toArray());
        $method->delete(); // cascades to sub_methods via FK

        return redirect()->route('admin.methods.index')->with('success', 'Method deleted.');
    }

    // ─── Sub-Methods ─────────────────────────────────────────────────────────────

    public function createSubMethod(Method $method)
    {
        return view('admin.methods.sub_methods.create', compact('method'));
    }

    public function storeSubMethod(Request $request, Method $method)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'account_name'   => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'bank_name'      => ['nullable', 'string', 'max:100'],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'swift_code'     => ['nullable', 'string', 'max:20'],
            'iban'           => ['nullable', 'string', 'max:50'],
            'wallet_address' => ['nullable', 'string', 'max:191'],
            'network'        => ['nullable', 'string', 'max:50'],
            'instructions'   => ['nullable', 'string'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $subMethod = $method->subMethods()->create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
        ]);

        AuditLog::record('sub_method.created', auth()->id(), 'admin', 'sub_method', $subMethod->id, null, $subMethod->toArray());

        return redirect()->route('admin.methods.edit', $method)->with('success', 'Sub-method added.');
    }

    public function editSubMethod(Method $method, SubMethod $subMethod)
    {
        return view('admin.methods.sub_methods.edit', compact('method', 'subMethod'));
    }

    public function updateSubMethod(Request $request, Method $method, SubMethod $subMethod)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'account_name'   => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'bank_name'      => ['nullable', 'string', 'max:100'],
            'routing_number' => ['nullable', 'string', 'max:50'],
            'swift_code'     => ['nullable', 'string', 'max:20'],
            'iban'           => ['nullable', 'string', 'max:50'],
            'wallet_address' => ['nullable', 'string', 'max:191'],
            'network'        => ['nullable', 'string', 'max:50'],
            'instructions'   => ['nullable', 'string'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $before = $subMethod->toArray();
        $subMethod->update($data);

        AuditLog::record('sub_method.updated', auth()->id(), 'admin', 'sub_method', $subMethod->id, $before, $subMethod->fresh()->toArray());

        return redirect()->route('admin.methods.edit', $method)->with('success', 'Sub-method updated.');
    }

    public function destroySubMethod(Method $method, SubMethod $subMethod)
    {
        AuditLog::record('sub_method.deleted', auth()->id(), 'admin', 'sub_method', $subMethod->id, $subMethod->toArray());
        $subMethod->delete();

        return redirect()->route('admin.methods.edit', $method)->with('success', 'Sub-method deleted.');
    }
}
