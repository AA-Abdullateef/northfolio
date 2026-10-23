<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\CountryResource;
use App\Http\Resources\API\V1\MethodResource;
use App\Http\Resources\API\V1\SubMethodResource;
use App\Models\Country;
use App\Models\Method;
use App\Models\SubMethod;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    use ApiResponse;

    public function countries(): JsonResponse
    {
        return $this->success('Countries.', [
            'countries' => CountryResource::collection(
                Country::orderBy('name')->get()
            ),
        ]);
    }

    public function methods(): JsonResponse
    {
        return $this->success('Methods.', [
            'methods' => MethodResource::collection(
                Method::with(['subMethods' => fn ($query) => $query->active()->orderBy('name')])
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function subMethods(Request $request): JsonResponse
    {
        $query = SubMethod::active()->with('method')->orderBy('name');

        if ($request->filled('method_id')) {
            $query->where('method_id', $request->query('method_id'));
        }

        return $this->success('Sub methods.', [
            'sub_methods' => SubMethodResource::collection($query->get()),
        ]);
    }
}
