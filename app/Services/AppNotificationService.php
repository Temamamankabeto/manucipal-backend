<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Citizen;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AppNotificationService
{
    public function create(?User $user, ?Citizen $citizen, string $type, string $title, string $message, array $data = [], string $channel = 'in_app'): AppNotification
    {
        return AppNotification::create([
            'user_id' => $user?->id,
            'citizen_id' => $citizen?->id,
            'type' => $type,
            'channel' => $channel,
            'title' => $title,
            'message' => $message,
            'data' => $data ?: null,
            'sent_at' => now(),
        ]);
    }

    public function notifyCitizenStatus(Citizen $citizen, string $type, string $title, string $message): void
    {
        $citizen->loadMissing('registeredBy');
        $this->create($citizen->registeredBy, $citizen, $type, $title, $message, [
            'citizen_id' => $citizen->id,
            'registration_number' => $citizen->registration_number,
            'status' => $citizen->status,
        ]);
    }

    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = AppNotification::query()
            ->with('citizen:id,registration_number,citizen_uid,first_name,middle_name,last_name,status')
            ->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->latest();

        if (($filters['status'] ?? null) === 'unread') {
            $query->whereNull('read_at');
        } elseif (($filters['status'] ?? null) === 'read') {
            $query->whereNotNull('read_at');
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate($perPage);
    }

    public function transformPaginated(LengthAwarePaginator $notifications): array
    {
        return [
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => collect($notifications->items())->map(fn (AppNotification $notification) => $this->transform($notification))->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ];
    }

    public function transform(AppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'channel' => $notification->channel,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'read_at' => optional($notification->read_at)->toISOString(),
            'sent_at' => optional($notification->sent_at)->toISOString(),
            'created_at' => optional($notification->created_at)->toISOString(),
            'citizen' => $notification->citizen,
        ];
    }
}
