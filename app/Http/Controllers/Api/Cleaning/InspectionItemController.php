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
            'name'       => ['required', 'string', 'max:255', 'unique:inspection_items,name'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $item = InspectionItem::create([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? (int) (InspectionItem::max('sort_order') + 1),
            'active'     => true,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function destroy(InspectionItem $inspection_item): JsonResponse
    {
        $inspection_item->delete();   // cascades its evaluation_item_values

        return response()->json(['deleted' => true]);
    }
}
