<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'credit' | 'debit'
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->date('transaction_date');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
            $table->index('type');
            // One transaction per order/expense — prevents the delivered
            // and expense-created hooks from ever double-recording the
            // same event if they somehow fire twice.
            $table->unique('order_id');
            $table->unique('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
