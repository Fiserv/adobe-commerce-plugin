<?php
/**
 * Copyright © Fiserv, Inc. All rights reserved.
 */
namespace Fiserv\Payments\Helper\PayByLink;

/**
 * Static helper for Pay By Link expiry time conversions.
 *
 * Centralizes the days/hours/minutes math and array-extraction logic that was
 * previously duplicated across observers, blocks, controllers and config models.
 */
class ExpiryTimeConverter
{
    /**
     * Minutes in a day.
     */
    const MINUTES_PER_DAY = 1440;

    /**
     * Seconds in a day.
     */
    const SECONDS_PER_DAY = 86400;

    /**
     * Recursively unwrap a (possibly nested) array down to its first scalar value.
     *
     * Handles form data passed as arrays: [0], [[0]], ["0"], etc.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function extractScalar($value)
    {
        while (is_array($value) && !empty($value)) {
            $value = reset($value);
        }
        return $value;
    }

    /**
     * Convert days/hours/minutes parts to a total number of minutes.
     *
     * @param int|string|array $days
     * @param int|string|array $hours
     * @param int|string|array $minutes
     * @return int
     */
    public static function partsToMinutes($days, $hours, $minutes): int
    {
        $days = (int) self::extractScalar($days);
        $hours = (int) self::extractScalar($hours);
        $minutes = (int) self::extractScalar($minutes);

        return ($days * self::MINUTES_PER_DAY) + ($hours * 60) + $minutes;
    }

    /**
     * Convert days/hours/minutes parts to a total number of seconds.
     *
     * @param int|string|array $days
     * @param int|string|array $hours
     * @param int|string|array $minutes
     * @return int
     */
    public static function partsToSeconds($days, $hours, $minutes): int
    {
        return self::partsToMinutes($days, $hours, $minutes) * 60;
    }

    /**
     * Normalize an expiry config array into [days, hours, minutes] integers.
     *
     * @param array|null $expiryData
     * @param array $defaults Optional defaults keyed by days/hours/minutes.
     * @return array{days:int, hours:int, minutes:int}
     */
    public static function configToParts($expiryData, array $defaults = []): array
    {
        $expiryData = is_array($expiryData) ? $expiryData : [];

        return [
            'days' => isset($expiryData['days'])
                ? (int) self::extractScalar($expiryData['days'])
                : (int) ($defaults['days'] ?? 0),
            'hours' => isset($expiryData['hours'])
                ? (int) self::extractScalar($expiryData['hours'])
                : (int) ($defaults['hours'] ?? 0),
            'minutes' => isset($expiryData['minutes'])
                ? (int) self::extractScalar($expiryData['minutes'])
                : (int) ($defaults['minutes'] ?? 0),
        ];
    }

    /**
     * Convert an expiry config array directly to total minutes.
     *
     * @param array|null $expiryData
     * @param array $defaults
     * @return int
     */
    public static function configToMinutes($expiryData, array $defaults = []): int
    {
        $parts = self::configToParts($expiryData, $defaults);
        return self::partsToMinutes($parts['days'], $parts['hours'], $parts['minutes']);
    }

    /**
     * Convert an expiry config array directly to total seconds.
     *
     * @param array|null $expiryData
     * @param array $defaults
     * @return int
     */
    public static function configToSeconds($expiryData, array $defaults = []): int
    {
        return self::configToMinutes($expiryData, $defaults) * 60;
    }
}
