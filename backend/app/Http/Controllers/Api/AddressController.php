<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->addresses()->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $address = DB::transaction(function () use ($request, $data): Address {
            if ($data['is_default'] ?? false) {
                $request->user()->addresses()->update(['is_default' => false]);
            }

            return $request->user()->addresses()->create($data);
        });

        return response()->json(['data' => $address], 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $data = $request->validate($this->rules(required: false));

        DB::transaction(function () use ($request, $address, $data): void {
            if ($data['is_default'] ?? false) {
                $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->update($data);
        });

        return response()->json(['data' => $address->fresh()]);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        $wasDefault = $address->is_default;
        $address->delete();

        // Keep exactly one default: promote the most recent remaining address.
        if ($wasDefault) {
            $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
        }

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $required = true): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:40'],
            'name' => [$req, 'string', 'max:120'],
            'line1' => [$req, 'string', 'max:255'],
            'line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:60'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:12'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
