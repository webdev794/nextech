<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CategoryInterests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A signed-in shopper looked at a product in this category (for their "Recommended"). */
class CategoryInterestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['category_id' => ['required', 'integer', 'exists:categories,id']]);
        CategoryInterests::viewed($request->user(), (int) $data['category_id']);

        return response()->json(status: 204);
    }
}
