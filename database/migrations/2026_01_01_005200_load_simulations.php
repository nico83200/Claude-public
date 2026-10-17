<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Simulations de charge (super-admin) : lots de comptes de test, supprimables ensuite. */
    public function up(): void
    {
        Schema::create('simulation_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('running'); // running, stopped, done, purging, purged
            $table->json('params');
            $table->json('counts')->nullable();
            $table->unsignedInteger('accounts_done')->default(0);
            $table->unsignedBigInteger('elapsed_ms')->default(0);
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('simulation_batch_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('simulation_batch_id'));
        Schema::dropIfExists('simulation_batches');
    }
};
