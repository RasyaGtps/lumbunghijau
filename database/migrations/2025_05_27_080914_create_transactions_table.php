<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('pickup_location')->nullable();
            $table->decimal('total_weight', 8, 2);
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['cart', 'pending', 'verified', 'rejected'])->default('cart');
            $table->string('image_path')->nullable();
            $table->string('verification_token', 64)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
