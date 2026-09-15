<?php

namespace App\Models;

use App\Models\Scopes\LiveScope;
use Database\Factories\GoalContributionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $goal_id
 * @property Carbon $date
 * @property string $amount
 * @property string|null $notes
 * @property bool $is_sandbox
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GoalContribution extends Model
{
    /** @use HasFactory<GoalContributionFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'amount',
        'notes',
        'is_sandbox',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'is_sandbox' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(LiveScope::class);
    }

    public static function withoutSandboxScope(): Builder
    {
        return static::query()->withoutGlobalScope(LiveScope::class);
    }

    public function scopeSandbox(Builder $query): void
    {
        $query->withoutGlobalScope(LiveScope::class)
            ->where($query->getModel()->getTable().'.is_sandbox', true);
    }

    // ─── Relationships ────────────────────────────────────────────

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
