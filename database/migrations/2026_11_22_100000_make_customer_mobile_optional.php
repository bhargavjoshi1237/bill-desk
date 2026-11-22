<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some customers (e.g. from the old Excel sheet) have no mobile number.
     * The unique index stays, so a filled-in mobile still can't repeat.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mobile', 15)->nullable()->change();
        });
    }

    /**
     * Fails if any customer has no mobile; fill those in first.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mobile', 15)->nullable(false)->change();
        });
    }
};
