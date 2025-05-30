<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WasteCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class TransactionController extends Controller
{
    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';
    const TOKEN_EXPIRY_HOURS = 24;
    const AUTO_VERIFY_WEIGHT_LIMIT = 20;

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickupLocation' => 'required|string',
            'categoryId' => 'required|string',
            'estimatedWeight' => 'required|string',
            'photo' => 'nullable|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $categoryIds = array_map('trim', explode(',', $request->categoryId));
            $weights = array_map('trim', explode(',', $request->estimatedWeight));

            if (count($categoryIds) !== count($weights)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Jumlah kategori dan berat harus sama'
                ], 422);
            }

            $totalWeight = 0;
            $totalPrice = 0;
            $items = [];

            for ($i = 0; $i < count($categoryIds); $i++) {
                $category = WasteCategory::find($categoryIds[$i]);
                
                if (!$category) {
                    return response()->json([
                        'status' => false,
                        'message' => "Kategori dengan ID {$categoryIds[$i]} tidak ditemukan"
                    ], 422);
                }

                $weight = floatval($weights[$i]);
                if ($weight <= 0) {
                    return response()->json([
                        'status' => false,
                        'message' => "Berat harus lebih dari 0"
                    ], 422);
                }

                $totalWeight += $weight;
                $totalPrice += $weight * $category->price_per_kg;
                
                $items[] = [
                    'category' => $category,
                    'weight' => $weight
                ];
            }
            
            $verificationToken = Str::random(32);
            $tokenExpiresAt = now()->addHours(self::TOKEN_EXPIRY_HOURS);
            $qrPath = 'qrcodes/tx-' . time() . '-' . uniqid() . '.png';

            $transaction = Transaction::create([
                'user_id' => Auth::id(),
                'pickup_location' => $request->pickupLocation,
                'total_weight' => $totalWeight,
                'total_price' => $totalPrice,
                'status' => self::STATUS_PENDING,
                'qr_code_path' => $qrPath,
                'verification_token' => $verificationToken,
                'token_expires_at' => $tokenExpiresAt
            ]);

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $filename = time() . '-' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $photoPath = $photo->storeAs('waste-photos', $filename, 'public');
            }

            foreach ($items as $item) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'category_id' => $item['category']->id,
                    'estimated_weight' => $item['weight'],
                    'photo_path' => $photoPath
                ]);
            }

            $qrData = [
                'transaction_id' => $transaction->id,
                'token' => $verificationToken,
                'expires_at' => $tokenExpiresAt->toIso8601String(),
                'verify_url' => url("/api/transactions/verify/{$transaction->id}")
            ];

            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel' => QRCode::ECC_H,
                'scale' => 5,
                'imageBase64' => false,
            ]);

            $qrcode = new QRCode($options);
            
            Storage::disk('public')->put(
                $qrPath, 
                $qrcode->render(json_encode($qrData))
            );

            DB::commit();

            $transaction->load('details.category');

            return response()->json([
                'status' => true,
                'message' => 'Transaksi berhasil dibuat',
                'data' => [
                    'transaction' => $transaction,
                    'qrCodePath' => Storage::url($qrPath),
                    'photoPath' => $photoPath ? Storage::url($photoPath) : null,
                    'tokenExpiresAt' => $tokenExpiresAt
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat membuat transaksi'
            ], 500);
        }
    }

    public function show($id)
    {
        $transaction = Transaction::with(['details.category'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $transaction->details->transform(function ($detail) {
            if ($detail->photo_path) {
                $detail->photo_path = Storage::url($detail->photo_path);
            }
            return $detail;
        });

        return response()->json([
            'status' => true,
            'data' => $transaction
        ]);
    }

    public function verify(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Token verifikasi tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction = Transaction::with(['details.category', 'user'])
            ->findOrFail($id);

        if ($transaction->isTokenExpired()) {
            return response()->json([
                'status' => false,
                'message' => 'Token verifikasi sudah kadaluarsa'
            ], 403);
        }

        if ($transaction->verification_token !== $request->token) {
            return response()->json([
                'status' => false,
                'message' => 'Token verifikasi tidak valid'
            ], 403);
        }

        if ($transaction->status !== self::STATUS_PENDING) {
            return response()->json([
                'status' => false,
                'message' => 'Transaksi sudah diverifikasi atau ditolak'
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Data transaksi valid',
            'data' => [
                'transaction' => $transaction,
                'user' => [
                    'name' => $transaction->user->name,
                    'phone_number' => $transaction->user->phone_number
                ]
            ]
        ]);
    }

    public function submitVerification(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'actualWeight' => 'required|numeric|min:0.1',
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $transaction = Transaction::with(['details.category', 'user'])->findOrFail($id);
            
            if ($transaction->isTokenExpired()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token verifikasi sudah kadaluarsa'
                ], 403);
            }

            if ($transaction->verification_token !== $request->token) {
                return response()->json([
                    'status' => false,
                    'message' => 'Token verifikasi tidak valid'
                ], 403);
            }

            if ($transaction->status !== self::STATUS_PENDING) {
                return response()->json([
                    'status' => false,
                    'message' => 'Transaksi sudah diverifikasi atau ditolak'
                ], 400);
            }

            $detail = $transaction->details->first();
            $category = $detail->category;
            $actualWeight = $request->actualWeight;
            $totalPrice = $actualWeight * $category->price_per_kg;

            $detail->update([
                'actual_weight' => $actualWeight
            ]);

            $transaction->update([
                'total_weight' => $actualWeight,
                'total_price' => $totalPrice,
                'verification_token' => null,
                'token_expires_at' => null,
                'status' => $actualWeight < self::AUTO_VERIFY_WEIGHT_LIMIT ? self::STATUS_VERIFIED : self::STATUS_PENDING
            ]);

            if ($actualWeight < self::AUTO_VERIFY_WEIGHT_LIMIT) {
                $user = $transaction->user;
                $user->update([
                    'balance' => $user->balance + $totalPrice
                ]);
            }

            DB::commit();

            $weightDiff = abs($detail->estimated_weight - $actualWeight);
            $message = $actualWeight < self::AUTO_VERIFY_WEIGHT_LIMIT 
                ? 'Verifikasi berhasil dan saldo telah ditambahkan' 
                : 'Verifikasi berhasil, menunggu persetujuan admin karena berat melebihi 20kg';

            if ($weightDiff > 0) {
                $message .= sprintf(
                    '. Terdapat perbedaan berat: estimasi %.2f kg, aktual %.2f kg',
                    $detail->estimated_weight,
                    $actualWeight
                );
            }

            $transaction->load(['details.category', 'user']);

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'transaction' => $transaction,
                    'transaction_detail' => $detail->fresh(),
                    'weight_difference' => $weightDiff,
                    'needs_admin_approval' => $actualWeight >= self::AUTO_VERIFY_WEIGHT_LIMIT,
                    'price_difference' => $totalPrice - ($detail->estimated_weight * $category->price_per_kg),
                    'verifier' => [
                        'name' => $request->user()->name,
                        'role' => $request->user()->role
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat memproses verifikasi'
            ], 500);
        }
    }

    public function adminAction(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $transaction = Transaction::with(['details.category', 'user'])->findOrFail($id);

            if ($transaction->status !== self::STATUS_PENDING) {
                return response()->json([
                    'status' => false,
                    'message' => 'Transaksi tidak dalam status pending'
                ], 400);
            }

            $detail = $transaction->details->first();
            $actualWeight = $detail->actual_weight;

            if ($actualWeight < self::AUTO_VERIFY_WEIGHT_LIMIT) {
                return response()->json([
                    'status' => false,
                    'message' => 'Transaksi ini tidak memerlukan persetujuan admin'
                ], 400);
            }

            if ($request->action === 'approve') {
                $transaction->update(['status' => self::STATUS_VERIFIED]);
                
                $user = $transaction->user;
                $user->update([
                    'balance' => $user->balance + $transaction->total_price
                ]);

                $message = 'Transaksi berhasil disetujui dan saldo telah ditambahkan';
            } else {
                $transaction->update([
                    'status' => self::STATUS_REJECTED,
                    'rejection_reason' => $request->reason
                ]);

                $message = 'Transaksi ditolak: ' . $request->reason;
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'transaction' => $transaction->fresh(['details.category', 'user']),
                    'admin' => [
                        'name' => $request->user()->name,
                        'role' => $request->user()->role
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat memproses persetujuan'
            ], 500);
        }
    }
}