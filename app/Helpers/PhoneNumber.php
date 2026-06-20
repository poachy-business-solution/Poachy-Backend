<?php

namespace App\Helpers;

/**
 * Utility class for normalising and validating Kenyan mobile phone numbers.
 *
 * Handles every format a user might reasonably enter and converts to the
 * E.164 / M-Pesa-required format: 254XXXXXXXXX (12 digits, no +).
 *
 * Supported input formats:
 *   07XXXXXXXX          — local Safaricom/Airtel/Telkom (10 digits)
 *   01XXXXXXXX          — local Airtel/Telkom (10 digits)
 *   +2547XXXXXXXX       — international with +
 *   2547XXXXXXXX        — international without +
 *   0712 345 678        — spaces allowed
 *   +254-712-345-678    — dashes allowed
 *   (0712) 345-678      — parentheses allowed
 */
class PhoneNumber
{
    /**
     * Kenyan mobile network prefixes (3-digit, after leading 0 stripped).
     * Used to verify the number belongs to a known Kenyan mobile operator.
     *
     * @var array<string>
     */
    private const KENYAN_MOBILE_PREFIXES = [
        // Safaricom
        '700', '701', '702', '703', '704', '705', '706', '707', '708', '709',
        '710', '711', '712', '713', '714', '715', '716', '717', '718', '719',
        '720', '721', '722', '723', '724', '725', '726', '727', '728', '729',
        '740', '741', '742', '745', '746', '747', '748',
        '757', '758', '759',
        '768', '769',
        '790', '791', '792', '793', '794', '795', '796', '797', '798', '799',
        '110', '111', '112', '113', '114', '115', '116', '117', '118', '119',
        // Airtel Kenya
        '730', '731', '732', '733', '734', '735', '736', '737', '738', '739',
        '750', '751', '752', '753', '754', '755', '756',
        '762',
        '780', '781', '782', '783', '784', '785', '786', '787', '788', '789',
        // Telkom Kenya
        '770', '771', '772', '773', '774', '775', '776', '777', '778', '779',
    ];

    /**
     * Normalise any Kenyan phone number to E.164 format (254XXXXXXXXX).
     *
     * @throws \InvalidArgumentException if the number cannot be normalised.
     */
    public static function normalize(string $phone): string
    {
        $cleaned = self::strip($phone);

        if ($cleaned === '') {
            throw new \InvalidArgumentException("Phone number '{$phone}' is empty after stripping non-numeric characters.");
        }

        // Resolve local format: 07XXXXXXXX or 01XXXXXXXX (10 digits)
        if (strlen($cleaned) === 10 && in_array($cleaned[0], ['0'], true)) {
            $cleaned = '254' . substr($cleaned, 1);
        }

        // At this point we expect 12-digit 254XXXXXXXXX
        if (strlen($cleaned) !== 12 || ! str_starts_with($cleaned, '254')) {
            throw new \InvalidArgumentException(
                "Phone number '{$phone}' could not be normalised to a valid Kenyan number. "
                . "Expected formats: 07XXXXXXXX, 01XXXXXXXX, +254XXXXXXXXX, 254XXXXXXXXX."
            );
        }

        return $cleaned;
    }

    /**
     * Alias for normalize() — returns E.164 format required by M-Pesa.
     *
     * @throws \InvalidArgumentException
     */
    public static function toE164(string $phone): string
    {
        return self::normalize($phone);
    }

    /**
     * Check whether a phone number can be successfully normalised.
     * Returns false instead of throwing on invalid input.
     */
    public static function isValid(string $phone): bool
    {
        try {
            self::normalize($phone);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Check whether a phone number belongs to a known Kenyan mobile network.
     * Validates the 3-digit prefix after the country code.
     *
     * @throws \InvalidArgumentException if the number cannot be normalised first.
     */
    public static function isKenyanMobile(string $phone): bool
    {
        $e164   = self::normalize($phone);
        $prefix = substr($e164, 3, 3); // characters 4-6 are the network prefix

        return in_array($prefix, self::KENYAN_MOBILE_PREFIXES, true);
    }

    /**
     * Return a masked version of the phone number suitable for logging or display.
     * Example: 254712345678 → 2547***5678
     */
    public static function mask(string $phone): string
    {
        try {
            $e164 = self::normalize($phone);
        } catch (\InvalidArgumentException) {
            // If we can't normalise, mask whatever we have
            $e164 = preg_replace('/\D/', '', $phone);
        }

        if (strlen($e164) <= 6) {
            return str_repeat('*', strlen($e164));
        }

        return substr($e164, 0, 4) . str_repeat('*', strlen($e164) - 8) . substr($e164, -4);
    }

    /**
     * Strip all formatting characters from a phone number string.
     * Handles spaces, dashes, parentheses, dots, and leading +.
     */
    private static function strip(string $phone): string
    {
        // Remove all non-digit characters (spaces, dashes, parens, dots, +)
        return preg_replace('/\D/', '', $phone);
    }
}
