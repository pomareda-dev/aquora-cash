<?php

namespace App\Models;

use App\Models\Scopes\LiveScope;
use Database\Factories\MovementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property string $description
 * @property int|null $category_id
 * @property string $amount
 * @property string $source
 * @property int|null $recurring_id
 * @property int|null $debt_id
 * @property string|null $notes
 * @property bool $is_sandbox
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Movement extends Model
{
    /** @use HasFactory<MovementFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'description',
        'category_id',
        'amount',
        'source',
        'recurring_id',
        'debt_id',
        'notes',
        'is_projected',
        'is_sandbox',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'is_projected' => 'boolean',
            'is_sandbox' => 'boolean',
            'sort_order' => 'integer',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_id');
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function scopeForMonth(Builder $query, Carbon $month): void
    {
        $query->whereBetween('date', [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    public function scopeActual(Builder $query): void
    {
        $query->where('date', '<=', now()->toDateString());
    }

    public function scopeProjected(Builder $query): void
    {
        $query->where('date', '>', now()->toDateString());
    }

    /**
     * Opening balance before a month: the sum of all real movements
     * (is_projected=false) dated before the month start, regardless of source.
     */
    public static function openingBalance(Carbon $monthStart, int $userId): string
    {
        $sum = static::where('user_id', $userId)
            ->where('date', '<', $monthStart->toDateString())
            ->where('is_projected', false)
            ->sum('amount');

        return number_format((float) ($sum ?? 0), 2, '.', '');
    }

    public static function nextSortOrder(int $userId, string $date, bool $isProjected): int
    {
        return (int) static::where('user_id', $userId)
            ->where('date', $date)
            ->where('is_projected', $isProjected)
            ->max('sort_order') + 1;
    }

    public static function nextSandboxSortOrder(int $userId, string $date, bool $isProjected): int
    {
        return (int) static::withoutSandboxScope()
            ->where('user_id', $userId)
            ->where('date', $date)
            ->where('is_sandbox', true)
            ->where('is_projected', $isProjected)
            ->max('sort_order') + 1;
    }

    /**
     * Real balance up to today: the sum of all real movements
     * (is_projected=false) with date <= today, regardless of source.
     * Recurring-generated rows count once they are marked as real.
     */
    public static function realBalance(int $userId): string
    {
        $sum = static::where('user_id', $userId)
            ->where('date', '<=', now()->toDateString())
            ->where('is_projected', false)
            ->sum('amount');

        return number_format((float) ($sum ?? 0), 2, '.', '');
    }
}
