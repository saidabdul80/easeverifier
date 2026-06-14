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
        if (Schema::hasColumn('email_campaigns', 'additional_emails')) {
            return;
        }

        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->json('additional_emails')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('email_campaigns', 'additional_emails')) {
            return;
        }

        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn('additional_emails');
        });
    }
};
