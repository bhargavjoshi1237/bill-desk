<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date')->index();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('portal_id')->constrained()->restrictOnDelete();

            // Inputs.
            $table->decimal('bill_amount', 14, 2);
            $table->decimal('portal_charge_percent', 6, 3);
            $table->decimal('service_charge_percent', 6, 3);

            // Derived values, kept in sync by Entry::recalculate().
            $table->decimal('portal_deduction', 14, 2)->default(0);
            $table->decimal('service_charge', 14, 2)->default(0);
            $table->decimal('total_due', 14, 2)->default(0);
            $table->decimal('amount_received', 14, 2)->default(0);
            $table->decimal('outstanding', 14, 2)->default(0);
            $table->decimal('net_profit', 14, 2)->default(0);
            $table->string('status')->default('unpaid')->index();
            $table->date('paid_at')->nullable()->index();

            $table->date('due_date')->nullable()->index();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on')->index();
            $table->decimal('amount', 14, 2);
            $table->string('mode');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('entries');
    }
};
