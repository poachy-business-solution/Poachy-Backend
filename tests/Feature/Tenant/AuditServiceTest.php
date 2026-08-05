<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenant\ProductBatch;
use App\Models\Tenant\ProductSerial;
use App\Services\Tenant\AuditService;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    // =========================================================================
    // shouldAudit() — config.audit.models registration
    // =========================================================================

    public function test_product_serial_is_audited_on_create(): void
    {
        $this->assertTrue((new AuditService)->shouldAudit(new ProductSerial, 'created'));
    }

    public function test_product_serial_is_audited_on_delete(): void
    {
        $this->assertTrue((new AuditService)->shouldAudit(new ProductSerial, 'deleted'));
    }

    public function test_product_batch_still_audited_on_create(): void
    {
        // Regression guard: ProductSerial's config entry was added alongside the
        // existing ProductBatch one — confirm that wasn't accidentally disturbed.
        $this->assertTrue((new AuditService)->shouldAudit(new ProductBatch, 'created'));
    }
}
