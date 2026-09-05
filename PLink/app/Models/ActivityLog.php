<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
