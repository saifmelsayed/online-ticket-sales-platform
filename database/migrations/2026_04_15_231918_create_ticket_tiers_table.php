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
        Schema::create('ticket_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            //info
            $table->string('name');
            $table->decimal('base_price', 10, 2);
            $table->integer('total_seats');
            $table->integer('sold_count')->default(0);
            //sale time
            $table->dateTime('sale_starts_at');
            $table->dateTime('sale_ends_at');
            
            $table->unsignedBigInteger('version')->default(1);



            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_tiers');
    }
};
