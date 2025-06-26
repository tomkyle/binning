<?php

/**
 * This file is part of tomkyle/binning.
 *
 * Determine optimal number of bins 𝒌 for histogram creation and optimal bin width 𝒉 using various statistical methods.
 */

namespace tomkyle\Binning;

use MathPHP\Statistics\Descriptive;
use MathPHP\Statistics\RandomVariable;

class BinSelection
{
    public const RICE = 'rice';

    public const STURGES = 'sturges';

    public const DOANE = 'doane';

    public const SQUARE_ROOT = 'squareRoot';

    public const PEARSON = 'pearson';

    public const SCOTT = 'scott';

    public const FREEDMAN_DIACONIS = 'freedmanDiaconis';

    public const TERRELL_SCOTT = 'terrellScott';

    public const DEFAULT = 'default';

    /**
     * Suggests a number of bins (𝒌).
     *
     * Per default, the Freedman-Diaconis rule is used. A custom method can be specified
     * using the `$method` parameter.
     *
     * @param array<float|int>    $data    array of numerical data points
     * @param string              $method  the binning method to use (default is Freedman-Diaconis' rule)
     * @param array<string,mixed> ...$args Additional arguments for specific methods.
     *
     * @return int Recommended number of bins (𝒌)
     */
    public static function suggestBins(array $data, string $method = self::DEFAULT, ...$args): int
    {
        return match ($method) {
            self::RICE => self::rice($data),
            self::TERRELL_SCOTT => self::terrellScott($data),
            self::STURGES => self::sturges($data),
            self::DOANE => self::doane($data, ...$args),
            self::SQUARE_ROOT => self::squareRoot($data),
            self::PEARSON => self::squareRoot($data),
            self::SCOTT => (int) self::scott($data)['bins'],
            self::FREEDMAN_DIACONIS => (int) self::freedmanDiaconis($data)['bins'],
            self::DEFAULT => (int) self::freedmanDiaconis($data)['bins'],
            default => throw new \InvalidArgumentException('Unknown binning method: '.$method),
        };
    }

    /**
     * Suggests an optimal bin width (𝒉).
     *
     * Per default, the Freedman-Diaconis rule is used. A custom method can be specified
     * using the `$method` parameter.
     *
     * @param array<float|int>    $data    array of numerical data points
     * @param string              $method  the binning method to use (default is Freedman-Diaconis' rule)
     * @param array<string,mixed> ...$args Additional arguments for specific methods.
     *
     * @return float Recommended bin width (𝒉)
     */
    public static function suggestBinWidth(array $data, string $method = self::DEFAULT, ...$args): float
    {
        return match ($method) {
            self::SCOTT => (float) self::scott($data)['width'],
            self::DEFAULT => (float) self::freedmanDiaconis($data)['width'],
            self::FREEDMAN_DIACONIS => (float) self::freedmanDiaconis($data)['width'],
            default => throw new \InvalidArgumentException('Unknown binning method: '.$method),
        };
    }

    /**
     * Calculates the recommended number of classes (𝒌) according to the “Rice University Rule”.
     *
     * This implementation uses David M. Lane’s formula:
     *
     *    𝒌 = 2 × ⌈ ³√𝑛 ⌉
     *    𝒌 = 2 × ⌈ 𝑛¹′³ ⌉
     *
     * Source:
     * Rubia, J.M.D.L. (2024):
     * Rice University Rule to Determine the Number of Bins.
     * Open Journal of Statistics, 14, 119-149.
     *
     * @param array<float|int> $data array of numerical data points
     *
     * @return int Recommended number of bins (𝒌)
     *
     * @throws \InvalidArgumentException if the dataset is empty
     */
    public static function rice(array $data): int
    {
        $n = count($data);

        if (0 === $n) {
            throw new \InvalidArgumentException('Dataset cannot be empty to apply the Rice Rule.');
        }

        // Rice Rule, as taught by David M. Lane, Rice University, in early 2000s
        $³√n = $n ** (1 / 3);
        $k = 2 * ceil($³√n);

        return self::atLeastOne($k);
    }

    /**
     * Calculates the recommended number of classes (k) according to the Terrell-Scott Rule (1985).
     *
     *    𝒌 = ⌈ ³√(2𝑛) ⌉
     *    𝒌 = ⌈ (2𝑛)¹′³ ⌉
     *
     *
     * Source:
     * https://en.wikipedia.org/wiki/Histogram#Terrell%E2%80%93Scott_rule
     *
     * @param array<float|int> $data array of numerical data points
     *
     * @return int recommended number of bins (𝒌)     * @return int Recommended number of classes (𝒌) based on the Terrell-Scott Rule
     *
     * @throws \InvalidArgumentException if the dataset is empty
     */
    public static function terrellScott(array $data): int
    {
        $n = count($data);

        if (0 === $n) {
            throw new \InvalidArgumentException('Dataset cannot be empty to apply the Terrell-Scott Rule.');
        }

        // The Rice Rule’s academic original, as taught by
        // George R. Terrell and David W. Scott (1985)
        $k = ceil((2 * $n) ** (1 / 3));

        return self::atLeastOne($k);
    }

    /**
     * Calculates the recommended number of classes (𝒌) according to Sturges' rule (1926).
     *
     *    𝑘 = 1 + ⌈ log₂(𝑛) ⌉
     *
     *
     * Source:
     * Rubia, J.M.D.L. (2024):
     * Rice University Rule to Determine the Number of Bins.
     * Open Journal of Statistics, 14, 119-149.
     *
     * @param array<float|int> $data array of numerical data points
     *
     * @return int Recommended number of bins (𝒌)
     *
     * @throws \InvalidArgumentException if the dataset is empty
     */
    public static function sturges(array $data): int
    {
        $n = count($data);

        if (0 === $n) {
            throw new \InvalidArgumentException("Dataset cannot be empty to apply the Sturges' Rule.");
        }

        // Sturges' rule
        $k = 1 + ceil(log($n, 2));

        return self::atLeastOne($k);
    }

    /**
     * Calculates the recommended number of classes (𝒌) according to
     * Doane’s improvement of Sturges' rule (1976) which accounts for skewness.
     *
     *            ⎡                 ⎛       │√b1⎟    ⎞ ⎤
     *    𝑘 = 1 + ⎪ log₂ (𝑛) + log₂ ⎪ 1 + —————————  ⎪ ⎪
     *            ⎪                 ⎝       σ_√b1    ⎠ ⎪
     *
     *
     *    “The standard deviation or error of Karl Pearson’s skewness coefficient (√b1)
     *     is calculated using the population formula proposed by Egon Sharpe Pearson
     *     for a variable with a normal distribution.” — Rubia, J.M.D.L. (2024)
     *
     * Source:
     * Rubia, J.M.D.L. (2024):
     * Rice University Rule to Determine the Number of Bins.
     * Open Journal of Statistics, 14, 119-149.
     *
     * @param array<float|int> $data       array of numerical data points
     * @param mixed            $population whether to use the population formula for skewness (default is false, using sample formula)
     *
     * @return int Recommended number of bins (𝒌)
     *
     * @throws \InvalidArgumentException if the dataset contains fewer than 3 numbers
     */
    public static function doane(array $data, $population = false): int
    {
        $n = count($data);

        if ($n < 3) {
            throw new \InvalidArgumentException("Dataset must contain at least 3 numbers to apply the Doane's Rule.");
        }

        // Doane's rule

        if ($population) {
            // Karl Pearson’s Skewness Coefficient √b1
            // and Egon Sharpe Pearson’s error of skewness.
            // Most accurate when applied to large samples
            // or when inferring about the population
            $√b1 = RandomVariable::populationSkewness($data);
            $σ_√b1 = self::sesEgonSharpePearson($n);
        } else {
            // More modern variant for small to moderate samples.
            // Corrects for bias and sample size effects
            $√b1 = RandomVariable::sampleSkewness($data);
            $σ_√b1 = RandomVariable::ses($n);
        }

        $│√b1│ = abs($√b1); // Readability

        $k = 1 + ceil(log($n, 2) + log(1 + $│√b1│ / $σ_√b1, 2));

        return self::atLeastOne($k);
    }

    /**
     * Calculates the recommended number of classes (𝒌) according to the Square Root Rule by Karl Pearson (1892).
     *
     *    𝒌 = ⌈ √𝑛 ⌉
     *
     *
     * Source:
     * Rubia, J.M.D.L. (2024):
     * Rice University Rule to Determine the Number of Bins.
     * Open Journal of Statistics, 14, 119-149.
     *
     * @param array<float|int> $data array of numerical data points
     *
     * @return int Recommended number of bins (𝒌)
     *
     * @throws \InvalidArgumentException if the dataset is empty
     */
    public static function squareRoot(array $data): int
    {
        $n = count($data);

        if (0 === $n) {
            throw new \InvalidArgumentException('Dataset cannot be empty to apply the Square Root Rule.');
        }

        // Square Root Rule
        $k = ceil(sqrt($n));

        return self::atLeastOne($k);
    }

    /**
     * Calculates the recommended number of classes (𝒌) according to Scott's rule (1979).
     *
     * Scott's rule is based on the standard deviation of the data
     * and is used to determine the optimal bin width:
     *
     *    ”The number of intervals (𝒌) is determined by rounding up the quotient of the
     *     total range (𝑹) divided by the amplitude (𝒘).“ — Rubia, J.M.D.L. (2024)
     *
     *
     *         3,49 × 𝑠
     *   𝒘 =  ——————————— = 𝒉
     *           ³√𝑛
     *
     *   𝑹 = 𝑚𝑎𝑥(x_𝑖) - 𝑚𝑖𝑛(x_𝑖)
     *
     *   𝒌 = ⎡ 𝑹 / 𝒘 ⎤
     *
     *
     * Source:
     * Rubia, J.M.D.L. (2024):
     * Rice University Rule to Determine the Number of Bins.
     * Open Journal of Statistics, 14, 119-149.
     *
     * @param array<float|int> $data array of numerical data points
     *
     * @return array<string,float|int> array with keys `bins` (𝒌), `width` (𝒉), `stddev` (𝒔), and `range` (𝑹)
     *
     * @throws \InvalidArgumentException if the dataset is empty
     */
    public static function scott(array $data): array
    {
        $n = count($data);

        if (0 === $n) {
            throw new \InvalidArgumentException("Dataset cannot be empty to apply the Scott's Rule.");
        }

        // Scott's rule.
        $R = Descriptive::range($data);
        $³√n = $n ** (1 / 3);

        $s = Descriptive::standardDeviation($data);
        if (0.0 === $s) { // numbers in data are all equal
            $h = 0.0;
            $k = 1;
        } else {
            $h = 3.49 * $s / $³√n;
            $k = ceil($R / $h);
        }

        return [
            'width' => $h,
            'bins' => self::atLeastOne($k),
            'range' => $R,
            'stddev' => $s,
        ];
    }

    /**
     * Calculates the recommended number of classes (𝒌) according to the Freedman-Diaconis (1981) rule.
     *
     * This rule is based on the interquartile range (IQR) of the data
     * and is used to determine the optimal bin width.
     *
     *
     *   IQR = Q₃ - Q₁
     *
     *             2 × IQR
     *   𝒘   =  ———————————— = 𝒉
     *               ³√𝑛
     *
     *   𝑹   =  𝑚𝑎𝑥(x_𝑖) - 𝑚𝑖𝑛(x_𝑖)
     *
     *   𝒌   =  ⎡ 𝑹 / 𝒘 ⎤
     *
     *
     *
     * Source:
     * Rubia, J.M.D.L. (2024):
     * Rice University Rule to Determine the Number of Bins.
     * Open Journal of Statistics, 14, 119-149.
     *
     * @param array<float|int> $data array of numerical data points
     *
     * @return array<string,float|int> Array with keys `bins` (𝒌), `width` (𝒉), `range` (𝑹), and `IQR`,
     *
     * @throws \InvalidArgumentException if the dataset is empty
     */
    public static function freedmanDiaconis(array $data): array
    {
        $n = count($data);

        if (0 === $n) {
            throw new \InvalidArgumentException('Dataset cannot be empty to apply the Freedman-Diaconis Rule.');
        }

        // Freedman-Diaconis rule
        $³√n = $n ** (1 / 3);
        $R = Descriptive::range($data);

        $IQR = Descriptive::interquartileRange($data, 'inclusive');
        if (0.0 === $IQR) { // numbers in data are all equal
            $h = 0.0;
            $k = 1;
        } else {
            $h = 2 * $IQR / $³√n;
            $k = ceil($R / $h);
        }

        return [
            'width' => $h,
            'bins' => self::atLeastOne($k),
            'range' => $R,
            'IQR' => $IQR,
        ];
    }

    /**
     * Helper method: Ensure that the number of bins is at least one.
     *
     * @param float|int $k the calculated number of bins
     *
     * @return int the number of bins, guaranteed to be at least one
     */
    protected static function atLeastOne($k): int
    {
        return max(1, (int) ceil($k));
    }

    /**
     * Calculates the standard error of the skewness coefficient for a given sample size.
     *
     * This method is used in Doane's rule to adjust the number of bins based on skewness
     * and is attributed to Egon Sharpe Pearson
     *
     *             _______________
     *            /    6(𝑛–2)
     *   σ_√b1 = / ——————————————
     *          √    (𝑛+1)(𝑛+3)
     *
     * Source:
     * Rubia, J.M.D.L. (2024):
     * Rice University Rule to Determine the Number of Bins.
     * Open Journal of Statistics, 14, 119-149.
     *
     * @param int $n sample size
     *
     * @return float standard error of the skewness coefficient
     */
    protected static function sesEgonSharpePearson(int $n): float
    {
        return sqrt((6 * ($n - 2)) / (($n + 1) * ($n + 3)));
    }
}
