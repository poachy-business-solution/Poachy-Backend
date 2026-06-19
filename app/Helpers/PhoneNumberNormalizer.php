<?php

namespace App\Helpers;

class PhoneNumberNormalizer
{
    /**
     * Default country code (Kenya)
     */
    private const DEFAULT_COUNTRY_CODE = '254';

    /**
     * Normalize phone number to international format
     * 
     * @param string|null $phone
     * @param string $countryCode Default country code (without +)
     * @return string|null Normalized phone number with + prefix
     */
    public static function normalize(?string $phone, string $countryCode = self::DEFAULT_COUNTRY_CODE): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // Remove any whitespace
        $cleaned = trim($cleaned);

        if (empty($cleaned)) {
            return null;
        }

        // Case 1: Already has + prefix (international format)
        if (str_starts_with($cleaned, '+')) {
            return $cleaned;
        }

        // Case 2: Starts with country code without + (e.g., 254745548093)
        if (str_starts_with($cleaned, $countryCode)) {
            return '+' . $cleaned;
        }

        // Case 3: Starts with 0 (local format) - remove 0 and add country code
        if (str_starts_with($cleaned, '0')) {
            $withoutZero = substr($cleaned, 1);
            return '+' . $countryCode . $withoutZero;
        }

        // Case 4: No prefix at all (e.g., 745548093) - add country code
        return '+' . $countryCode . $cleaned;
    }

    /**
     * Validate if phone number is valid Kenyan format
     * 
     * @param string $phone
     * @return bool
     */
    public static function isValidKenyanNumber(string $phone): bool
    {
        // After normalization, valid Kenyan numbers should be:
        // +254 followed by 9 digits (7xx, 1xx, or 0xx)
        // Total: 13 characters

        $normalized = self::normalize($phone);

        if (!$normalized) {
            return false;
        }

        // Check format: +254 followed by 9 digits
        return (bool) preg_match('/^\+254[017]\d{8}$/', $normalized);
    }

    /**
     * Format phone number for display
     * 
     * @param string|null $phone
     * @param string $format Format: 'international', 'local', 'display'
     * @return string|null
     */
    public static function format(?string $phone, string $format = 'international'): ?string
    {
        $normalized = self::normalize($phone);

        if (!$normalized) {
            return null;
        }

        return match ($format) {
            'local' => self::toLocalFormat($normalized),
            'display' => self::toDisplayFormat($normalized),
            default => $normalized, // international
        };
    }

    /**
     * Convert to local format (0745548093)
     * 
     * @param string $phone
     * @return string
     */
    private static function toLocalFormat(string $phone): string
    {
        // Remove + and country code, add leading 0
        if (str_starts_with($phone, '+' . self::DEFAULT_COUNTRY_CODE)) {
            $withoutCode = substr($phone, strlen('+' . self::DEFAULT_COUNTRY_CODE));
            return '0' . $withoutCode;
        }

        return $phone;
    }

    /**
     * Convert to display format (+254 745 548 093)
     * 
     * @param string $phone
     * @return string
     */
    private static function toDisplayFormat(string $phone): string
    {
        // Format: +254 745 548 093
        if (str_starts_with($phone, '+254')) {
            $number = substr($phone, 4); // Get digits after +254
            return '+254 ' . substr($number, 0, 3) . ' ' . substr($number, 3, 3) . ' ' . substr($number, 6);
        }

        return $phone;
    }

    /**
     * Extract country code from phone number
     * 
     * @param string $phone
     * @return string|null
     */
    public static function extractCountryCode(string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if (!$normalized) {
            return null;
        }

        // Extract digits after + until we hit a common length (1-3 digits)
        if (preg_match('/^\+(\d{1,3})/', $normalized, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if two phone numbers are equivalent
     * 
     * @param string|null $phone1
     * @param string|null $phone2
     * @return bool
     */
    public static function areEquivalent(?string $phone1, ?string $phone2): bool
    {
        if (empty($phone1) || empty($phone2)) {
            return false;
        }

        $normalized1 = self::normalize($phone1);
        $normalized2 = self::normalize($phone2);

        return $normalized1 === $normalized2;
    }
}
