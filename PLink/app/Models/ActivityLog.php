<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'activity_log_id';
    protected $fillable = [
        'user_id', 'student_id', 'action', 'description', 'module',
        'metadata', 'ip_address', 'user_agent'
    ];
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'log_type',
    ];

    public const LOG_TYPES = [
        'redemption' => 'redemption',
        'user'       => 'user',
        'collection' => 'collection',
        'system'     => 'system',
    ];

    private const REDEMPTION_ACTIONS = [
        'REDEEM_REWARD',
        'POINTS_DEDUCTED',
    ];

    private const REDEMPTION_MODULES = [
        'Redemptions',
    ];

    private const USER_ACTIONS = [
        'CREATE_USER',
        'UPDATE_USER',
        'DELETE_USER',
        'CREATE_STUDENT',
        'UPDATE_STUDENT',
        'DELETE_STUDENT',
        'PASSWORD_UPDATED',
        'PASSWORD_CHANGED',
        'BULK_IMPORT_STUDENTS',
        'ASSIGN_RFID_CARD',
    ];

    private const USER_MODULES = [
        'Users',
    ];

    private const COLLECTION_ACTIONS = [
        'COLLECTION_SCHEDULED',
        'UPDATE_COLLECTION',
        'DELETE_COLLECTION',
        'PLASTIC_SCANNED',
        'RECYCLING_SESSION_CLAIMED',
        'POINTS_ADDED',
    ];

    private const COLLECTION_MODULES = [
        'Collections',
        'Collection',
    ];

    private const SYSTEM_ACTIONS = [
        'ADD_REWARD',
        'UPDATE_REWARD',
        'DELETE_REWARD',
        'RESTOCK_INVENTORY',
        'UPDATE_INVENTORY_QTY',
        'ADD_MACHINE',
        'UPDATE_MACHINE',
        'DELETE_MACHINE',
    ];

    private const SYSTEM_MODULES = [
        'Rewards',
        'Inventory',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id', 'student_id');
    }

    public function scopeNewestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeForLogType(Builder $query, string $logType): Builder
    {
        $normalized = strtolower(trim($logType));
        if (!in_array($normalized, array_values(self::LOG_TYPES), true)) {
            return $query->whereRaw('1 = 0');
        }

        $actionGroups = [];
        $moduleGroups = [];

        switch ($normalized) {
            case self::LOG_TYPES['redemption']:
                $actionGroups = self::REDEMPTION_ACTIONS;
                $moduleGroups = self::REDEMPTION_MODULES;
                break;
            case self::LOG_TYPES['user']:
                $actionGroups = self::USER_ACTIONS;
                $moduleGroups = self::USER_MODULES;
                break;
            case self::LOG_TYPES['collection']:
                $actionGroups = self::COLLECTION_ACTIONS;
                $moduleGroups = self::COLLECTION_MODULES;
                break;
            case self::LOG_TYPES['system']:
                return $this->applySystemScope($query);
        }

        return $query->where(function (Builder $q) use ($actionGroups, $moduleGroups) {
            if (!empty($actionGroups)) {
                $q->whereIn('action', $actionGroups);
            }
            if (!empty($moduleGroups)) {
                $q->orWhereIn('module', $moduleGroups);
            }
        });
    }

    private function applySystemScope(Builder $query): Builder
    {
        $systemActions = self::SYSTEM_ACTIONS;
        $systemModules = self::SYSTEM_MODULES;
        $nonSystemActions = array_merge(
            self::REDEMPTION_ACTIONS,
            self::USER_ACTIONS,
            self::COLLECTION_ACTIONS
        );
        $nonSystemModules = array_merge(
            self::REDEMPTION_MODULES,
            self::USER_MODULES,
            self::COLLECTION_MODULES
        );

        return $query->where(function (Builder $q) use (
            $systemActions,
            $systemModules,
            $nonSystemActions,
            $nonSystemModules
        ) {
            $q->whereIn('action', $systemActions)
              ->orWhereIn('module', $systemModules)
              ->orWhere(function (Builder $inner) use ($nonSystemActions, $nonSystemModules) {
                  $inner->whereNotIn('action', $nonSystemActions)
                        ->whereNotIn('module', $nonSystemModules);
              });
        });
    }

    public static function categorize(string $action, string $module): string
    {
        $actionUpper = strtoupper(trim((string) $action));
        $moduleTrim  = trim((string) $module);

        if (in_array($actionUpper, self::REDEMPTION_ACTIONS, true)
            || in_array($moduleTrim, self::REDEMPTION_MODULES, true)) {
            return self::LOG_TYPES['redemption'];
        }

        if (in_array($actionUpper, self::USER_ACTIONS, true)
            || in_array($moduleTrim, self::USER_MODULES, true)) {
            return self::LOG_TYPES['user'];
        }

        if (in_array($actionUpper, self::COLLECTION_ACTIONS, true)
            || in_array($moduleTrim, self::COLLECTION_MODULES, true)) {
            return self::LOG_TYPES['collection'];
        }

        if (in_array($actionUpper, self::SYSTEM_ACTIONS, true)
            || in_array($moduleTrim, self::SYSTEM_MODULES, true)) {
            return self::LOG_TYPES['system'];
        }

        return self::LOG_TYPES['system'];
    }

    public function getLogTypeAttribute(): string
    {
        return self::categorize(
            (string) $this->getAttribute('action'),
            (string) $this->getAttribute('module')
        );
    }

    public function getActorAttribute()
    {
        if ($this->user) {
            return $this->user->name ?? 'Admin User';
        }
        if ($this->student) {
            return trim(($this->student->first_name ?? '') . ' ' . ($this->student->last_name ?? '')) ?: 'Student ' . $this->student_id;
        }
        return 'System';
    }

    public function getActorTypeAttribute()
    {
        if ($this->user_id) return 'Admin';
        if ($this->student_id) return 'Student';
        return 'System';
    }

    public static function record(
        string $action,
        string $description,
        string $module,
        ?int $userId = null,
        ?int $studentId = null,
        ?array $metadata = null
    ): self {
        $request = request();
        return self::create([
            'user_id'     => $userId,
            'student_id'  => $studentId,
            'action'      => $action,
            'description' => $description,
            'module'      => $module,
            'metadata'    => $metadata,
            'ip_address'  => $request?->ip(),
            'user_agent'  => $request?->userAgent(),
        ]);
    }
}
