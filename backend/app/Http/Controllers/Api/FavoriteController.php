<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "My favourites": a buyer saves products to come back to. The list itself
 * (priced for the store being browsed) is CatalogController::favorites.
 */
class FavoriteController extends Controller
{
    /** Ids of the buyer's saved products, for the hearts on product pages. */
    public function ids(Request $request): JsonResponse
    {
        return response()->json(['data' => Favorite::where('user_id', $request->user()->id)->pluck('product_id')]);
    }

    /** Save or un-save a product. */
    public function toggle(Request $request, Product $product): JsonResponse
    {
        $existing = Favorite::where('user_id', $request->user()->id)->where('product_id', $product->id)->first();
        if ($existing) {
            $existing->delete();
        } else {
            abort_if(Favorite::where('user_id', $request->user()->id)->count() >= 500, 422, 'You can save up to 500 favourites.');
            Favorite::create(['user_id' => $request->user()->id, 'product_id' => $product->id]);
        }

        return response()->json(['data' => ['saved' => ! $existing]]);
    }
}
