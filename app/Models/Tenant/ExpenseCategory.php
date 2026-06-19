<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\ExpenseCategoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ExpenseCategoryObserver::class)]
class ExpenseCategory extends Model
{
    use HasFactory;

    protected $table = 'expense_categories';

    protected $fillable = [
        'name',
        'code',
        'description',
        'parent_id',
        'is_recurring_eligible',
        'requires_receipt',
        'requires_approval',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_recurring_eligible' => 'boolean',
        'requires_receipt' => 'boolean',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * ============================================
     * RELATIONSHIPS
     * ============================================
     */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id')
            ->orderBy('display_order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class, 'category_id');
    }

    /**
     * ============================================
     * SCOPES
     * ============================================
     */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeRecurringEligible($query)
    {
        return $query->where('is_recurring_eligible', true);
    }

    public function scopeOrderedByDisplay($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * ============================================
     * ACCESSORS
     * ============================================
     */

    /**
     * Get full category path (e.g., "Utilities > Electricity")
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Get hierarchy level (0 = root, 1 = first child, etc.)
     */
    public function getLevelAttribute(): int
    {
        $level = 0;
        $parent = $this->parent;

        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }

        return $level;
    }

    /**
     * Check if category has any child categories
     */
    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->count() > 0;
    }

    /**
     * Check if category has any expenses
     */
    public function getHasExpensesAttribute(): bool
    {
        return $this->expenses()->count() > 0;
    }

    /**
     * Check if category is deletable (no expenses, no active budgets)
     */
    public function getIsDeletableAttribute(): bool
    {
        return !$this->has_expenses
            && !$this->budgets()->where('is_active', true)->exists()
            && !$this->has_children;
    }

    /**
     * ============================================
     * METHODS
     * ============================================
     */

    /**
     * Get all ancestor categories (parent, grandparent, etc.)
     */
    public function ancestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;

        while ($parent) {
            $ancestors[] = $parent;
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get all descendant categories (children, grandchildren, etc.)
     */
    public function descendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->descendants());
        }

        return $descendants;
    }

    /**
     * Check if this category is an ancestor of another category
     */
    public function isAncestorOf(ExpenseCategory $category): bool
    {
        $parent = $category->parent;

        while ($parent) {
            if ($parent->id === $this->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }

    /**
     * Check if this category is a descendant of another category
     */
    public function isDescendantOf(ExpenseCategory $category): bool
    {
        return $category->isAncestorOf($this);
    }

    /**
     * Validate that a parent_id doesn't create a circular reference
     */
    public function wouldCreateCircularReference(?int $parentId): bool
    {
        if (!$parentId || $parentId === $this->id) {
            return false;
        }

        $parent = self::find($parentId);

        if (!$parent) {
            return false;
        }

        // Check if the proposed parent is a descendant of this category
        return $this->isAncestorOf($parent);
    }
}
