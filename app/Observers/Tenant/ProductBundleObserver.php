<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\ProductBundle;
use Illuminate\Support\Facades\Log;

class ProductBundleObserver
{
    public function created(ProductBundle $bundle): void {}

    public function updated(ProductBundle $bundle): void {}

    public function deleted(ProductBundle $bundle): void {}
}
