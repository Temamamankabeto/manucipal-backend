<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));
        $page = max(1, (int) $request->integer('page', 1));
        $items = $this->actionNeededItems($request->user());
        $total = $items->count();
        $paged = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'message' => 'Role based action notifications retrieved successfully',
            'data' => $paged,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->actionNeededItems($request->user())->count();

        return response()->json([
            'success' => true,
            'message' => 'Role based unread action notification count retrieved successfully',
            'data' => ['count' => $count],
            'meta' => ['count' => $count],
        ]);
    }

    public function markRead(int|string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Notification acknowledged successfully',
            'data' => ['id' => $id],
            'meta' => null,
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Notifications acknowledged successfully',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Notification dismissed successfully',
            'data' => ['id' => $id],
            'meta' => null,
        ]);
    }

    private function actionNeededItems(User $user): Collection
    {
        return $this->paymentActionItems($user)
            ->merge($this->procurementActionItems($user))
            ->sortByDesc('created_at')
            ->values();
    }

    private function paymentActionItems(User $user): Collection
    {
        $items = PaymentRequest::query()
            ->with(['paymentType:id,name', 'requester:id,name', 'currentHandler:id,name'])
            ->whereNotIn('status', [
                $this->paymentStatus('payment_completed'),
                $this->paymentStatus('rejected'),
            ])
            ->where(fn (Builder $query) => $this->paymentActionScope($query, $user))
            ->latest()
            ->limit(50)
            ->get();

        return $items->map(function (PaymentRequest $payment) {
            $paymentType = optional($payment->paymentType)->name;
            $requestNo = $payment->request_no ?: ('Payment #' . $payment->id);

            return [
                'id' => 'payment-' . $payment->id,
                'type' => 'payment_action_needed',
                'module' => 'payment',
                'title' => 'Payment action needed',
                'message' => trim($requestNo . ' is waiting at ' . str_replace('_', ' ', (string) $payment->status) . ($paymentType ? ' — ' . $paymentType : '') . '.'),
                'read_at' => null,
                'created_at' => optional($payment->updated_at ?? $payment->created_at)->toISOString(),
                'sent_at' => optional($payment->updated_at ?? $payment->created_at)->toISOString(),
                'data' => [
                    'payment_id' => $payment->id,
                    'request_no' => $payment->request_no,
                    'status' => $payment->status,
                    'payment_type' => $paymentType,
                    'redirect_url' => '/dashboard/payment/' . $payment->id,
                ],
            ];
        });
    }

    private function paymentActionScope(Builder $query, User $user): void
    {
        $roles = $this->normalizedRoles($user);

        $query->where(function (Builder $q) use ($user, $roles) {
            // Draft notifications are personal. Only the creator sees their own draft submit action.
            $q->where(function (Builder $draft) use ($user) {
                $draft->where('status', $this->paymentStatus('draft'))
                    ->where('requested_by', $user->id);
            });

            // Manager / Development Head / Service Head: only requests sent to this logged-in user.
            if ($roles->intersect(['manager', 'municipal-manager', 'head-of-development-branch', 'head-of-service-branch'])->isNotEmpty()) {
                $q->orWhere(function (Builder $manager) use ($user) {
                    $manager->whereIn('status', [
                        $this->paymentStatus('manager_review'),
                        $this->paymentStatus('manager_final_review'),
                    ])->where('current_handler_id', $user->id);
                });
            }

            // Department Team Leader: only items assigned/handled by this Team Leader.
            if ($roles->intersect(['team-leader', 'department-head', 'team-leader-department-head'])->isNotEmpty()) {
                $q->orWhere(function (Builder $teamLeader) use ($user) {
                    $teamLeader->whereIn('status', [
                        $this->paymentStatus('budget_tl_review'),
                        $this->paymentStatus('budget_tl_final_review'),
                    ])->where(function (Builder $assigned) use ($user) {
                        $assigned->where('assigned_team_leader_id', $user->id)
                            ->orWhere('current_handler_id', $user->id);
                    });
                });
            }

            // Expert: only requests forwarded to this logged-in expert.
            if ($roles->contains('expert')) {
                $q->orWhere(function (Builder $expert) use ($user) {
                    $expert->where('status', $this->paymentStatus('budget_expert_processing'))
                        ->where(function (Builder $assigned) use ($user) {
                            $assigned->where('assigned_expert_id', $user->id)
                                ->orWhere('current_handler_id', $user->id);
                        });
                });
            }

            // Record Officer/Secretory: role based record-office inbox.
            if ($roles->intersect(['record-officer', 'records-office', 'record-office', 'secretory'])->isNotEmpty()) {
                $q->orWhere('status', $this->paymentStatus('records_processing'));
            }

            // Accountant: role based finance inbox.
            if ($roles->intersect(['accountant', 'finance-accountant', 'finance'])->isNotEmpty()) {
                $q->orWhere('status', $this->paymentStatus('sent_to_finance'));
            }
        });
    }

    private function procurementActionItems(User $user): Collection
    {
        if (! class_exists(ProcurementRequest::class)) {
            return collect();
        }

        $items = ProcurementRequest::query()
            ->with(['category:id,name', 'procurementType:id,name', 'requester:id,name', 'currentHandler:id,name'])
            ->whereNotIn('status', [
                $this->procurementStatus('completed'),
                $this->procurementStatus('rejected'),
            ])
            ->where(fn (Builder $query) => $this->procurementActionScope($query, $user))
            ->latest()
            ->limit(50)
            ->get();

        return $items->map(function (ProcurementRequest $procurement) {
            $procurementType = optional($procurement->procurementType)->name;
            $requestNo = $procurement->request_no ?: ('Procurement #' . $procurement->id);

            return [
                'id' => 'procurement-' . $procurement->id,
                'type' => 'procurement_action_needed',
                'module' => 'procurement',
                'title' => 'Procurement action needed',
                'message' => trim($requestNo . ' is waiting at ' . str_replace('_', ' ', (string) $procurement->status) . ($procurementType ? ' — ' . $procurementType : '') . '.'),
                'read_at' => null,
                'created_at' => optional($procurement->updated_at ?? $procurement->created_at)->toISOString(),
                'sent_at' => optional($procurement->updated_at ?? $procurement->created_at)->toISOString(),
                'data' => [
                    'procurement_id' => $procurement->id,
                    'request_no' => $procurement->request_no,
                    'status' => $procurement->status,
                    'procurement_type' => $procurementType,
                    'redirect_url' => '/dashboard/procurement/' . $procurement->id,
                ],
            ];
        });
    }

    private function procurementActionScope(Builder $query, User $user): void
    {
        $roles = $this->normalizedRoles($user);

        $query->where(function (Builder $q) use ($user, $roles) {
            $q->where(function (Builder $draft) use ($user) {
                $draft->where('status', $this->procurementStatus('draft'))
                    ->where('requested_by', $user->id);
            });

            // For procurement workflow assignments, current_handler_id is the single source of truth.
            $q->orWhere('current_handler_id', $user->id);

            if ($roles->intersect(['record-officer', 'records-office', 'record-office', 'secretory'])->isNotEmpty()) {
                $q->orWhere('status', $this->procurementStatus('records_processing'));
            }

            if ($roles->intersect(['accountant', 'finance-accountant', 'finance'])->isNotEmpty()) {
                $q->orWhere('status', $this->procurementStatus('sent_to_finance'));
            }
        });
    }

    private function normalizedRoles(User $user): Collection
    {
        $roles = collect();

        if (method_exists($user, 'getRoleNames')) {
            $roles = $roles->merge($user->getRoleNames());
        }

        if ($user->relationLoaded('roles')) {
            $roles = $roles->merge($user->roles->pluck('name'));
        }

        if (isset($user->role) && $user->role) {
            $roles->push($user->role);
        }

        return $roles
            ->filter()
            ->map(fn ($role) => $this->normalizeRole((string) $role))
            ->unique()
            ->values();
    }

    private function normalizeRole(string $role): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', str_replace('&', 'and', strtolower($role)))), '-');
    }

    private function paymentStatus(string $name): string
    {
        $constant = PaymentRequest::class . '::STATUS_' . strtoupper($name);

        return defined($constant) ? constant($constant) : $name;
    }

    private function procurementStatus(string $name): string
    {
        $constant = ProcurementRequest::class . '::STATUS_' . strtoupper($name);

        return defined($constant) ? constant($constant) : $name;
    }
}
