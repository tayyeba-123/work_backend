<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->nullableMorphs('related');
            $table->timestamps();
            
            $table->index(['user_id']);
            $table->index(['type']);
            $table->index(['read_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};