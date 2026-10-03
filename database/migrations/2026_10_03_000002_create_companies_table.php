<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            // Null for COS partner companies that have no portal login.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 100)->nullable();
            $table->string('company_code', 20)->unique();
            $table->string('logo_path')->nullable();
            $table->text('about')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('permit_path')->nullable();
            $table->string('moa_path')->nullable();
            $table->string('approval_status', 20)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
