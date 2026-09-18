<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteFeedbackController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
        ]);

        SiteFeedback::create([
            'user_id' => $request->user('sanctum')?->id,
            'rating' => $validated['rating'],
        ]);

        return response()->json(status: 201);
    }
}
