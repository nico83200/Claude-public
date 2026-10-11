<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consignes données à l'assistant (texte uniquement : l'audio n'est jamais conservé).
        Schema::create('assistant_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->uuid('conversation_id')->index();
            $table->enum('input_mode', ['voice', 'text'])->default('text');
            $table->text('input_text');
            $table->text('reply')->nullable();
            $table->enum('status', ['done', 'clarify', 'error'])->default('done');
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Actions réalisées, pour l'historique et l'annulation.
        Schema::create('assistant_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_request_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->nullableMorphs('subject');
            $table->string('summary');
            $table->string('url')->nullable();
            $table->json('undo')->nullable(); // données nécessaires à l'annulation
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('assistant_monthly')->nullable()->after('storage_mb'); // null = illimité
        });

        // Offres payantes existantes : assistant inclus avec un quota mensuel par espace.
        foreach (['particulier' => 150, 'multi-chevaux' => 400, 'ecurie' => 2000] as $slug => $quota) {
            $plan = DB::table('subscription_plans')->where('slug', $slug)->first();
            if ($plan) {
                $features = json_decode($plan->features ?? '[]', true) ?: [];
                $features[] = 'assistant';
                DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode(array_values(array_unique($features))), 'assistant_monthly' => $quota]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_actions');
        Schema::dropIfExists('assistant_requests');
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn('assistant_monthly'));
    }
};
