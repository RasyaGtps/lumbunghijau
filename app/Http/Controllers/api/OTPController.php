<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Mail\OTPMail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class OTPController extends Controller
{
    /**
     * Generate and send OTP to user
     */
    public function sendOTP(Request $request)
    {
        try {
            $user = Auth::user();
            $cacheKey = 'otp_request_' . $user->id;
            
            // Check if user has requested OTP in the last 24 hours
            if (Cache::has($cacheKey)) {
                $lastRequest = Cache::get($cacheKey);
                $nextAvailable = Carbon::parse($lastRequest)->addDay();
                
                if (Carbon::now()->lt($nextAvailable)) {
                    $waitTime = Carbon::now()->diffInHours($nextAvailable);
                    return response()->json([
                        'message' => 'Anda harus menunggu ' . $waitTime . ' jam lagi sebelum meminta OTP baru',
                        'next_available' => $nextAvailable
                    ], 429);
                }
            }
            
            // Generate 6 digit OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Save OTP to database
            $user->otp_code = $otp;
            $user->otp_expires_at = now()->addMinutes(10); // OTP valid for 10 minutes
            $user->save();

            // Store request timestamp and initialize resend count in cache
            $cacheData = [
                'timestamp' => Carbon::now(),
                'resend_count' => 0,
                'last_resend' => null
            ];
            Cache::put($cacheKey, $cacheData, Carbon::now()->addDay());

            // Send OTP via email
            Mail::to($user->email)->send(new OTPMail($user, $otp));
            
            return response()->json([
                'message' => 'OTP telah dikirim ke email Anda',
                'expires_at' => $user->otp_expires_at,
                'next_available_request' => Carbon::now()->addDay(),
                'remaining_resend' => 3
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengirim OTP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyOTP(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6'
        ]);

        $user = Auth::user();

        if (!$user->otp_code || !$user->otp_expires_at) {
            return response()->json([
                'message' => 'Silakan minta OTP terlebih dahulu'
            ], 400);
        }

        if ($user->otp_expires_at < now()) {
            return response()->json([
                'message' => 'OTP sudah kadaluarsa'
            ], 400);
        }

        if ($user->otp_code !== $request->otp) {
            return response()->json([
                'message' => 'Kode OTP tidak valid'
            ], 400);
        }

        // OTP is valid, mark email as verified
        $user->email_verified = true;
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        // Clear the cache after successful verification
        Cache::forget('otp_request_' . $user->id);

        return response()->json([
            'message' => 'Email berhasil diverifikasi',
            'user' => $user
        ]);
    }

    /**
     * Resend OTP if expired
     */
    public function resendOTP(Request $request)
    {
        try {
            $user = Auth::user();
            $cacheKey = 'otp_request_' . $user->id;

            if ($user->email_verified) {
                return response()->json([
                    'message' => 'Email sudah terverifikasi'
                ], 400);
            }

            // Check if initial OTP request exists
            if (!Cache::has($cacheKey)) {
                return response()->json([
                    'message' => 'Silakan lakukan request OTP pertama kali terlebih dahulu'
                ], 400);
            }

            $cacheData = Cache::get($cacheKey);
            
            // Check resend count
            if ($cacheData['resend_count'] >= 3) {
                return response()->json([
                    'message' => 'Anda telah mencapai batas maksimal pengiriman ulang OTP (3 kali)',
                    'next_available' => Carbon::parse($cacheData['timestamp'])->addDay()
                ], 429);
            }

            // Check 30 seconds cooldown
            if ($cacheData['last_resend'] && Carbon::now()->lt(Carbon::parse($cacheData['last_resend'])->addSeconds(30))) {
                $waitTime = Carbon::now()->diffInSeconds(Carbon::parse($cacheData['last_resend'])->addSeconds(30));
                return response()->json([
                    'message' => 'Mohon tunggu ' . $waitTime . ' detik sebelum meminta OTP baru',
                    'retry_after' => $waitTime
                ], 429);
            }

            // Generate new OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            $user->otp_code = $otp;
            $user->otp_expires_at = now()->addMinutes(10);
            $user->save();

            // Update cache data
            $cacheData['resend_count']++;
            $cacheData['last_resend'] = Carbon::now();
            Cache::put($cacheKey, $cacheData, Carbon::parse($cacheData['timestamp'])->addDay());

            // Send new OTP via email
            Mail::to($user->email)->send(new OTPMail($user, $otp));
            
            return response()->json([
                'message' => 'OTP baru telah dikirim ke email Anda',
                'expires_at' => $user->otp_expires_at,
                'remaining_resend' => 3 - $cacheData['resend_count'],
                'next_resend_available' => Carbon::now()->addSeconds(30)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengirim OTP: ' . $e->getMessage()
            ], 500);
        }
    }
} 