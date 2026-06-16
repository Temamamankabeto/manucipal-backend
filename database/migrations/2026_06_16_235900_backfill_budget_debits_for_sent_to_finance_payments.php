<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_requests') || ! Schema::hasTable('budgets') || ! Schema::hasTable('budget_transactions')) {
            return;
        }

        DB::transaction(function (): void {
            $payments = DB::table('payment_requests')
                ->select(['id', 'budget_id', 'payment_no', 'request_no', 'amount', 'status', 'records_signed_by', 'updated_at'])
                ->whereNotNull('budget_id')
                ->whereIn('status', ['sent_to_finance', 'payment_completed', 'completed'])
                ->where('amount', '>', 0)
                ->orderBy('id')
                ->get();

            foreach ($payments as $payment) {
                $alreadyRecorded = DB::table('budget_transactions')
                    ->where('payment_request_id', $payment->id)
                    ->where('type', 'DEBIT')
                    ->exists();

                if ($alreadyRecorded) {
                    continue;
                }

                $budget = DB::table('budgets')
                    ->where('id', $payment->budget_id)
                    ->lockForUpdate()
                    ->first();

                if (! $budget) {
                    continue;
                }

                $amount = (float) $payment->amount;
                $before = (float) $budget->remaining_amount;
                $after = $before - $amount;
                $used = (float) $budget->used_amount + $amount;

                if ($after < 0) {
                    continue;
                }

                DB::table('budgets')
                    ->where('id', $budget->id)
                    ->update([
                        'used_amount' => number_format($used, 2, '.', ''),
                        'remaining_amount' => number_format($after, 2, '.', ''),
                        'updated_at' => now(),
                    ]);

                DB::table('budget_transactions')->insert([
                    'budget_id' => $budget->id,
                    'payment_request_id' => $payment->id,
                    'transaction_no' => $this->nextTransactionNo($payment->id),
                    'type' => 'DEBIT',
                    'amount' => number_format($amount, 2, '.', ''),
                    'balance_before' => number_format($before, 2, '.', ''),
                    'balance_after' => number_format($after, 2, '.', ''),
                    'remarks' => 'Budget deducted for payment ' . ($payment->payment_no ?: ($payment->request_no ?: $payment->id)),
                    'created_by' => $payment->records_signed_by,
                    'created_at' => $payment->updated_at ?: now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Safe one-way data repair. Do not auto-reverse budget balances.
    }

    private function nextTransactionNo(int $paymentId): string
    {
        return 'BT-' . now()->format('YmdHis') . '-' . $paymentId;
    }
};
