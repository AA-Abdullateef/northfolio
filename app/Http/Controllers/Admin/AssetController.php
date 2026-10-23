<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Services\AssetPriceService;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(private readonly AssetPriceService $priceService) {}

    public function index()
    {
        $assets = Asset::orderBy('name')->paginate(20);
        return view('admin.assets.index', compact('assets'));
    }

    public function create()
    {
        return view('admin.assets.create');
    }

    public function store(Request $request)
    {
        // if exists, return error
        if (Asset::where('label', $request->label)->exists() || Asset::where('name', $request->name)->exists()) {
            return redirect()->back()->withInput()->withErrors(['label' => 'Asset with this name or label already exists.']);
        }

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:50', 'unique:assets,name'],
            'label'            => ['required', 'string', 'max:50'],
            'icon'             => ['nullable', 'string', 'max:191'],
            'active'           => ['nullable', 'boolean'],
            'price_source'     => ['nullable', 'in:manual,coingecko,alphavantage,finnhub'],
            'price_source_id'  => ['nullable', 'required_if:price_source,coingecko,alphavantage,finnhub', 'string', 'max:50'],
            'current_price'    => ['nullable', 'numeric', 'gt:0', 'max:9999999999999999.99999999'],
            'swap_charge_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'buy_charge_rate'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sell_charge_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $asset = Asset::create([...$data, 'active' => $data['active'] ?? true]);

        AuditLog::record(
            action:      'asset.created',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'asset',
            subjectId:   $asset->id,
            after:       $asset->toArray(),
        );

        return redirect()->route('admin.assets.index')->with('success', 'Asset created.');
    }

    public function edit(Asset $asset)
    {
        return view('admin.assets.edit', compact('asset'));
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:50', 'unique:assets,name,' . $asset->id],
            'label'            => ['required', 'string', 'max:50'],
            'icon'             => ['nullable', 'string', 'max:191'],
            'active'           => ['nullable', 'boolean'],
            'price_source'     => ['nullable', 'in:manual,coingecko,alphavantage,finnhub'],
            'price_source_id'  => ['nullable', 'required_if:price_source,coingecko,alphavantage,finnhub', 'string', 'max:50'],
            'current_price'    => ['nullable', 'numeric', 'gt:0', 'max:9999999999999999.99999999'],
            'swap_charge_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'buy_charge_rate'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sell_charge_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $before = $asset->toArray();
        $asset->update($data);

        AuditLog::record(
            action:      'asset.updated',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'asset',
            subjectId:   $asset->id,
            before:      $before,
            after:       $asset->fresh()->toArray(),
        );

        return redirect()->route('admin.assets.index')->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset)
    {
        AuditLog::record(
            action:      'asset.deleted',
            actorId:     auth()->id(),
            actorType:   'admin',
            subjectType: 'asset',
            subjectId:   $asset->id,
            before:      $asset->toArray(),
        );

        $asset->delete();

        return redirect()->route('admin.assets.index')->with('success', 'Asset deleted.');
    }

    public function syncPrices()
    {
        $result = $this->priceService->syncAll();

        AuditLog::record(
            action: 'admin_asset_prices_synced',
            actorId: auth()->id(),
            actorType: 'admin',
            subjectType: 'asset',
            subjectId: null,
            after: $result,
        );

        return redirect()
            ->route('admin.assets.index')
            ->with('success', "Price sync completed. Synced: {$result['synced']}; skipped: {$result['skipped']}; failed: {$result['failed']}.");
    }
}
