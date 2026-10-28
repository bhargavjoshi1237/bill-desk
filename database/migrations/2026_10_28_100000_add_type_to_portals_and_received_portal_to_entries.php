<?php

use App\Models\Entry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portals', function (Blueprint $table) {
            $table->string('type')->default('portal')->after('name')->index();
        });

        Schema::table('entries', function (Blueprint $table) {
            // portal_id is the portal / account that paid the bill; this one received the customer's money.
            $table->foreignId('received_portal_id')->nullable()->after('portal_id')->constrained('portals')->restrictOnDelete();
        });

        // The customer now owes only the portal fee + service charge, so re-total every entry.
        Entry::query()->chunkById(200, function ($entries): void {
            foreach ($entries as $entry) {
                $entry->recalculate()->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_portal_id');
        });

        Schema::table('portals', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
