<?php

use App\Http\Controllers\Api\Procurement\ProcurementCategoryController;
use App\Http\Controllers\Api\Procurement\ProcurementRequestController;
use App\Http\Controllers\Api\Procurement\ProcurementTypeController;
use App\Http\Controllers\Api\Payment\PaymentCategoryController;
use App\Http\Controllers\Api\Payment\PaymentRequestController;
use App\Http\Controllers\Api\Payment\PaymentTypeController;
use App\Http\Controllers\Api\Reports\ProcurementPaymentReportController;
use App\Http\Controllers\Api\Budget\BudgetController;
use App\Http\Controllers\Api\Budget\BudgetTransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/procurement-payment-reports/summary', [ProcurementPaymentReportController::class, 'summary']);
    Route::get('/procurement-requests', [ProcurementRequestController::class, 'index']);
    Route::get('/procurement-requests-initial-approvers', [ProcurementRequestController::class, 'initialApprovers']);
    Route::post('/procurement-requests', [ProcurementRequestController::class, 'store']);
    Route::get('/procurement-requests/{id}', [ProcurementRequestController::class, 'show']);
    Route::post('/procurement-requests/{id}', [ProcurementRequestController::class, 'update']);
    Route::put('/procurement-requests/{id}', [ProcurementRequestController::class, 'update']);
    Route::delete('/procurement-requests/{id}', [ProcurementRequestController::class, 'destroy']);
    Route::post('/procurement-requests/{id}/action', [ProcurementRequestController::class, 'action']);

    Route::apiResource('procurement-categories', ProcurementCategoryController::class);
    Route::apiResource('procurement-types', ProcurementTypeController::class);

    Route::get('/budgets/summary', [BudgetController::class, 'summary']);
    Route::get('/budgets/fiscal-years', [BudgetController::class, 'fiscalYears']);
    Route::get('/budgets/bi-codes', [BudgetController::class, 'biCodes']);
    Route::get('/budgets/account-codes', [BudgetController::class, 'accountCodes']);
    Route::get('/budgets/payment-type-balance', [BudgetController::class, 'paymentTypeBalance']);
    Route::get('/budgets/aggregate', [BudgetController::class, 'aggregate']);
    Route::apiResource('budgets', BudgetController::class);
    Route::get('/budget-transactions', [BudgetTransactionController::class, 'index']);

    Route::apiResource('payment-categories', PaymentCategoryController::class);
    Route::apiResource('payment-types', PaymentTypeController::class);

    Route::get('/payment-requests', [PaymentRequestController::class, 'index']);
    Route::get('/payment-requests-initial-approvers', [PaymentRequestController::class, 'initialApprovers']);
    Route::get('/payment-requests-planning-budget-experts', [PaymentRequestController::class, 'planningBudgetExperts']);
    Route::post('/payment-requests', [PaymentRequestController::class, 'store']);
    Route::get('/payment-requests/{id}', [PaymentRequestController::class, 'show']);
    Route::post('/payment-requests/{id}', [PaymentRequestController::class, 'update']);
    Route::put('/payment-requests/{id}', [PaymentRequestController::class, 'update']);
    Route::delete('/payment-requests/{id}', [PaymentRequestController::class, 'destroy']);
    Route::post('/payment-requests/{id}/action', [PaymentRequestController::class, 'action']);
});
