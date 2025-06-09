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
            
            // Check if user already has valid OTP
            if ($user->otp_code && $user->otp_expires_at && Carbon::parse($user->otp_expires_at)->gt(Carbon::now())) {
                return response()->json([
                    'message' => 'Anda sudah memiliki OTP yang masih berlaku',
                    'expires_at' => $user->otp_expires_at
                ]);
            }

            $cacheKey = 'otp_request_' . $user->id;
            $today = Carbon::now()->startOfDay();
            
            // Initialize or get cache data
            $cacheData = Cache::get($cacheKey, [
                'last_request_date' => null,
                'request_count' => 0,
                'resend_count' => 0,
                'last_resend' => null,
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);

            // Reset counter if it's a new day
            if (!$cacheData['last_request_date'] || Carbon::parse($cacheData['last_request_date'])->startOfDay()->lt($today)) {
                $cacheData['request_count'] = 0;
            }
            
            // Check if user has exceeded daily limit (3 requests)
            if ($cacheData['request_count'] >= 3) {
                $nextAvailable = $today->copy()->addDay();
                $waitHours = Carbon::now()->diffInHours($nextAvailable);
                return response()->json([
                    'message' => 'Anda telah mencapai batas maksimal permintaan OTP hari ini (3 kali). Silakan coba lagi besok.',
                    'next_available' => $nextAvailable,
                    'wait_hours' => $waitHours
                ], 429);
            }
            
            // Generate 6 digit OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Save OTP to database
            $user->otp_code = $otp;
            $user->otp_expires_at = now()->addMinutes(10); // OTP valid for 10 minutes
            $user->save();

            // Increment request count and update cache
            $cacheData['request_count']++;
            $cacheData['last_request_date'] = Carbon::now()->toDateTimeString();
            $cacheData['resend_count'] = 0;
            $cacheData['last_resend'] = null;
            
            Cache::put($cacheKey, $cacheData, $today->copy()->addDays(2)); // Keep for 2 days to handle timezone edge cases

            // Send OTP via email
            Mail::to($user->email)->send(new OTPMail($user, $otp));
            
            return response()->json([
                'message' => 'OTP telah dikirim ke email Anda',
                'expires_at' => $user->otp_expires_at,
                'remaining_requests_today' => 3 - $cacheData['request_count'],
                'next_available_request' => $today->copy()->addDay()
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
            $cacheData['last_resend'] = Carbon::now()->toDateTimeString();
            Cache::put($cacheKey, $cacheData, Carbon::now()->addDay());

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