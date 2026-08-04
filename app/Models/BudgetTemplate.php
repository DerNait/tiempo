<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'budget_type',
        'target_minutes',
    ];

    protected function casts(): array
    {
        return [
            'target_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
