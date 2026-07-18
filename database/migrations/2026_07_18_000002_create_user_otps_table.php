<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_otps', function (Blueprint $table) {
            $table->id();
            $table->string('user_type', 20);
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->string('code_hash');
            $table->string('session_token', 64)->unique();
            $table->timestamp('code_expires_at');
            $table->timestamp('resend_allowed_at');
            $table->integer('attempts_remaining')->default(3);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['user_type', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
    }
};
