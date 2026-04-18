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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->string('name');
            $table->text('description');
            $table->dateTime('event_datetime');
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('banner_image')->nullable();

            $table->enum('status', ['upcoming', 'cancelled', 'completed'])->default('upcoming');


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
