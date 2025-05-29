<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\WasteCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class TransactionController extends Controller
{
    const STATUS_PENDING = 'pending';
    const STATUS_VERIFIED = 'verified';
    const STATUS_REJECTED = 'rejected';

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickupLocation' => 'required|string',
            'categoryId' => 'required|exists:waste_categories,id',
            'estimatedWeight' => 'required|numeric|min:0.1',
            'photo' => 'nullable|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload foto jika ada
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $filename = time() . '-' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $photoPath = $photo->storeAs('waste-photos', $filename, 'public');
        }

        $category = WasteCategory::find($request->categoryId);

        $qrPath = 'qrcodes/tx-' . time() . '-' . uniqid() . '.png';

        $transaction = Transaction::create([
            'user_id' => Auth::id(),
            'pickup_location' => $request->pickupLocation,
            'total_weight' => $request->estimatedWeight,
            'total_price' => $request->estimatedWeight * $category->price_per_kg,
            'status' => self::STATUS_PENDING,
            'qr_code_path' => $qrPath
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'category_id' => $request->categoryId,
            'estimated_weight' => $request->estimatedWeight,
            'photo_path' => $photoPath
        ]);

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'scale' => 5,
            'imageBase64' => false,
        ]);

        $qrcode = new QRCode($options);
        
        Storage::disk('public')->put(
            $qrPath, 
            $qrcode->render($transaction->id)
        );

        return response()->json([
            'status' => true,
            'message' => 'Transaksi berhasil dibuat',
            'data' => [
                'transactionId' => $transaction->id,
                'status' => $transaction->status,
                'qrCodePath' => Storage::url($qrPath),
                'photoPath' => $photoPath ? Storage::url($photoPath) : null
            ]
        ], 201);
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
} 