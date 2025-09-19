<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['New', 'Open', 'In Progress', 'Completed'])->default('New');
            $table->enum('priority', ['Low', 'Medium', 'High', 'Critical'])->default('Medium');
            $table->date('due_date')->nullable();
            $table->decimal('time_estimate', 5, 2)->nullable(); // Added time estimate
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('pair_programmer_id')->nullable()->constrained('users')->onDelete('set null'); // Added pair programmer
            $table->timestamps();
            
            $table->index(['status']);
            $table->index(['due_date']);
            $table->index(['created_by']);
            $table->index(['pair_programmer_id']); // Added index
        });
    }

    public function down()
    {
        Schema::dropIfExists('tasks');
    }
};