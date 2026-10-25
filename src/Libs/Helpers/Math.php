<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use TrayDigita\WP\Stack\Source\Exceptions\DivisionByZeroError;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use TrayDigita\WP\Stack\Source\Exceptions\OverflowException;
use function chr;
use function explode;
use function function_exists;
use function intdiv;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function ltrim;
use function max;
use function ord;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_pad;
use function str_repeat;
use function str_replace;
use function strcmp;
use function strlen;
use function strval;
use function substr;
use function trim;
use const PHP_INT_MAX;
use const PHP_INT_SIZE;
use const STR_PAD_LEFT;

final class Math
{
    /**
     * Compare two numeric values.
     *
     *
     * - $a is less than $b: -1,
     * - $a is equal to $b: 0,
     * - $a is greater than $b: 1
     * - false if either value is not numeric.
     *
     * @param numeric $a
     * @param numeric $b
     * @return int|false
     */
    public static function compare(int|float|string $a, int|float|string $b): int|false
    {
        if (is_int($a) && (is_int($b) || is_float($b))) {
            return $a <=> $b;
        }

        if (is_float($a) && (is_int($b) || is_float($b))) {
            return $a <=> $b;
        }

        if (!is_numeric($a) || !is_numeric($b)) {
            return false;
        }

        $a = self::number($a);
        $b = self::number($b);

        if ($a === null || $b === null) {
            return false;
        }

        if ($a === $b) {
            return 0;
        }

        $a_split = explode('.', $a, 2);
        $b_split = explode('.', $b, 2);

        $a_integer = $a_split[0] ?: '0';
        $b_integer = $b_split[0] ?: '0';

        $a_floating_point = rtrim(
            $a_split[1] ?? '0',
            '0'
        ) ?: '0';

        $b_floating_point = rtrim(
            $b_split[1] ?? '0',
            '0'
        ) ?: '0';

        $a_is_negative =
            str_starts_with($a_integer, '-')
            && (
                $a_floating_point !== '0'
                || ltrim($a_integer, '-0') !== ''
            );

        $b_is_negative =
            str_starts_with($b_integer, '-')
            && (
                $b_floating_point !== '0'
                || ltrim($b_integer, '-0') !== ''
            );

        if ($a_is_negative && !$b_is_negative) {
            return -1;
        }

        if (!$a_is_negative && $b_is_negative) {
            return 1;
        }

        $is_negated = $a_is_negative && $b_is_negative;

        if ($a_integer[0] === '-') {
            $a_integer = substr($a_integer, 1);
        }

        if ($b_integer[0] === '-') {
            $b_integer = substr($b_integer, 1);
        }

        $a_integer = ltrim($a_integer, '0') ?: '0';
        $b_integer = ltrim($b_integer, '0') ?: '0';

        if ($a_integer === $b_integer) {
            /**
             * @var numeric-string&non-empty-string $a_floating_point
             * @var numeric-string&non-empty-string $b_floating_point
             */
            return self::compareNumberLoop(
                $a_floating_point,
                $b_floating_point,
                $is_negated
            );
        }

        /**
         * @var numeric-string&non-empty-string $a_integer
         * @var numeric-string&non-empty-string $b_integer
         */
        return self::compareNumberLoop(
            $a_integer,
            $b_integer,
            $is_negated
        );
    }

    /**
     * Normalize a numeric value and convert scientific notation
     * into standard decimal notation.
     *
     * @param numeric $number
     * @return ?numeric-string
     */
    public static function number(int|float|string $number): ?string
    {
        if (is_string($number)) {
            $number = trim($number);
        }

        if (!is_numeric($number)) {
            return null;
        }

        $number = str_replace(
            'E',
            'e',
            strval($number)
        );

        if (!str_contains($number, 'e')) {
            return self::normalizeDecimal($number);
        }

        [$mantissa, $exponent] = explode('e', $number, 2);
        $negative = false;
        if (isset($mantissa[0]) && ($mantissa[0] === '-' || $mantissa[0] === '+')) {
            $negative = $mantissa[0] === '-';
            $mantissa = substr($mantissa, 1);
        }

        $exponent_negative = false;

        if (isset($exponent[0])
            && ($exponent[0] === '-' || $exponent[0] === '+')
        ) {
            $exponent_negative = $exponent[0] === '-';
            $exponent = substr($exponent, 1);
        }

        $exponent = ltrim($exponent, '0') ?: '0';

        /*
         * Prevent conversion to int when the exponent cannot fit.
         */
        if (strlen($exponent) > strlen((string)PHP_INT_MAX)
            || (
                strlen($exponent) === strlen((string)PHP_INT_MAX)
                && strcmp($exponent, (string)PHP_INT_MAX) > 0
            )
        ) {
            throw new OverflowException(
                sprintf(
                    'Exponent "%s" is too large',
                    $exponent
                )
            );
        }

        $exponent = (int)$exponent;

        $mantissa_split = explode('.', $mantissa, 2);

        $integer_part = $mantissa_split[0] ?? '0';
        $fraction_part = $mantissa_split[1] ?? '';

        $digits = $integer_part . $fraction_part;

        /*
         * Remove insignificant leading zeroes from the digit stream.
         * Keep one zero so the number never becomes an empty string.
         */
        $digits = ltrim($digits, '0') ?: '0';

        /*
         * Original decimal position is the number of digits before
         * the decimal point.
         */
        $decimal_position = strlen($integer_part);

        if ($exponent_negative) {
            $decimal_position -= $exponent;
        } else {
            $decimal_position += $exponent;
        }

        $digits_length = strlen($digits);

        if ($digits === '0') {
            return '0';
        }

        if ($decimal_position <= 0) {
            $result =
                '0.'
                . str_repeat('0', -$decimal_position)
                . $digits;
        } elseif ($decimal_position >= $digits_length) {
            $result =
                $digits
                . str_repeat(
                    '0',
                    $decimal_position - $digits_length
                );
        } else {
            $result =
                substr($digits, 0, $decimal_position)
                . '.'
                . substr($digits, $decimal_position);
        }

        $result = self::normalizeDecimal($result);

        if ($result === '0') {
            return '0';
        }
        /**
         * @var numeric-string $result
         */
        $result = $negative
            ? '-' . $result
            : $result;
        return $result;
    }

    /**
     * Multiply two arbitrary-precision decimal numbers.
     *
     * Supports:
     * - integers
     * - decimal numbers
     * - negative numbers
     * - scientific notation
     *
     * @param numeric $num_1
     * @param numeric $num_2
     * @return numeric-string
     */
    public static function multiply(int|float|string $num_1, int|float|string $num_2): string
    {
        if (is_int($num_1)
            && is_int($num_2)
            && function_exists('gmp_mul')
            && function_exists('gmp_strval')
        ) {
            /**
             * GMP is only used when both operands are actual PHP integers.
             *
             * Do not pass arbitrary strings/floats to GMP here because this
             * method also supports arbitrary-precision decimal strings.
             * @var numeric-string $result
             */
            $result = \gmp_strval(\gmp_mul($num_1, $num_2));
            return $result;
        }

        $a = self::number($num_1);
        $b = self::number($num_2);
        if ($a === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 1: %s',
                    $num_1
                )
            );
        }
        if ($b === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 2: %s',
                    $num_2
                )
            );
        }

        /*
         * BCMath can handle arbitrary precision decimals directly.
         */
        if (function_exists('bcmul')) {
            /**
             * @var numeric-string $result
             */
            $result = \bcmul($a, $b);
            return $result;
        }

        [$a_digits, $a_scale, $negativeA] =
            self::decimalParts($a);

        [$b_digits, $b_scale, $negativeB] =
            self::decimalParts($b);

        if ($a_digits === '0' || $b_digits === '0') {
            return '0';
        }

        /**
         * @var numeric-string $b_digits
         * @var numeric-string $a_digits
         */
        $result = self::multiplyUnsigned($a_digits, $b_digits);
        $scale = $a_scale + $b_scale;
        if ($scale > 0) {
            $length = strlen($result);

            if ($length <= $scale) {
                $result =
                    '0.'
                    . str_repeat('0', $scale - $length)
                    . $result;
            } else {
                $result =
                    substr($result, 0, $length - $scale)
                    . '.'
                    . substr($result, $length - $scale);
            }

            $result = self::normalizeDecimal($result);
        }

        if ($negativeA !== $negativeB && $result !== '0') {
            $result = '-' . $result;
        }
        /**
         * @var numeric-string $result
         */
        return $result;
    }

    /**
     * Add two arbitrary-precision decimal numbers.
     *
     * Supports:
     * - integers
     * - decimal numbers
     * - negative numbers
     * - scientific notation
     *
     * No floating-point arithmetic is used for the actual calculation.
     *
     * @param numeric $num_1
     * @param numeric $num_2
     * @return numeric-string
     */
    public static function sum(int|float|string $num_1, int|float|string $num_2): string
    {
        /*
         * GMP is only used when both operands are actual PHP integers.
         *
         * Do not pass arbitrary strings/floats to GMP here because this
         * method also supports arbitrary-precision decimal strings.
         */
        if (is_int($num_1)
            && is_int($num_2)
            && function_exists('gmp_add')
            && function_exists('gmp_strval')
        ) {
            /**
             * @var numeric-string $result
             */
            $result = \gmp_strval(\gmp_add($num_1, $num_2));
            return $result;
        }

        $a = self::number($num_1);
        $b = self::number($num_2);

        if ($a === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 1: %s',
                    $num_1
                )
            );
        }

        if ($b === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 2: %s',
                    $num_2
                )
            );
        }

        /*
         * BCMath already handles arbitrary-precision decimal addition.
         */
        if (function_exists('bcadd')) {
            /**
             * @var numeric-string $result
             */
            $result = \bcadd($a, $b);
            return $result;
        }

        /*
         * Convert both values into:
         *
         *   unsigned digits
         *   decimal scale
         *   sign
         *
         * Example:
         *
         *   -123.4500
         *
         * becomes:
         *
         *   digits = 12345
         *   scale  = 2
         *   negative = true
         */
        [$a_digits, $a_scale, $negativeA] = self::decimalParts($a);
        [$b_digits, $b_scale, $negativeB] = self::decimalParts($b);

        /*
         * Zero is always unsigned.
         */
        if ($a_digits === '0') {
            /**
             * @var numeric-string $result
             * @var numeric-string $b_digits
             */
            $result = $negativeB
                ? '-' . self::formatDecimal(
                    $b_digits,
                    $b_scale
                )
                : self::formatDecimal(
                    $b_digits,
                    $b_scale
                );
            return $result;
        }

        if ($b_digits === '0') {
            /**
             * @var numeric-string $result
             * @var numeric-string $a_digits
             */
            $result = $negativeA
                ? '-' . self::formatDecimal(
                    $a_digits,
                    $a_scale
                )
                : self::formatDecimal(
                    $a_digits,
                    $a_scale
                );
            return $result;
        }

        /*
         * Align both numbers to the same decimal scale.
         *
         * Example:
         *
         *   12.34   -> 1234, scale 2
         *   0.567   -> 567,  scale 3
         *
         * becomes:
         *
         *   12340
         *   567
         *
         * both with scale = 3.
         */
        $scale = max($a_scale, $b_scale);

        if ($a_scale < $scale) {
            $a_digits .= str_repeat(
                '0',
                $scale - $a_scale
            );
        }

        if ($b_scale < $scale) {
            $b_digits .= str_repeat(
                '0',
                $scale - $b_scale
            );
        }

        /*
         * Same sign:
         *
         *   (+a) + (+b)
         *   (-a) + (-b)
         *
         * is simply unsigned addition.
         */
        if ($negativeA === $negativeB) {
            /**
             * @var numeric-string $result
             * @var numeric-string $a_digits
             * @var numeric-string $b_digits
             */
            $result = self::addUnsigned(
                $a_digits,
                $b_digits
            );

            $result = self::formatDecimal(
                $result,
                $scale
            );

            if ($negativeA && $result !== '0') {
                $result = '-' . $result;
            }
            /**
             * @var numeric-string $result
             */
            return $result;
        }

        /**
         * Different signs:
         *
         *   (+a) + (-b) = a - b
         *   (-a) + (+b) = b - a
         * @var numeric-string $a_digits
         * @var numeric-string $b_digits
         */
        $comparison = self::compareNumberLoop(
            $a_digits,
            $b_digits,
            false
        );

        /*
         * Equal magnitudes cancel each other.
         */
        if ($comparison === 0) {
            return '0';
        }

        if ($comparison > 0) {
            /**
             * @var numeric-string $result
             * @var numeric-string $a_digits
             * @var numeric-string $b_digits
             *
             * |a| > |b|
             *
             * Result takes A's sign.
             */
            $result = self::subtractUnsigned(
                $a_digits,
                $b_digits
            );

            $result = self::formatDecimal(
                $result,
                $scale
            );

            if ($negativeA && $result !== '0') {
                $result = '-' . $result;
            }
            /**
             * @var numeric-string $result
             */
            return $result;
        }

        /*
         * |b| > |a|
         *
         * Result takes B's sign.
         */
        $result = self::subtractUnsigned(
            $b_digits,
            $a_digits
        );

        $result = self::formatDecimal(
            $result,
            $scale
        );

        if ($negativeB && $result !== '0') {
            $result = '-' . $result;
        }
        /**
         * @var numeric-string $result
         */
        return $result;
    }

    /**
     * Subtract two arbitrary-precision decimal numbers.
     *
     * Supports:
     * - integers
     * - decimal numbers
     * - negative numbers
     * - scientific notation
     *
     * No floating-point arithmetic is used for the actual calculation.
     *
     * @param numeric $num_1
     * @param numeric $num_2
     * @return numeric-string
     */
    public static function subtract(
        int|float|string $num_1,
        int|float|string $num_2
    ): string {
        $b = self::number($num_2);
        if ($b === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 2: %s',
                    $num_2
                )
            );
        }

        if ($b === '0') {
            return self::number($num_1) ?? throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 1: %s',
                    $num_1
                )
            );
        }
        /*
         * Negate the second number and add it to the first.
         */
        $b = str_starts_with($b, '-')
            ? substr($b, 1)
            : '-' . $b;
        /**
         * @var numeric-string $num_1
         * @var numeric-string $b
         */
        return self::sum($num_1, $b);
    }

    /**
     * Divide two arbitrary-precision decimal numbers.
     *
     * The result is rounded/truncated to the requested decimal scale.
     *
     * @param numeric $num_1
     * @param numeric $num_2
     * @param int $scale Number of digits after decimal point.
     * @return numeric-string
     */
    public static function divide(
        int|float|string $num_1,
        int|float|string $num_2,
        int $scale = 20
    ): string {
        if ($scale < 0) {
            throw new InvalidArgumentException(
                'Division scale cannot be negative'
            );
        }

        $a = self::number($num_1);
        $b = self::number($num_2);

        if ($a === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 1: %s',
                    $num_1
                )
            );
        }

        if ($b === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 2: %s',
                    $num_2
                )
            );
        }

        if ($b === '0') {
            throw new DivisionByZeroError(
                'Division by zero'
            );
        }

        if (function_exists('bcdiv')) {
            return \bcdiv($a, $b, $scale);
        }

        [$aDigits, $aScale, $negativeA] = self::decimalParts($a);
        [$bDigits, $bScale, $negativeB] = self::decimalParts($b);

        if ($aDigits === '0') {
            return '0';
        }

        /*
         * We want:
         *
         *   A / B
         *
         * where:
         *
         *   A = aDigits * 10^-aScale
         *   B = bDigits * 10^-bScale
         *
         * Therefore:
         *
         *   A / B =
         *   aDigits / bDigits * 10^(bScale - aScale)
         */

        /*
         * Generate enough integer digits to represent the requested
         * fractional precision.
         *
         * We shift the dividend by:
         *
         *   scale + aScale - bScale
         *
         * decimal places.
         */
        $shift = $scale + $aScale - $bScale;
        $dividend = '0';
        if ($shift < 0) {
            /*
             * If the shift is negative, reduce the dividend's digits.
             * This case occurs when the divisor has a larger decimal
             * scale than the requested output precision.
             */
            $remove = -$shift;
            if ($remove < strlen($aDigits)) {
                $dividend = substr($aDigits, 0, strlen($aDigits) - $remove);
            }
        } else {
            $dividend = $aDigits . str_repeat('0', $shift);
        }
        $resultScale = $scale;
        /**
         * @var numeric-string $bDigits
         * @var numeric-string $dividend
         */
        [$quotient] = self::divideUnsigned($dividend, $bDigits);
        $result = self::formatDecimal($quotient, $resultScale);

        if ($negativeA !== $negativeB && $result !== '0') {
            $result = '-' . $result;
        }
        /**
         * @var numeric-string $result
         */
        return $result;
    }

    /**
     * Calculate the remainder of two arbitrary-precision decimal numbers.
     *
     * The remainder follows PHP's integer-style truncation semantics:
     *
     *     a % b = a - trunc(a / b) * b
     *
     * Supports:
     * - integers
     * - decimal numbers
     * - negative numbers
     * - scientific notation
     *
     * @param numeric $num_1
     * @param numeric $num_2
     * @return numeric-string
     */
    public static function modulo(int|float|string $num_1, int|float|string $num_2): string
    {
        $a = self::number($num_1);
        $b = self::number($num_2);

        if ($a === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 1: %s',
                    $num_1
                )
            );
        }

        if ($b === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid number on argument 2: %s',
                    $num_2
                )
            );
        }

        if ($b === '0') {
            throw new InvalidArgumentException(
                'Modulo by zero'
            );
        }

        [$aDigits, $aScale, $negativeA] = self::decimalParts($a);

        // [$bDigits, $bScale, $negativeB] = self::decimalParts($b);
        [$bDigits, $bScale] = self::decimalParts($b);

        if ($aDigits === '0') {
            return '0';
        }

        /*
         * Align both numbers to the same decimal scale.
         */
        $scale = max($aScale, $bScale);

        if ($aScale < $scale) {
            $aDigits .= str_repeat(
                '0',
                $scale - $aScale
            );
        }

        if ($bScale < $scale) {
            $bDigits .= str_repeat(
                '0',
                $scale - $bScale
            );
        }

        /**
         * We only need the integer quotient because modulo
         * uses truncation toward zero.
         * @var numeric-string $aDigits
         * @var numeric-string $bDigits
         */
        [$quotient] = self::divideUnsigned(
            $aDigits,
            $bDigits
        );

        if ($quotient === '0') {
            /**
             * If the quotient is zero, the remainder is simply the dividend.
             * The result takes the sign of the dividend.
             * @var numeric-string $result
             */
            $result = $negativeA
                ? '-' . self::formatDecimal($aDigits, $scale)
                : self::formatDecimal($aDigits, $scale);
            return $result;
        }
        /**
         * @var (non-falsy-string&numeric-string) $quotient
         * @var numeric-string $aDigits
         * @var numeric-string $bDigits
         */
        $product = self::multiplyUnsigned($quotient, $bDigits);
        $remainder = self::subtractUnsigned($aDigits, $product);

        $result = self::formatDecimal(
            $remainder,
            $scale
        );

        /**
         * The remainder takes the sign of the dividend.
         */
        if ($negativeA && $result !== '0') {
            $result = '-' . $result;
        }
        /**
         * @var numeric-string $result
         */
        return $result;
    }

    /**
     * Divide two unsigned integers.
     *
     * Returns:
     *
     * [
     *     quotient,
     *     remainder
     * ]
     *
     * @param numeric-string $dividend
     * @param numeric-string $divisor
     * @return array{
     *     0: numeric-string,
     *      1: numeric-string
     * }
     */
    private static function divideUnsigned(
        string $dividend,
        string $divisor
    ): array {
        if ($divisor === '0') {
            throw new DivisionByZeroError(
                'Division by zero'
            );
        }

        $comparison = self::compareNumberLoop(
            $dividend,
            $divisor,
            false
        );

        if ($comparison < 0) {
            return ['0', $dividend];
        }

        $length = strlen($dividend);
        $remainder = '0';
        $quotient = '';

        for ($i = 0; $i < $length; ++$i) {
            $remainder = $remainder === '0'
                ? $dividend[$i]
                : $remainder . $dividend[$i];

            /**
             * @var non-empty-string $remainder
             */
            $remainder = ltrim($remainder, '0') ?: '0';
            /**
             * @var numeric-string $remainder
             */
            $digit = 0;

            /*
             * Find the largest decimal digit that satisfies:
             *
             * divisor * digit <= remainder
             *
             * Since this is one decimal digit, at most 10
             * comparisons are required.
             */
            for ($candidate = 9; $candidate >= 1; --$candidate) {
                $product = self::multiplyUnsigned(
                    $divisor,
                    (string)$candidate
                );

                if (self::compareNumberLoop(
                    $product,
                    $remainder,
                    false
                ) <= 0
                ) {
                    $digit = $candidate;
                    $newRemainder = self::subtractUnsigned(
                        $remainder,
                        $product
                    );
                    $remainder = $newRemainder;
                    break;
                }
            }

            $quotient .= $digit;
        }

        /**
         * Remove leading zeroes from the quotient and remainder.
         * If either is empty, return "0".
         * @var non-empty-string $quotient
         * @var non-empty-string $remainder
         */
        $quotient = ltrim($quotient, '0') ?: '0';
        $remainder = ltrim($remainder, '0') ?: '0';
        /**
         * @var array{0: numeric-string, 1: numeric-string} $result
         */
        $result = [
            $quotient,
            $remainder,
        ];
        return $result;
    }

    /**
     * Compare two unsigned numbers represented as strings.
     *
     * @param numeric-string $a
     * @param numeric-string $b
     * @param bool $is_negated
     * @return int
     */
    private static function compareNumberLoop(string $a, string $b, bool $is_negated): int
    {
        if ($a === $b) {
            return 0;
        }

        $a_length = strlen($a);
        $b_length = strlen($b);

        if ($a_length !== $b_length) {
            $return = $a_length <=> $b_length;

            return $is_negated ? -$return : $return;
        }

        for ($i = 0; $i < $a_length; ++$i) {
            if ($a[$i] === $b[$i]) {
                continue;
            }

            $return = $a[$i] <=> $b[$i];

            return $is_negated ? -$return : $return;
        }

        return 0;
    }

    /**
     * Normalize a standard decimal number.
     *
     * @param string $number
     * @return numeric-string
     */
    private static function normalizeDecimal(string $number): string
    {
        $negative = false;

        if (isset($number[0]) && ($number[0] === '-' || $number[0] === '+')) {
            $negative = $number[0] === '-';
            $number = substr($number, 1);
        }

        $split = explode('.', $number, 2);

        $integer = ltrim($split[0] ?? '0', '0') ?: '0';
        $fraction = rtrim($split[1] ?? '', '0');

        if ($fraction === '') {
            /**
             * @var non-falsy-string $result
             */
            $result = $negative && $integer !== '0'
                ? '-' . $integer
                : $integer;
            /**
             * @var numeric-string $result
             */
            return $result;
        }

        if ($integer === '0' && ltrim($fraction, '0') === '') {
            return '0';
        }

        $result = $integer . '.' . $fraction;

        /**
         * @var non-falsy-string $result
         */
        $result = $negative
            ? '-' . $result
            : $result;

        /**
         * @var numeric-string $result
         */
        return $result;
    }

    /**
     * Pads the left of two unsigned numbers.
     *
     * @return array{string, string, int}
     */
    private static function padNumber(
        string $a,
        string $b
    ): array {
        $x = strlen($a);
        $y = strlen($b);

        if ($x > $y) {
            $b = str_repeat('0', $x - $y) . $b;

            return [$a, $b, $x];
        }

        if ($x < $y) {
            $a = str_repeat('0', $y - $x) . $a;

            return [$a, $b, $y];
        }

        return [$a, $b, $x];
    }

    /**
     * Add two unsigned integers without GMP/BCMath.
     *
     * @param numeric-string $a
     * @param numeric-string $b
     * @return numeric-string
     */
    private static function addUnsigned(
        string $a,
        string $b
    ): string {
        [$a, $b, $length] = self::padNumber($a, $b);

        $maxDigits = PHP_INT_SIZE === 4 ? 9 : 18;

        $carry = 0;
        $result = '';

        for ($i = $length - $maxDigits;; $i -= $maxDigits) {
            $blockLength = $maxDigits;

            if ($i < 0) {
                $blockLength += $i;
                $i = 0;
            }

            $blockA = substr(
                $a,
                $i,
                $blockLength
            );

            $blockB = substr(
                $b,
                $i,
                $blockLength
            );

            $sum = (string)(
                (int)$blockA
                + (int)$blockB
                + $carry
            );

            $sumLength = strlen($sum);

            if ($sumLength > $blockLength) {
                $sum = substr($sum, 1);
                $carry = 1;
            } else {
                if ($sumLength < $blockLength) {
                    $sum = str_repeat(
                        '0',
                        $blockLength - $sumLength
                    ) . $sum;
                }

                $carry = 0;
            }

            $result = $sum . $result;

            if ($i === 0) {
                break;
            }
        }

        if ($carry !== 0) {
            $result = '1' . $result;
        }

        /**
         * @var non-empty-string $result
         */
        $result = ltrim($result, '0') ?: '0';
        /**
         * @var numeric-string $result
         */
        return $result;
    }

    /**
     * Subtract unsigned integers where $a >= $b.
     *
     * @param numeric-string $a
     * @param numeric-string $b
     * @return numeric-string
     */
    private static function subtractUnsigned(
        string $a,
        string $b
    ): string {
        [$a, $b, $length] = self::padNumber($a, $b);

        $borrow = 0;
        $result = '';

        for ($i = $length - 1; $i >= 0; --$i) {
            $digitA = ord($a[$i]) - 48;
            $digitB = ord($b[$i]) - 48;

            $value = $digitA - $digitB - $borrow;

            if ($value < 0) {
                $value += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = chr(48 + $value) . $result;
        }

        /**
         * Remove leading zeroes from the result.
         * Keep one zero so the number never becomes an empty string.
         * @var non-empty-string $result
         */
        $result = ltrim($result, '0') ?: '0';
        /**
         * @var numeric-string $result
         */
        return $result;
    }

    /**
     * Format an unsigned integer digit string using a decimal scale.
     *
     * @param numeric-string $digits
     * @param int $scale
     * @return numeric-string
     */
    private static function formatDecimal(
        string $digits,
        int $scale
    ): string {

        /**
         * @var non-empty-string $digits
         */
        $digits = ltrim($digits, '0') ?: '0';

        if ($digits === '0') {
            return '0';
        }

        /**
         * @var numeric-string $digits
         */
        if ($scale <= 0) {
            return $digits;
        }

        $length = strlen($digits);

        if ($length <= $scale) {
            $result =
                '0.'
                . str_repeat(
                    '0',
                    $scale - $length
                )
                . $digits;
        } else {
            $position = $length - $scale;

            $result =
                substr(
                    $digits,
                    0,
                    $position
                )
                . '.'
                . substr(
                    $digits,
                    $position
                );
        }

        return self::normalizeDecimal($result);
    }

    /**
     * Multiply two unsigned integers.
     *
     * @param numeric-string $a
     * @param numeric-string $b
     * @return numeric-string
     */
    private static function multiplyUnsigned(string $a, string $b): string
    {
        if ($a === '0' || $b === '0') {
            return '0';
        }

        $x = strlen($a);
        $y = strlen($b);

        $maxDigits = PHP_INT_SIZE === 4 ? 9 : 18;
        $maxDigits = intdiv($maxDigits, 2);

        $complement = 10 ** $maxDigits;
        $result = '0';

        for ($i = $x - $maxDigits;; $i -= $maxDigits) {
            $blockALength = $maxDigits;
            if ($i < 0) {
                $blockALength += $i;
                $i = 0;
            }
            $blockA = (int)substr($a, $i, $blockALength);
            $line = '';
            $carry = 0;

            for ($j = $y - $maxDigits;; $j -= $maxDigits) {
                $blockBLength = $maxDigits;

                if ($j < 0) {
                    $blockBLength += $j;
                    $j = 0;
                }

                $blockB = (int)substr($b, $j, $blockBLength);
                $mul = $blockA * $blockB + $carry;
                $value = $mul % $complement;
                $carry = intdiv($mul - $value, $complement);

                $value = str_pad(
                    (string)$value,
                    $maxDigits,
                    '0',
                    STR_PAD_LEFT
                );

                $line = $value . $line;

                if ($j === 0) {
                    break;
                }
            }

            if ($carry !== 0) {
                $line = $carry . $line;
            }

            $line = ltrim($line, '0');

            if ($line !== '') {
                $line .= str_repeat(
                    '0',
                    $x - $blockALength - $i
                );

                /**
                 * @var numeric-string $result
                 * @var numeric-string&non-empty-string $line
                 */
                $result = self::addUnsigned(
                    $result,
                    $line
                );
            }

            if ($i === 0) {
                break;
            }
        }

        /**
         * @var non-empty-string $result
         */
        $result = ltrim($result, '0') ?: '0';
        /**
         * @var numeric-string $result
         */
        return $result;
    }

    /**
     * Split a decimal number into unsigned digits, scale and sign.
     *
     * @param numeric-string $number
     * @return array{string, int, bool}
     */
    private static function decimalParts(string $number): array
    {
        $negative = str_starts_with($number, '-');

        if ($negative) {
            $number = substr($number, 1);
        }

        $split = explode('.', $number, 2);

        $integer = $split[0] ?? '0';
        $fraction = $split[1] ?? '';

        /*
         * Remove insignificant zeroes from the fractional part.
         */
        $fraction = rtrim($fraction, '0');

        /*
         * The integer and fractional digits become one
         * unsigned integer for multiplyUnsigned().
         *
         * Example:
         *
         * 12.3400
         *
         * becomes:
         *
         * digits = 1234
         * scale  = 2
         */
        $digits = ltrim($integer . $fraction, '0');

        if ($digits === '') {
            return ['0', 0, false];
        }

        return [
            $digits,
            strlen($fraction),
            $negative,
        ];
    }
}
