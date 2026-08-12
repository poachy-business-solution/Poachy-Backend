<?php

namespace Tests\Feature\Tenant\Product;

use App\Http\Requests\Tenant\Product\StoreProductScaleBarcodeFormatRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class ProductScaleBarcodeFormatRequestTest extends TestCase
{
    use InteractsWithTenantAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantAuthorization();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantAuthorization();
        parent::tearDown();
    }

    public function test_authorize_allows_manage_products_user(): void
    {
        $request = $this->requestAs($this->makeTenantUserWithPermission('manage-products'));

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_denies_user_without_manage_products(): void
    {
        $request = $this->requestAs($this->makeTenantUserWithPermission('view-products'));

        $this->assertFalse($request->authorize());
    }

    public function test_rejects_overlapping_product_code_and_value_segments(): void
    {
        $validator = $this->validatorFor([
            'product_code_start' => 2,
            'product_code_length' => 5,
            'value_start' => 6,
            'value_length' => 5,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('value_start', $validator->errors()->toArray());
    }

    public function test_rejects_segments_that_exceed_barcode_length(): void
    {
        $validator = $this->validatorFor([
            'value_start' => 9,
            'value_length' => 5,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('value_length', $validator->errors()->toArray());
    }

    public function test_rejects_ean13_checksum_for_non_13_length(): void
    {
        $validator = $this->validatorFor([
            'length' => 12,
            'checksum' => 'ean13',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('checksum', $validator->errors()->toArray());
    }

    private function requestAs($user): StoreProductScaleBarcodeFormatRequest
    {
        $request = new StoreProductScaleBarcodeFormatRequest;
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function validatorFor(array $overrides)
    {
        $request = new StoreProductScaleBarcodeFormatRequest;
        $validator = Validator::make(array_merge([
            'name' => 'EAN13 weight scale',
            'prefix' => '21',
            'length' => 13,
            'product_code_start' => 2,
            'product_code_length' => 5,
            'value_start' => 7,
            'value_length' => 5,
            'value_type' => 'weight',
            'decimal_places' => 3,
            'checksum' => 'ean13',
        ], $overrides), $request->rules());
        $request->withValidator($validator);

        return $validator;
    }
}
