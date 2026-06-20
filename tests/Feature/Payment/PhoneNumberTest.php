<?php

namespace Tests\Feature\Payment;

use App\Helpers\PhoneNumber;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    // =========================================================================
    // normalize()
    // =========================================================================

    public function test_normalizes_07_prefix_to_254(): void
    {
        $this->assertSame('254712345678', PhoneNumber::normalize('0712345678'));
    }

    public function test_normalizes_01_prefix_to_254(): void
    {
        $this->assertSame('254112345678', PhoneNumber::normalize('0112345678'));
    }

    public function test_leaves_254_prefix_unchanged(): void
    {
        $this->assertSame('254712345678', PhoneNumber::normalize('254712345678'));
    }

    public function test_strips_leading_plus(): void
    {
        $this->assertSame('254712345678', PhoneNumber::normalize('+254712345678'));
    }

    public function test_strips_spaces(): void
    {
        $this->assertSame('254712345678', PhoneNumber::normalize('0712 345 678'));
    }

    public function test_strips_dashes(): void
    {
        $this->assertSame('254712345678', PhoneNumber::normalize('+254-712-345-678'));
    }

    public function test_strips_parentheses(): void
    {
        $this->assertSame('254712345678', PhoneNumber::normalize('(0712) 345-678'));
    }

    public function test_strips_mixed_separators(): void
    {
        $this->assertSame('254712345678', PhoneNumber::normalize('+254 712.345.678'));
    }

    public function test_throws_on_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhoneNumber::normalize('');
    }

    public function test_throws_on_too_short_number(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhoneNumber::normalize('07123');
    }

    public function test_throws_on_non_kenyan_country_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhoneNumber::normalize('+447911123456'); // UK number — 11 digits after stripping +
    }

    public function test_throws_on_letters_only(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhoneNumber::normalize('not-a-phone');
    }

    // =========================================================================
    // toE164() — alias
    // =========================================================================

    public function test_to_e164_is_alias_for_normalize(): void
    {
        $this->assertSame(
            PhoneNumber::normalize('0712345678'),
            PhoneNumber::toE164('0712345678'),
        );
    }

    // =========================================================================
    // isValid()
    // =========================================================================

    public function test_is_valid_returns_true_for_valid_numbers(): void
    {
        $this->assertTrue(PhoneNumber::isValid('0712345678'));
        $this->assertTrue(PhoneNumber::isValid('+254712345678'));
        $this->assertTrue(PhoneNumber::isValid('254712345678'));
        $this->assertTrue(PhoneNumber::isValid('0112 345 678'));
    }

    public function test_is_valid_returns_false_for_invalid_numbers(): void
    {
        $this->assertFalse(PhoneNumber::isValid(''));
        $this->assertFalse(PhoneNumber::isValid('07123'));
        $this->assertFalse(PhoneNumber::isValid('not-a-number'));
        $this->assertFalse(PhoneNumber::isValid('+447911123456'));
    }

    // =========================================================================
    // isKenyanMobile()
    // =========================================================================

    public function test_recognizes_safaricom_prefixes(): void
    {
        $this->assertTrue(PhoneNumber::isKenyanMobile('0712345678')); // 712 — Safaricom
        $this->assertTrue(PhoneNumber::isKenyanMobile('0722345678')); // 722 — Safaricom
        $this->assertTrue(PhoneNumber::isKenyanMobile('0700345678')); // 700 — Safaricom
    }

    public function test_recognizes_safaricom_01x_prefixes(): void
    {
        $this->assertTrue(PhoneNumber::isKenyanMobile('0110345678')); // 110 — Safaricom
        $this->assertTrue(PhoneNumber::isKenyanMobile('0111345678')); // 111 — Safaricom
    }

    public function test_recognizes_airtel_prefixes(): void
    {
        $this->assertTrue(PhoneNumber::isKenyanMobile('0733345678')); // 733 — Airtel
        $this->assertTrue(PhoneNumber::isKenyanMobile('0780345678')); // 780 — Airtel
        $this->assertTrue(PhoneNumber::isKenyanMobile('0750345678')); // 750 — Airtel
    }

    public function test_recognizes_telkom_prefixes(): void
    {
        $this->assertTrue(PhoneNumber::isKenyanMobile('0770345678')); // 770 — Telkom
        $this->assertTrue(PhoneNumber::isKenyanMobile('0777345678')); // 777 — Telkom
    }

    public function test_rejects_unknown_prefix(): void
    {
        $this->assertFalse(PhoneNumber::isKenyanMobile('0600000000')); // not a recognised mobile prefix
    }

    // =========================================================================
    // mask()
    // =========================================================================

    public function test_masks_middle_digits(): void
    {
        $masked = PhoneNumber::mask('0712345678');
        // 254712345678 → 2547 + 4 stars + 5678
        $this->assertSame('2547****5678', $masked);
        $this->assertStringNotContainsString('2345', $masked);
    }

    public function test_mask_on_invalid_input_does_not_throw(): void
    {
        // Should not throw — gracefully masks whatever digits are present
        $masked = PhoneNumber::mask('12345');
        $this->assertIsString($masked);
    }
}
