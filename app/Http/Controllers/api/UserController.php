<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        $avatarPath = $user->avatar ? '/storage/avatars/' . $user->avatar : null;

        return response()->json([
            'status' => true,
            'message' => 'Profile berhasil diambil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                    'balance' => number_format($user->balance, 2, '.', ''),
                    'address' => $user->address,
                    'avatar' => $user->avatar,
                    'avatar_path' => $avatarPath,
                    'created_at' => $user->created_at
                ]
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        // Validasi data
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,'.$user->id,
            'phone_number' => 'sometimes|required|string|max:20|unique:users,phone_number,'.$user->id,
            'address' => 'sometimes|nullable|string',
            'avatar' => 'sometimes|nullable|string'
        ]);
        
        // Handle avatar jika ada base64 string
        if ($request->has('avatar')) {
            // Hapus avatar lama jika ada
            if ($user->avatar) {
                Storage::delete('public/avatars/' . $user->avatar);
            }
            
            // Decode base64 dan simpan sebagai file
            $imageData = base64_decode($request->avatar);
            $fileName = Str::slug($user->name) . '-' . Str::random(4) . '.jpg';
            
            // Simpan avatar baru
            Storage::disk('public')->makeDirectory('avatars');
            Storage::disk('public')->put('avatars/' . $fileName, $imageData);
            
            $validated['avatar'] = $fileName;
        }
        
        // Update user
        $user->update($validated);
        $user = $user->fresh();
        
        // Tambah avatar_path untuk response
        $user->avatar_path = $user->avatar ? '/storage/avatars/' . $user->avatar : null;
        
        return response()->json([
            'status' => true,
            'message' => 'Profile berhasil diupdate',
            'data' => [
                'user' => $user
            ]
        ]);
    }

    public function show($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $avatarPath = $user->avatar ? '/storage/avatars/' . $user->avatar : null;

        return response()->json([
            'status' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                    'balance' => number_format($user->balance, 2, '.', ''),
                    'address' => $user->address,
                    'avatar' => $user->avatar,
                    'avatar_path' => $avatarPath,
                    'created_at' => $user->created_at
                ]
            ]
        ]);
    }
} 