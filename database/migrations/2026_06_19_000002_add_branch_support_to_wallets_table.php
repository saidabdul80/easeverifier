<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        Schema::table('wallets', function (Blueprint $table) {
            // Add a normal index first so the user_id foreign key still has an index
            $table->index('user_id', 'wallets_user_id_index');
        });

        Schema::table('wallets', function (Blueprint $table) {
            // Now MySQL can safely drop the unique index
            $table->dropUnique('wallets_user_id_unique');

            // Recommended: one wallet per user per branch
            $table->unique(['user_id', 'branch_id'], 'wallets_user_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique('wallets_user_branch_unique');

            $table->unique('user_id', 'wallets_user_id_unique');

            $table->dropIndex('wallets_user_id_index');

            $table->dropConstrainedForeignId('branch_id');
        });
    }
};