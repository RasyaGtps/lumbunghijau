<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalController extends Controller
{
    public function index()
    {
        $withdrawals = Withdrawal::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $withdrawals
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $user = User::find(Auth::id());

        // Cek apakah user sudah membuat request withdrawal hari ini
        $todayRequest = Withdrawal::where('user_id', Auth::id())
            ->whereDate('created_at', today())
            ->where('status', 'pending')
            ->first();

        if ($todayRequest) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah membuat permintaan penarikan hari ini. Silakan tunggu konfirmasi admin atau coba lagi besok.',
                'data' => [
                    'next_request_available' => today()->addDay()->format('Y-m-d H:i:s')
                ]
            ], 400);
        }

        if ($user->balance < $request->amount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Saldo tidak mencukupi. Saldo Anda: Rp ' . number_format($user->balance, 0, ',', '.'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Set expiration time to 24 hours from now
            $withdrawal = Withdrawal::create([
                'user_id' => Auth::id(),
                'amount' => $request->amount,
                'status' => 'pending',
                'expires_at' => now()->addHours(24)
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Permintaan penarikan berhasil dibuat',
                'data' => [
                    'withdrawal' => $withdrawal,
                    'saldo_tersisa' => $user->balance,
                    'expires_at' => $withdrawal->expires_at
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses penarikan'
            ], 500);
        }
    }

    public function show(Withdrawal $withdrawal)
    {
        if ($withdrawal->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $withdrawal
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected'
        ]);

        try {
            DB::beginTransaction();

            $withdrawal = Withdrawal::findOrFail($id);
            
            // Log untuk debugging
            Log::info('Updating withdrawal status', [
                'withdrawal_id' => $id,
                'old_status' => $withdrawal->status,
                'new_status' => $request->status
            ]);

            // Cek apakah withdrawal sudah expired
            if ($withdrawal->expires_at && now()->gt($withdrawal->expires_at)) {
                $withdrawal->status = 'expired';
                $withdrawal->save();

                DB::commit();
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permintaan penarikan ini sudah kadaluarsa'
                ], 400);
            }

            // Cek status saat ini
            if ($withdrawal->status !== 'pending') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permintaan penarikan ini sudah diproses sebelumnya'
                ], 400);
            }

            // Jika status diubah menjadi accepted
            if ($request->status === 'accepted') {
                // Kurangi saldo user
                $user = User::find($withdrawal->user_id);
                
                if ($user->balance < $withdrawal->amount) {
                    DB::rollback();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Saldo user tidak mencukupi untuk penarikan ini'
                    ], 400);
                }

                $user->balance -= $withdrawal->amount;
                $user->save();

                Log::info('Deducting balance from user', [
                    'user_id' => $user->id,
                    'amount' => $withdrawal->amount,
                    'new_balance' => $user->balance
                ]);
            }

            $withdrawal->status = $request->status;
            $withdrawal->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Status penarikan berhasil diperbarui',
                'data' => $withdrawal
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating withdrawal status', [
                'error' => $e->getMessage(),
                'withdrawal_id' => $id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui status'
            ], 500);
        }
    }

    // Tambah method untuk mengecek dan mengupdate status expired withdrawals
    public function checkExpiredWithdrawals()
    {
        try {
            $expiredWithdrawals = Withdrawal::where('status', 'pending')
                ->where('expires_at', '<', now())
                ->get();

            foreach ($expiredWithdrawals as $withdrawal) {
                $withdrawal->status = 'expired';
                $withdrawal->save();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil mengecek withdrawal yang kadaluarsa',
                'data' => [
                    'expired_count' => $expiredWithdrawals->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengecek withdrawal yang kadaluarsa'
            ], 500);
        }
    }
} 