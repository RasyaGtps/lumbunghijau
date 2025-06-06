<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function getCart()
    {
        $user = Auth::user();
        $cart = Transaction::with(['details.category'])
            ->where('user_id', $user->id)
            ->where('status', 'cart')
            ->first();

        if (!$cart) {
            $cart = Transaction::create([
                'user_id' => $user->id,
                'status' => 'cart',
                'total_weight' => '0.00',
                'total_price' => '0.00',
                'pickup_location' => null,
                'photo_path' => null,
                'verification_token' => null,
                'token_expires_at' => null,
                'rejection_reason' => null
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $cart
        ]);
    }

    public function addToCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'categoryId' => 'required|exists:waste_categories,id',
                'estimatedWeight' => 'required|numeric|min:1'
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $user = Auth::user();
            
            // Ambil atau buat cart
            $cart = Transaction::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'status' => 'cart'
                ],
                [
                    'total_weight' => 0,
                    'total_price' => 0
                ]
            );

            // Cek apakah item dengan kategori yang sama sudah ada di cart
            $existingDetail = TransactionDetail::where('transaction_id', $cart->id)
                ->where('category_id', $request->categoryId)
                ->first();

            if ($existingDetail) {
                // Jika item sudah ada, update beratnya
                $existingDetail->estimated_weight = $existingDetail->estimated_weight + (int)$request->estimatedWeight;
                $existingDetail->save();
            } else {
                // Jika item belum ada, buat baru
                $detail = new TransactionDetail();
                $detail->transaction_id = $cart->id;
                $detail->category_id = $request->categoryId;
                $detail->estimated_weight = (int)$request->estimatedWeight;
                $detail->save();
            }

            // Update total dan ambil data terbaru
            $this->updateCartTotal($cart->id);
            $cart->refresh();
            $cart->load('details.category');

            return response()->json([
                'status' => true,
                'data' => $cart
            ]);

        } catch (\Exception $e) {
            \Log::error('Error di addToCart:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->all()
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat menambahkan ke keranjang: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateCartItem(Request $request)
    {
        $data = [
            'detailId' => $request->detailId,
            'estimatedWeight' => $request->estimatedWeight
        ];

        $validator = Validator::make($data, [
            'detailId' => 'required|exists:transaction_details,id',
            'estimatedWeight' => 'required|numeric|min:0.1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            // Cek apakah item ada dan milik user ini
            $detail = TransactionDetail::whereHas('transaction', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('status', 'cart');
            })->find($data['detailId']);

            if (!$detail) {
                return response()->json([
                    'status' => false,
                    'message' => 'Item tidak ditemukan'
                ], 404);
            }

            // Update berat
            $detail->estimated_weight = number_format($data['estimatedWeight'], 2);
            $detail->save();

            // Update total cart
            $this->updateCartTotal($detail->transaction_id);

            // Load cart dengan relasinya
            $cart = Transaction::with(['details.category'])
                ->find($detail->transaction_id);

            return response()->json([
                'status' => true,
                'data' => $cart
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in updateCartItem: ' . $e->getMessage());
            \Log::error('Request data: ' . json_encode($request->all()));
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mengupdate item'
            ], 500);
        }
    }

    public function removeFromCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'detailId' => 'required|exists:transaction_details,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            // Cek apakah item ada dan milik user ini
            $detail = TransactionDetail::whereHas('transaction', function($q) use ($user) {
                $q->where('user_id', $user->id)->where('status', 'cart');
            })->find($request->detailId);

            if (!$detail) {
                return response()->json([
                    'status' => false,
                    'message' => 'Item tidak ditemukan'
                ], 404);
            }

            $cartId = $detail->transaction_id;

            // Hapus item
            $detail->delete();

            // Update total cart
            $this->updateCartTotal($cartId);

            // Load cart dengan relasinya
            $cart = Transaction::with('details.category')->find($cartId);

            // Jika cart kosong, kembalikan null
            if ($cart->details->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => null
                ]);
            }

            return response()->json([
                'status' => true,
                'data' => $cart
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in removeFromCart: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat menghapus item'
            ], 500);
        }
    }

    public function updateWeight(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'detailId' => 'required|exists:transaction_items,id',
            'estimated_weight' => 'required|numeric|min:0.1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();
            $item = TransactionItem::with('transaction')->find($request->detailId);

            if (!$item || $item->transaction->user_id !== $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Item tidak ditemukan'
                ], 404);
            }

            $item->estimated_weight = $request->estimated_weight;
            $item->save();

            // Update total
            $this->updateCartTotal($item->transaction_id);

            DB::commit();

            return response()->json([
                'status' => true,
                'data' => Transaction::with(['details.category'])->find($item->transaction_id)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mengupdate berat'
            ], 500);
        }
    }

    public function submit(Request $request)
    {
        \Log::info('Submit request received', [
            'has_file' => $request->hasFile('photo'),
            'content_type' => $request->header('Content-Type'),
            'all_data' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'pickupLocation' => 'required|string',
            'photo' => 'required'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $user = Auth::user();
            $cart = Transaction::with('details')
                ->where('user_id', $user->id)
                ->where('status', 'cart')
                ->first();

            if (!$cart || $cart->details->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Keranjang kosong'
                ], 404);
            }

            // Handle both base64 string and file upload
            $filename = 'waste-photos/' . Str::random(40) . '.jpg';
            
            if ($request->hasFile('photo')) {
                \Log::info('Processing uploaded file', [
                    'original_name' => $request->file('photo')->getClientOriginalName(),
                    'mime_type' => $request->file('photo')->getMimeType(),
                    'size' => $request->file('photo')->getSize()
                ]);

                // Handle file upload
                $file = $request->file('photo');
                if (!$file->isValid()) {
                    \Log::error('Invalid file upload', [
                        'error' => $file->getError(),
                        'error_message' => $file->getErrorMessage()
                    ]);
                    return response()->json([
                        'status' => false,
                        'message' => 'File tidak valid: ' . $file->getErrorMessage()
                    ], 422);
                }

                if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'File harus berupa gambar (JPG, JPEG, PNG)'
                    ], 422);
                }
                
                try {
                    $file->storeAs('public', $filename);
                    \Log::info('File successfully stored at: ' . $filename);
                } catch (\Exception $e) {
                    \Log::error('Error storing file: ' . $e->getMessage());
                    throw $e;
                }
            } else if (is_string($request->photo)) {
                // Handle base64 string
                try {
                    $imageData = base64_decode($request->photo, true);
                    if ($imageData === false) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Invalid base64 string'
                        ], 422);
                    }
                    Storage::disk('public')->put($filename, $imageData);
                    \Log::info('Base64 image successfully stored at: ' . $filename);
                } catch (\Exception $e) {
                    \Log::error('Error storing base64 image: ' . $e->getMessage());
                    throw $e;
                }
            } else {
                \Log::error('Invalid photo format', [
                    'photo_type' => gettype($request->photo)
                ]);
                return response()->json([
                    'status' => false,
                    'message' => 'Format foto tidak valid'
                ], 422);
            }

            // Update cart
            try {
                $cart->status = 'pending';
                $cart->pickup_location = $request->pickupLocation;
                $cart->image_path = $filename;
                
                // Generate token verifikasi
                $verificationToken = Str::random(32);
                $tokenExpiresAt = now()->addHours(24); // 24 jam expiry time
                
                $cart->verification_token = $verificationToken;
                $cart->token_expires_at = $tokenExpiresAt;
                
                $cart->save();

                \Log::info('Cart updated successfully', [
                    'cart_id' => $cart->id,
                    'status' => $cart->status,
                    'image_path' => $cart->image_path
                ]);
            } catch (\Exception $e) {
                \Log::error('Error updating cart: ' . $e->getMessage());
                throw $e;
            }

            return response()->json([
                'status' => true,
                'message' => 'Transaksi berhasil disubmit',
                'data' => $cart
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in submit: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::error('Request data:', $request->all());
            
            if (isset($filename)) {
                try {
                    Storage::disk('public')->delete($filename);
                    \Log::info('Cleaned up file: ' . $filename);
                } catch (\Exception $cleanupError) {
                    \Log::error('Error cleaning up file: ' . $cleanupError->getMessage());
                }
            }

            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat submit transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updateCartTotal($cartId)
    {
        $cart = Transaction::find($cartId);
        
        // Hitung total dari detail
        $totals = TransactionDetail::join('waste_categories', 'transaction_details.category_id', '=', 'waste_categories.id')
            ->where('transaction_id', $cartId)
            ->selectRaw('SUM(estimated_weight) as total_weight, CAST(SUM(estimated_weight * price_per_kg) AS DECIMAL(10,2)) as total_price')
            ->first();

        $cart->total_weight = $totals->total_weight ?? 0;
        $cart->total_price = $totals->total_price ?? 0;
        $cart->save();
    }
} 