<?php

namespace App\Http\Controllers\Api\Cleaning;

use App\Http\Controllers\Controller;
use App\Models\InspectionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionItemController extends Controller
{
    public function index(): JsonResponse
    {
        $items = InspectionItem::query()
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        // Uniqueness is handled here (including soft-deleted rows) so that
        // re-adding a previously removed item RESTORES it (its old cells return)
        // instead of failing the DB unique constraint.
        $existing = InspectionItem::withTrashed()->where('name', $data['name'])->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $existing->update(['active' => true]);
                return response()->json(['data' => $existing, 'restored' => true]);
            }
            return response()->json([
                'message' => 'An item with this name already exists.',
                'errors'  => ['name' => ['An item with this name already exists.']],
            ], 422);
        }

        $item = InspectionItem::create([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? (int) (InspectionItem::max('sort_order') + 1),
            'active'     => true,
        ]);

        return response()->json(['data' => $item], 201);
    }

    /**
     * Soft delete: the column disappears from the grid, but its past
     * evaluation cells stay in the DB (not cascade-deleted).
     */
    public function destroy(InspectionItem $inspection_item): JsonResponse
    {
        $inspection_item->delete();

        return response()->json(['deleted' => true]);
    }
}
