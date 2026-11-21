<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Pencatatan log aktivitas pengguna (FR-23, ISO/IEC 27001).
 */
class AuditLogger
{
    public const CREATE = 'CREATE';

    public const UPDATE = 'UPDATE';

    public const SUBMIT = 'SUBMIT';

    public const STATUS_CHANGE = 'STATUS_CHANGE';

    public const LOGIN = 'LOGIN';

    public const LOGOUT = 'LOGOUT';

    public const APPROVE = 'APPROVE';

    public const REMINDER = 'REMINDER';

    /**
     * @param  array<string, mixed>|string|null  $oldValue
     * @param  array<string, mixed>|string|null  $newValue
     */
    public static function record(
        string $action,
        Model|string $entity,
        array|string|null $oldValue = null,
        array|string|null $newValue = null,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_name' => $entity instanceof Model ? $entity->getTable() : $entity,
            'entity_id' => $entity instanceof Model ? $entity->getKey() : null,
            'old_value' => self::encode($oldValue),
            'new_value' => self::encode($newValue),
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
        ]);
    }

    /**
     * Catat perubahan atribut model yang baru disimpan.
     */
    public static function recordChanges(Model $model, string $action = self::UPDATE): ?AuditLog
    {
        $changes = collect($model->getChanges())->except(['updated_at', 'last_saved_at']);

        if ($changes->isEmpty()) {
            return null;
        }

        $previous = $model->getPrevious();
        $original = $changes->keys()->mapWithKeys(fn (string $key): array => [$key => $previous[$key] ?? null]);

        return self::record($action, $model, self::normalize($original->all()), self::normalize($changes->all()));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function normalize(array $values): array
    {
        return array_map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value, $values);
    }

    /**
     * @param  array<string, mixed>|string|null  $value
     */
    private static function encode(array|string|null $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return json_encode(self::normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
