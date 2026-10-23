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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('actor_id')->nullable();    // user who performed the action
            $table->string('actor_type', 20)->nullable(); // 'user' | 'admin' | 'system'
            $table->string('action', 100);           // 'transaction.completed', 'user.created', etc.
            $table->string('subject_type', 50)->nullable(); // model class short name
            $table->uuid('subject_id')->nullable();  // model primary key
            $table->json('before')->nullable();      // state before mutation
            $table->json('after')->nullable();       // state after mutation
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
 
            $table->index(['actor_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
