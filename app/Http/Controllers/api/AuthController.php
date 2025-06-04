<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone_number' => 'required|string|unique:users'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'role' => 'user',
            'balance' => 0
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                    'balance' => $user->balance,
                    'avatar' => $user->avatar ? Storage::url($user->avatar) : null
                ],
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah login menggunakan email atau phone_number
        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone_number';
        $credentials = [
            $loginType => $request->login,
            'password' => $request->password
        ];

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Email/No Telepon atau password salah'
            ], 401);
        }

        $user = User::where($loginType, $request->login)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        $avatarPath = $user->avatar ? '/storage/avatars/' . $user->avatar : null;

        return response()->json([
            'status' => true,
            'message' => 'Login berhasil',
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
                ],
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();
        
        return response()->json([
            'status' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    public function me()
    {
        $user = Auth::user();

        return response()->json([
            'status' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                    'balance' => $user->balance,
                    'avatar' => $user->avatar ? Storage::url($user->avatar) : null
                ]
            ]
        ]);
    }
} 