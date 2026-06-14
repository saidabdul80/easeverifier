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
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->string('title');
            $table->string('recipient_scope')->default('all');
            $table->json('selected_customer_ids')->nullable();
            $table->string('subject');
            $table->string('heading')->nullable();
            $table->text('body');
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};
