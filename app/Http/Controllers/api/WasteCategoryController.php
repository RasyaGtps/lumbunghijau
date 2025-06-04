<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WasteCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:organic,inorganic',
            'price_per_kg' => 'required|numeric|min:0',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $image = $request->file('image');
            $fileName = time() . '_' . $image->getClientOriginalName();
            $path = $image->storeAs('waste-categories', $fileName, 'public');

            $category = WasteCategory::create([
                'name' => $request->name,
                'type' => $request->type,
                'price_per_kg' => $request->price_per_kg,
                'image_path' => $path
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Kategori sampah berhasil dibuat',
                'data' => [
                    'category' => $category,
                    'image_url' => Storage::url($path)
                ]
            ], 201);

        } catch (\Exception $e) {
            // Hapus file yang sudah terupload jika ada error
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat membuat kategori sampah'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:organic,inorganic',
            'price_per_kg' => 'sometimes|required|numeric|min:0',
            'image' => 'sometimes|required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category = WasteCategory::findOrFail($id);
            $updateData = [];

            // Update text fields
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('type')) {
                $updateData['type'] = $request->type;
            }
            if ($request->has('price_per_kg')) {
                $updateData['price_per_kg'] = $request->price_per_kg;
            }

            // Handle image update
            if ($request->hasFile('image')) {
                // Delete old image
                if ($category->image_path) {
                    Storage::disk('public')->delete($category->image_path);
                }

                $image = $request->file('image');
                $fileName = time() . '_' . $image->getClientOriginalName();
                $path = $image->storeAs('waste-categories', $fileName, 'public');
                $updateData['image_path'] = $path;
            }

            $category->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'Kategori sampah berhasil diupdate',
                'data' => [
                    'category' => $category,
                    'image_url' => $category->image_path ? Storage::url($category->image_path) : null
                ]
            ]);

        } catch (\Exception $e) {
            // Hapus file yang sudah terupload jika ada error
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mengupdate kategori sampah'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = WasteCategory::findOrFail($id);

            // Delete image if exists
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }

            $category->delete();

            return response()->json([
                'status' => true,
                'message' => 'Kategori sampah berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat menghapus kategori sampah'
            ], 500);
        }
    }
}
