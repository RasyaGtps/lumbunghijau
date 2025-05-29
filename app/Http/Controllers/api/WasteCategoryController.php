<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WasteCategory;
use Illuminate\Http\Request;

class WasteCategoryController extends Controller
{
    public function index()
    {
        $categories = WasteCategory::all();

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil data kategori sampah',
            'data' => $categories
        ]);
    }
}
