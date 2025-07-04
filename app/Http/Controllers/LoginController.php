<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class LoginController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            
            // Simpan data user ke Redis dengan expire time 1 jam
            Redis::setex('user:' . $user->id, 3600, json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]));

            return response()->json([
                'message' => 'Login berhasil',
                'user' => $user
            ]);
        }

        return response()->json([
            'message' => 'Email atau password salah'
        ], 401);
    }

    public function getAdminData($id)
    {
        // Coba ambil data dari Redis dulu
        $cachedUser = Redis::get('user:' . $id);
        
        if ($cachedUser) {
            return response()->json([
                'message' => 'Data dari cache',
                'user' => json_decode($cachedUser)
            ]);
        }

        // Jika tidak ada di cache, ambil dari database
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Simpan ke Redis untuk next request
        Redis::setex('user:' . $user->id, 3600, json_encode([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]));

        return response()->json([
            'message' => 'Data dari database',
            'user' => $user
        ]);
    }
}
