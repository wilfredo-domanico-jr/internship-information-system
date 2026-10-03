<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internship_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('city', 100);
            $table->text('description');
            $table->text('responsibilities')->nullable();
            $table->date('closing_date')->nullable();
            $table->unsignedInteger('required_hours')->nullable();
            $table->unsignedInteger('vacancies')->default(1);
            $table->string('contact_name');
            $table->string('contact_position')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->string('resume_path');
            $table->string('endorsement_path');
            $table->string('status', 20)->default('pending')->index();
            $table->text('decline_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['internship_posting_id', 'intern_id']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('venue');
            $table->string('link')->nullable();
            $table->date('scheduled_on');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cos_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->string('acceptance_letter_path');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->unsignedInteger('hours_rendered')->default(0);
            $table->unsignedInteger('absences')->default(0);
            $table->timestamps();
            $table->index(['intern_id', 'ended_at']);
        });

        Schema::create('dtrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('hours');
            $table->unsignedInteger('absences')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->string('control_no', 20)->unique();
            $table->string('document_name');
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('file_path')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->unsignedInteger('hours_at_issue');
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('document_requests');
        Schema::dropIfExists('dtrs');
        Schema::dropIfExists('placements');
        Schema::dropIfExists('cos_applications');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('internship_postings');
    }
};
