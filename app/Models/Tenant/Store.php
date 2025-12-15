<?php

namespace App\Models\Tenant;

use App\Observers\Tenant\StoreObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([StoreObserver::class])]
class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'stores';

    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'city',
        'region',
        'phone',
        'email',
        'is_main_store',
        'is_active',
        'manager_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_main_store' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeMainStore($query)
    {
        return $query->where('is_main_store', true);
    }

    public function scopeBranches($query)
    {
        return $query->where('is_main_store', false);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function isMainStore(): bool
    {
        return $this->is_main_store;
    }

    public function isBranch(): bool
    {
        return !$this->is_main_store;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasManager(): bool
    {
        return !is_null($this->manager_id);
    }

    public function activate(): bool
    {
        $this->is_active = true;

        if (Auth::check()) {
            $this->updated_by = Auth::id();
        }

        return $this->save();
    }

    public function deactivate(): bool
    {
        $activeStoresCount = static::where('is_active', true)
            ->where('id', '!=', $this->id)
            ->count();

        if ($activeStoresCount === 0) {
            throw new \RuntimeException(
                'Cannot deactivate the only active store. At least one store must remain active.'
            );
        }

        $this->is_active = false;

        if (Auth::check()) {
            $this->updated_by = Auth::id();
        }

        return $this->save();
    }

    public function setAsMainStore(): bool
    {
        static::where('is_main_store', true)
            ->where('id', '!=', $this->id)
            ->update(['is_main_store' => false]);

        $this->is_main_store = true;

        if (Auth::check()) {
            $this->updated_by = Auth::id();
        }

        return $this->save();
    }

    // ============================================
    // ACCESSORS & MUTATORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    public function getStoreTypeLabelAttribute(): string
    {
        return $this->is_main_store ? 'Main Store' : 'Branch';
    }

    public static function generateUniqueCode(): string
    {
        do {
            // Format: STR-YYYY-XXXXX
            $code = sprintf(
                'STR-%s-%05d',
                now()->format('Y'),
                rand(1, 99999)
            );
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
