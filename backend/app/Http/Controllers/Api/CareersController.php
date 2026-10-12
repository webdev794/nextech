<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Careers page (public) and the admin Careers menu. */
class CareersController extends Controller
{
    /** Open roles for the Careers page. */
    public function roles(): JsonResponse
    {
        $roles = JobRole::query()->where('is_open', true)->orderByRaw("kind = 'rider' desc")->orderBy('title')->get();
        $stores = \App\Models\Store::query()->whereIn('id', $roles->pluck('store_ids')->flatten()->filter()->unique())->with('shop:id,name')->get()->keyBy('id');

        return response()->json(['data' => $roles->map(fn ($r) => [
            'id' => $r->id, 'title' => $r->title, 'kind' => $r->kind, 'description' => $r->description, 'requirements' => $r->requirements,
            // Where: the text, plus the stores it's for.
            'location' => trim(implode(' · ', array_filter([$r->location, collect($r->store_ids ?? [])->map(fn ($id) => $stores[$id] ?? null)->filter()->map(fn ($s) => ($s->shop?->name ?? $s->name).($s->city ? " ({$s->city})" : ''))->join(', ')]))) ?: null,
        ])]);
    }

    /** Apply for a (non-rider) role: a short form; admin is told in the dashboard. */
    public function apply(Request $request, JobRole $role): JsonResponse
    {
        abort_unless($role->is_open && $role->kind !== 'rider', 422, 'This role isn’t open for applications here.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'message' => ['nullable', 'string', 'max:3000'],
        ]);
        $role->applications()->create($data + ['user_id' => $request->user('sanctum')?->id]);
        // A job for a seller's store: that seller is told (and sees it in Local delivery & riders).
        foreach (\App\Models\Store::query()->whereIn('id', $role->store_ids ?? [])->whereNotNull('shop_id')->with('shop.seller')->get() as $store) {
            if ($store->shop?->seller) {
                \App\Support\SellerNotify::send($store->shop->seller, $store->shop->seller->user, 'New job application', "{$data['name']} applied for “{$role->title}” at your store. See Local delivery & riders → Job applications.", true);
            }
        }

        return response()->json(['message' => 'Thanks — your application is in. We’ll email you if we’d like to talk.'], 201);
    }

    // ---------------------------------------------------------------- admin

    public function adminIndex(): JsonResponse
    {
        return response()->json(['data' => [
            'roles' => JobRole::query()->withCount('applications')->orderByDesc('is_open')->orderBy('title')->get(),
            'applications' => JobApplication::query()->with('role:id,title')->latest()->limit(200)->get(),
        ]]);
    }

    public function save(Request $request, ?JobRole $role = null): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['general', 'rider'])],
            'location' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'is_open' => ['required', 'boolean'],
            'store_ids' => ['sometimes', 'nullable', 'array'],
            'store_ids.*' => ['integer', 'exists:stores,id'],
        ]);
        $role ? $role->update($data) : JobRole::create($data);

        return $this->adminIndex();
    }

    public function update(Request $request, JobRole $role): JsonResponse
    {
        return $this->save($request, $role);
    }

    public function destroy(JobRole $role): JsonResponse
    {
        $role->delete();

        return $this->adminIndex();
    }

    public function setStatus(Request $request, JobApplication $application): JsonResponse
    {
        $application->update($request->validate(['status' => ['required', Rule::in(['new', 'reviewed', 'hired', 'declined'])]]));

        return $this->adminIndex();
    }
}
