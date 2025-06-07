<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WasteCategory;
use App\Models\User;
use App\Models\BalanceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    const STATUS_CART = 'cart';
    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';
    const AUTO_VERIFY_WEIGHT_LIMIT = 20;

    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->json()->all(), [
            'categoryId' => 'required|exists:waste_categories,id',
            'estimatedWeight' => 'required|numeric|min:0.01',
            'pickupLocation' => 'nullable|string'
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

            // Cari cart yang masih aktif
            $cart = Transaction::where('user_id', Auth::id())
                ->where('status', 'cart')
                ->first();

            // Jika tidak ada cart, buat baru
            if (!$cart) {
                $cart = Transaction::create([
                    'user_id' => Auth::id(),
                    'total_weight' => 0,
                    'total_price' => 0,
                    'status' => 'cart',
                    'pickup_location' => $request->json('pickupLocation')
                ]);
            } else if ($request->json('pickupLocation')) {
                // Update pickup location jika ada
                $cart->pickup_location = $request->json('pickupLocation');
                $cart->save();
            }

            $category = WasteCategory::find($request->json('categoryId'));
            if (!$category) {
                return response()->json([
                    'status' => false,
                    'message' => 'Kategori sampah tidak ditemukan'
                ], 404);
            }

            $weight = floatval($request->json('estimatedWeight'));
            $price = $weight * $category->price_per_kg;

            // Buat detail transaksi
            TransactionDetail::create([
                'transaction_id' => $cart->id,
                'category_id' => $category->id,
                'estimated_weight' => $weight,
                'photo_path' => null
            ]);

            // Update total di cart
            $cart->update([
                'total_weight' => $cart->total_weight + $weight,
                'total_price' => $cart->total_price + $price
            ]);

            DB::commit();

            // Load relationships dan format response
            $cart->load('details.category');
            
            return response()->json([
                'status' => true,
                'message' => 'Item berhasil ditambahkan ke cart',
                'data' => [
                    'id' => $cart->id,
                    'user_id' => $cart->user_id,
                    'pickup_location' => $cart->pickup_location,
                    'total_weight' => $cart->total_weight,
                    'total_price' => $cart->total_price,
                    'status' => $cart->status,
                    'created_at' => $cart->created_at,
                    'updated_at' => $cart->updated_at,
                    'details' => $cart->details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'transaction_id' => $detail->transaction_id,
                            'category_id' => $detail->category_id,
                            'estimated_weight' => $detail->estimated_weight,
                            'actual_weight' => $detail->actual_weight,
                            'photo_path' => $detail->photo_path,
                            'created_at' => $detail->created_at,
                            'updated_at' => $detail->updated_at,
                            'category' => $detail->category
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in addToCart: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickupLocation' => 'required|string',
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048'
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

            $cart = Transaction::where('user_id', Auth::id())
                ->where('status', 'cart')
                ->first();

            if (!$cart) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart tidak ditemukan'
                ], 404);
            }

            if ($cart->details->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart masih kosong'
                ], 400);
            }

            // Upload foto
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $filename = time() . '-' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $photoPath = $photo->storeAs('waste-photos', $filename, 'public');

                // Update photo_path di semua detail transaksi
                foreach ($cart->details as $detail) {
                    $detail->update(['photo_path' => $photoPath]);
                }
            }

            // Update cart menjadi pending
            $cart->update([
                'pickup_location' => $request->pickupLocation,
                'status' => self::STATUS_PENDING
            ]);

            DB::commit();

            // Load relationships dan format response
            $cart->load('details.category');

            return response()->json([
                'status' => true,
                'message' => 'Transaksi berhasil disubmit',
                'data' => [
                    'id' => $cart->id,
                    'user_id' => $cart->user_id,
                    'pickup_location' => $cart->pickup_location,
                    'total_weight' => $cart->total_weight,
                    'total_price' => $cart->total_price,
                    'status' => $cart->status,
                    'created_at' => $cart->created_at,
                    'updated_at' => $cart->updated_at,
                    'details' => $cart->details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'transaction_id' => $detail->transaction_id,
                            'category_id' => $detail->category_id,
                            'estimated_weight' => $detail->estimated_weight,
                            'actual_weight' => $detail->actual_weight,
                            'photo_path' => $detail->photo_path ? Storage::url($detail->photo_path) : null,
                            'created_at' => $detail->created_at,
                            'updated_at' => $detail->updated_at,
                            'category' => $detail->category
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in submitCart: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $query = Transaction::with(['details.category', 'user']);

            // Jika user biasa, hanya bisa lihat transaksinya sendiri
            // Jika collector atau admin, bisa lihat semua transaksi
            if (!in_array($user->role, ['collector', 'admin'])) {
                $query->where('user_id', $user->id);
            }

            $transaction = $query->findOrFail($id);

            // Transform photo_path to full URL
            if ($transaction->image_path) {
                $transaction->image_path = Storage::url($transaction->image_path);
            }
            
            $transaction->details->transform(function ($detail) {
                if ($detail->photo_path) {
                    $detail->photo_path = Storage::url($detail->photo_path);
                }
                if ($detail->category && $detail->category->image_path) {
                    $detail->category->image_path = Storage::url($detail->category->image_path);
                }
                return $detail;
            });

            return response()->json([
                'status' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in transaction show: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'status' => false,
                'message' => 'Transaksi tidak ditemukan'
            ], 404);
        }
    }

    public function submitVerification(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'actualWeights' => 'required'
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
                    'message' => 'Transaksi sudah diverifikasi atau ditolak'
                ], 400);
            }

            $details = $transaction->details;
            $actualWeights = [];

            if (!is_array($request->actualWeights)) {
                if (!is_numeric($request->actualWeights) || floatval($request->actualWeights) <= 0) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Berat harus berupa angka dan lebih dari 0'
                    ], 422);
                }
                $actualWeights = array_fill(0, $details->count(), floatval($request->actualWeights));
            } else {
                foreach ($request->actualWeights as $weight) {
                    if (!is_numeric($weight) || floatval($weight) <= 0) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Semua berat harus berupa angka dan lebih dari 0'
                        ], 422);
                    }
                }
                
                foreach ($details as $index => $detail) {
                    if (isset($request->actualWeights[$index])) {
                        $actualWeights[$index] = floatval($request->actualWeights[$index]);
                    } else {
                        $actualWeights[$index] = $detail->actual_weight > 0 
                            ? $detail->actual_weight 
                            : $detail->estimated_weight;
                    }
                }
            }

            $totalWeight = 0;
            $totalPrice = 0;
            $weightDifferences = [];

            foreach ($details as $index => $detail) {
                $actualWeight = $actualWeights[$index];
                $pricePerKg = $detail->category->price_per_kg;
                
                $detail->update([
                    'actual_weight' => $actualWeight
                ]);

                $totalWeight += $actualWeight;
                $totalPrice += $actualWeight * $pricePerKg;
                
                $weightDifferences[] = [
                    'category' => $detail->category->name,
                    'estimated' => $detail->estimated_weight,
                    'actual' => $actualWeight,
                    'difference' => $actualWeight - $detail->estimated_weight,
                    'was_updated' => isset($request->actualWeights[$index]) || !is_array($request->actualWeights)
                ];
            }

            $transaction->update([
                'total_weight' => $totalWeight,
                'total_price' => $totalPrice,
                'status' => self::STATUS_VERIFIED
            ]);

            // Langsung tambahkan saldo untuk semua transaksi yang terverifikasi
            $user = $transaction->user;
            $user->update([
                'balance' => $user->balance + $totalPrice
            ]);

            // Catat balance history
            BalanceHistory::create([
                'user_id' => $user->id,
                'amount' => $totalPrice,
                'transaction_id' => $transaction->id,
                'timestamp' => now()
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Verifikasi berhasil dan saldo telah ditambahkan',
                'data' => [
                    'transaction' => $transaction->fresh(['details.category', 'user']),
                    'weight_differences' => $weightDifferences,
                    'needs_admin_approval' => false,
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

                // Catat balance history untuk transaksi yang disetujui admin
                BalanceHistory::create([
                    'user_id' => $user->id,
                    'amount' => $transaction->total_price,
                    'transaction_id' => $transaction->id,
                    'timestamp' => now()
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

    public function removeFromCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'detailId' => 'required|integer|exists:transaction_details,id'
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

            // Cari detail transaksi yang akan dihapus
            $detail = TransactionDetail::with(['transaction', 'category'])
                ->whereHas('transaction', function($query) {
                    $query->where('user_id', Auth::id())
                          ->where('status', 'cart');
                })
                ->find($request->detailId);

            if (!$detail) {
                return response()->json([
                    'status' => false,
                    'message' => 'Item tidak ditemukan dalam cart'
                ], 404);
            }

            $cart = $detail->transaction;
            
            // Hitung pengurangan total
            $weightReduction = $detail->estimated_weight;
            $priceReduction = $detail->estimated_weight * $detail->category->price_per_kg;

            // Hapus detail
            $detail->delete();

            // Update total di cart
            $cart->update([
                'total_weight' => $cart->total_weight - $weightReduction,
                'total_price' => $cart->total_price - $priceReduction
            ]);

            // Jika cart kosong, hapus cart
            if ($cart->details()->count() === 0) {
                $cart->delete();
                
                return response()->json([
                    'status' => true,
                    'message' => 'Item terakhir berhasil dihapus, cart telah dikosongkan',
                    'data' => null
                ]);
            }

            // Load cart yang diperbarui
            $cart->load('details.category');

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Item berhasil dihapus dari cart',
                'data' => [
                    'id' => $cart->id,
                    'user_id' => $cart->user_id,
                    'total_weight' => $cart->total_weight,
                    'total_price' => $cart->total_price,
                    'status' => $cart->status,
                    'details' => $cart->details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'transaction_id' => $detail->transaction_id,
                            'category_id' => $detail->category_id,
                            'estimated_weight' => $detail->estimated_weight,
                            'category' => $detail->category
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in removeFromCart: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCart()
    {
        try {
            $cart = Transaction::with(['details.category'])
                ->where('user_id', Auth::id())
                ->where('status', self::STATUS_CART)
                ->first();

            if (!$cart) {
                return response()->json([
                    'status' => true,
                    'message' => 'Cart kosong',
                    'data' => null
                ]);
            }

            // Transform photo_path to full URL for each detail
            $cart->details->transform(function ($detail) {
                if ($detail->photo_path) {
                    $detail->photo_path = Storage::url($detail->photo_path);
                }
                return $detail;
            });

            return response()->json([
                'status' => true,
                'message' => 'Berhasil mengambil data cart',
                'data' => $cart
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getCart: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mengambil data cart'
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $status = $request->query('status', 'pending');
            \Log::info('Fetching transactions with status: ' . $status);

            $query = Transaction::with(['details.category'])
                ->where('user_id', $user->id)
                ->where('status', '!=', 'cart');

            if ($status === 'pending') {
                $query->where('status', 'pending');
            } else {
                $query->whereIn('status', ['verified', 'rejected']);
            }

            $transactions = $query->orderBy('created_at', 'desc')->get();
            
            \Log::info('Found transactions: ' . $transactions->count());

            return response()->json([
                'status' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in transactions index: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mengambil data transaksi'
            ], 500);
        }
    }

    /**
     * Get pending transactions (for collectors and admins)
     */
    public function getPendingTransactions()
    {
        try {
            $user = Auth::user();
            \Log::info('User attempting to access pending transactions:', [
                'user_id' => $user->id,
                'role' => $user->role
            ]);

            if (!$user || !in_array($user->role, ['collector', 'admin'])) {
                \Log::warning('Unauthorized access attempt to pending transactions');
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            \Log::info('Fetching pending transactions for collector/admin');

            $query = Transaction::with(['user', 'details.category'])
                ->where('status', self::STATUS_PENDING)
                ->orderBy('created_at', 'desc');

            \Log::info('SQL Query:', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            $transactions = $query->get();

            \Log::info('Raw transactions found:', [
                'count' => $transactions->count(),
                'first_transaction' => $transactions->first()
            ]);

            // Transform photo_path to full URL for each detail
            $transactions->transform(function ($transaction) {
                if ($transaction->image_path) {
                    $transaction->image_path = Storage::url($transaction->image_path);
                }
                $transaction->details->transform(function ($detail) {
                    if ($detail->photo_path) {
                        $detail->photo_path = Storage::url($detail->photo_path);
                    }
                    return $detail;
                });
                return $transaction;
            });

            \Log::info('Found pending transactions: ' . $transactions->count());

            if ($transactions->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'Tidak ada transaksi pending',
                    'data' => []
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Berhasil mengambil data transaksi pending',
                'data' => $transactions
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getPendingTransactions: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mengambil data transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search transactions by user name
     */
    public function searchTransactions(Request $request)
    {
        try {
            $user = Auth::user();
            \Log::info('User attempting to search transactions:', [
                'user_id' => $user->id,
                'role' => $user->role
            ]);

            if (!$user || !in_array($user->role, ['collector', 'admin'])) {
                \Log::warning('Unauthorized access attempt to search transactions');
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $query = $request->query('query');
            \Log::info('Searching transactions with query: ' . $query);

            $transactions = Transaction::with(['user', 'details.category'])
                ->whereHas('user', function($q) use ($query) {
                    $q->where('name', 'like', '%' . $query . '%');
                })
                ->where('status', self::STATUS_PENDING)
                ->orderBy('created_at', 'desc')
                ->get();

            // Transform photo_path to full URL for each detail
            $transactions->transform(function ($transaction) {
                if ($transaction->image_path) {
                    $transaction->image_path = Storage::url($transaction->image_path);
                }
                $transaction->details->transform(function ($detail) {
                    if ($detail->photo_path) {
                        $detail->photo_path = Storage::url($detail->photo_path);
                    }
                    return $detail;
                });
                return $transaction;
            });

            \Log::info('Found transactions: ' . $transactions->count());

            return response()->json([
                'status' => true,
                'message' => 'Berhasil mencari transaksi',
                'data' => $transactions
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in searchTransactions: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mencari transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUserTransactions()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            \Log::info('Fetching transactions for user:', ['user_id' => $user->id]);

            $transactions = Transaction::with(['details.category'])
                ->where('user_id', $user->id)
                ->where('status', '!=', 'cart')
                ->orderBy('created_at', 'desc')
                ->get();

            // Transform photo paths to full URLs
            $transactions->transform(function ($transaction) {
                if ($transaction->image_path) {
                    $transaction->image_path = Storage::url($transaction->image_path);
                }
                $transaction->details->transform(function ($detail) {
                    if ($detail->photo_path) {
                        $detail->photo_path = Storage::url($detail->photo_path);
                    }
                    if ($detail->category && $detail->category->image_path) {
                        $detail->category->image_path = Storage::url($detail->category->image_path);
                    }
                    return $detail;
                });
                return $transaction;
            });

            \Log::info('Found transactions:', ['count' => $transactions->count()]);

            return response()->json([
                'status' => true,
                'message' => 'Berhasil mengambil data transaksi',
                'data' => $transactions
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getUserTransactions: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat mengambil data transaksi'
            ], 500);
        }
    }

    public function getUserTransaction($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $transaction = Transaction::with(['details.category'])
                ->where('user_id', $user->id)
                ->findOrFail($id);

            if ($transaction->image_path) {
                $transaction->image_path = Storage::url($transaction->image_path);
            }
            $transaction->details->transform(function ($detail) {
                if ($detail->photo_path) {
                    $detail->photo_path = Storage::url($detail->photo_path);
                }
                if ($detail->category && $detail->category->image_path) {
                    $detail->category->image_path = Storage::url($detail->category->image_path);
                }
                return $detail;
            });

            return response()->json([
                'status' => true,
                'message' => 'Berhasil mengambil detail transaksi',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getUserTransaction: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Transaksi tidak ditemukan'
            ], 404);
        }
    }
}