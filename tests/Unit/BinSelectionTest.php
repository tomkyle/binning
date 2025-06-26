<?php

/**
 * This file is part of tomkyle/binning.
 *
 * Determine optimal number of bins 𝒌 for histogram creation and optimal bin width 𝒉 using various statistical methods.
 */

declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use tomkyle\Binning\BinSelection;

/**
 * @internal
 */
#[CoversClass(BinSelection::class)]
class BinSelectionTest extends TestCase
{
    /**
     * Tests that suggestBins works with all available binning methods.
     *
     * @param string     $method The binning method constant to test
     * @param array<int> $data   The test data array
     */
    #[Test]
    #[DataProvider('suggestBinsMethodProvider')]
    public function suggestBinsWorksWithAllMethods(string $method, array $data): void
    {
        $result = BinSelection::suggestBins($data, $method);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * @return array<string, array{string, array<int>}>
     */
    public static function suggestBinsMethodProvider(): array
    {
        $data = range(1, 20);

        return [
            'default (no method specified)' => [BinSelection::DEFAULT, $data],
            BinSelection::FREEDMAN_DIACONIS => [BinSelection::FREEDMAN_DIACONIS, $data],
            BinSelection::RICE => [BinSelection::RICE, $data],
            BinSelection::TERRELL_SCOTT => [BinSelection::TERRELL_SCOTT, $data],
            BinSelection::STURGES => [BinSelection::STURGES, $data],
            BinSelection::DOANE => [BinSelection::DOANE, $data],
            BinSelection::SQUARE_ROOT => [BinSelection::SQUARE_ROOT, $data],
            BinSelection::PEARSON => [BinSelection::PEARSON, $data],
            BinSelection::SCOTT => [BinSelection::SCOTT, $data],
        ];
    }

    /**
     * Tests that suggestBinWidth works with all available methods that support width calculation.
     *
     * @param string     $method The binning method constant to test
     * @param array<int> $data   The test data array
     */
    #[Test]
    #[DataProvider('suggestBinWidthMethodProvider')]
    public function suggestBinWidthWorksWithAllMethods(string $method, array $data): void
    {
        $result = BinSelection::suggestBinWidth($data, $method);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * @return array<string, array{string, array<int>}>
     */
    public static function suggestBinWidthMethodProvider(): array
    {
        $data = range(1, 20);

        return [
            'default (no method specified)' => [BinSelection::DEFAULT, $data],
            BinSelection::FREEDMAN_DIACONIS => [BinSelection::FREEDMAN_DIACONIS, $data],
            BinSelection::SCOTT => [BinSelection::SCOTT, $data],
        ];
    }

    /**
     * Tests that suggestBins throws exception for invalid method.
     */
    #[Test]
    public function suggestBinsThrowsExceptionForInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown binning method: invalid');

        BinSelection::suggestBins([1, 2, 3], 'invalid');
    }

    /**
     * Tests that suggestBins uses Freedman-Diaconis as default method.
     */
    #[Test]
    public function suggestBinsUsesFreedmanDiaconisToByDefault(): void
    {
        $data = range(1, 10);

        $defaultResult = BinSelection::suggestBins($data);
        $freedmanDiaconisResult = BinSelection::suggestBins($data, BinSelection::FREEDMAN_DIACONIS);

        $this->assertEquals($freedmanDiaconisResult, $defaultResult);
    }

    // ===================================================
    // Test methods for Sturges' Rule
    // ===================================================

    /**
     * Tests Sturges' rule returns expected bin counts for various dataset sizes.
     *
     * @param array<float|int> $data         Test dataset
     * @param int              $expectedBins Expected number of bins
     */
    #[Test]
    #[DataProvider('sturgesDataProvider')]
    public function sturgesReturnsExpectedValues(array $data, int $expectedBins): void
    {
        $result = BinSelection::sturges($data);
        $this->assertEquals($expectedBins, $result);
    }

    /**
     * @return array<string, array{array<float|int>, int}>
     */
    public static function sturgesDataProvider(): array
    {
        return [
            'single element' => [range(1, 1), 1],
            'two elements' => [range(1, 2), 2],
            'four elements' => [range(1, 4), 3],
            'eight elements' => [range(1, 8), 4],
            'ten elements' => [range(1, 10), 5],
            'sixteen elements' => [range(1, 16), 5],
            'thirty-two elements' => [range(1, 32), 6],
            'hundred elements' => [range(1, 100), 8],
            'thousand elements' => [range(1, 1000), 11],
            'mixed numeric data' => [[1, 2.5, 3, 4.7, 5], 4],
        ];
    }

    /**
     * Tests that Sturges' rule throws exception for empty dataset.
     */
    #[Test]
    public function sturgesThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BinSelection::sturges([]);
    }

    // ===================================================
    // Test methods for Doane's Rule
    // ===================================================

    /**
     * Tests Doane's rule with sample-based calculation returns expected minimum bins.
     *
     * @param array<float|int> $data            Test dataset
     * @param int              $expectedMinBins Expected minimum number of bins
     */
    #[Test]
    #[DataProvider('doaneDataProvider')]
    public function doaneReturnsExpectedValuesForSamples(array $data, int $expectedMinBins): void
    {
        $result = BinSelection::doane($data);

        // Doane's rule adjusts for skewness, so we test that
        // it returns at least as many bins as Sturges
        $sturgesResult = BinSelection::sturges($data);

        $this->assertGreaterThanOrEqual($expectedMinBins, $result);
        $this->assertGreaterThanOrEqual($sturgesResult, $result);
    }

    /**
     * Tests Doane's rule with population-based calculation returns expected minimum bins.
     *
     * @param array<float|int> $data            Test dataset
     * @param int              $expectedMinBins Expected minimum number of bins
     */
    #[Test]
    #[DataProvider('doaneDataProvider')]
    public function doaneReturnsExpectedValuesForPopulation(array $data, int $expectedMinBins): void
    {
        $result = BinSelection::doane($data, population: true);

        // Doane's rule adjusts for skewness, so we test that it returns at least as many bins as Sturges
        $sturgesResult = BinSelection::sturges($data);

        $this->assertGreaterThanOrEqual($expectedMinBins, $result);
        $this->assertGreaterThanOrEqual($sturgesResult, $result);
    }

    /**
     * @return array<string, array{array<float|int>, int}>
     */
    public static function doaneDataProvider(): array
    {
        return [
            'symmetric data' => [[1, 2, 3, 4, 5], 3],
            'right-skewed data' => [[1, 1, 1, 2, 2, 3, 5, 8, 13], 4],
            'left-skewed data' => [[1, 3, 5, 8, 8, 8, 9, 9, 9], 4],
            'normal distribution sample' => [range(1, 100), 8],
            'highly skewed data' => [[1, 1, 1, 1, 1, 2, 3, 4, 100], 4],
        ];
    }

    /**
     * Tests that Doane's rule throws exception for datasets too small.
     */
    #[Test]
    public function doaneThrowsExceptionForTooSmallDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Dataset must contain at least 3 numbers to apply the Doane's Rule.");

        BinSelection::doane([1, 2]);
    }

    /**
     * Tests that Doane's rule adjusts for skewness by suggesting more bins for skewed data.
     *
     * @param array<int> $symmetricData Symmetric test dataset
     * @param array<int> $skewedData    Skewed test dataset
     */
    #[Test]
    #[DataProvider('skewnessAdjustmentDataProvider')]
    public function doaneAdjustsForSkewness(array $symmetricData, array $skewedData): void
    {
        $symmetricBins = BinSelection::doane($symmetricData);
        $skewedBins = BinSelection::doane($skewedData);

        // For datasets of similar size, Doane should suggest more bins for skewed data
        $this->assertGreaterThanOrEqual($symmetricBins, $skewedBins);
    }

    /**
     * @return array<string, array{array<int>, array<int>}>
     */
    public static function skewnessAdjustmentDataProvider(): array
    {
        return [
            'symmetric vs. right-skewed' => [
                [1, 2, 3, 4, 5], // symmetric
                [1, 1, 1, 2, 10], // right-skewed
            ],
            'symmetric vs. left-skewed' => [
                [1, 2, 3, 4, 5], // symmetric
                [10, 1, 1, 1, 2], // left-skewed
            ],
        ];
    }

    // ===================================================
    // Test methods for Rice Rule
    // ===================================================

    /**
     * Tests that Rice rule returns expected bin counts for various dataset sizes.
     *
     * @param array<float|int> $data         Test dataset
     * @param int              $expectedBins Expected number of bins
     */
    #[Test]
    #[DataProvider('riceDataProvider')]
    public function riceReturnsExpectedValues(array $data, int $expectedBins): void
    {
        $result = BinSelection::rice($data);
        $this->assertEquals($expectedBins, $result);
    }

    /**
     * @return array<string, array{array<float|int>, int}>
     */
    public static function riceDataProvider(): array
    {
        return [
            'single element' => [[42], 2],
            'eight elements' => [[1, 2, 3, 4, 5, 6, 7, 8], 4],
            'twenty-seven elements' => [range(1, 27), 6],
            'sixty-four elements' => [range(1, 64), 8],
            'hundred elements' => [range(1, 100), 10],
            'thousand elements' => [range(1, 1000), 20],
            'mixed numeric data' => [[1, 2.5, 3, 4.7, 5], 4],
        ];
    }

    /**
     * Tests that Rice rule throws exception for empty dataset.
     */
    #[Test]
    public function riceThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BinSelection::rice([]);
    }

    // ===================================================
    // Test methods for Terrell-Scrott Rule
    // ===================================================

    /**
     * Tests that Terrell-Scott rule returns expected bin counts for various dataset sizes.
     *
     * @param array<float|int> $data         Test dataset
     * @param int              $expectedBins Expected number of bins
     */
    #[Test]
    #[DataProvider('terrellScottDataProvider')]
    public function terrellScottReturnsExpectedValues(array $data, int $expectedBins): void
    {
        $result = BinSelection::terrellScott($data);
        $this->assertEquals($expectedBins, $result);
    }

    /**
     * @return array<string, array{array<float|int>, int}>
     */
    public static function terrellScottDataProvider(): array
    {
        return [
            'single element' => [[42], 2],
            'eight elements' => [[1, 2, 3, 4, 5, 6, 7, 8], 3],
            'twenty-seven elements' => [range(1, 27), 4],
            'sixty-four elements' => [range(1, 64), 6],
            'hundred elements' => [range(1, 100), 6],
            'thousand elements' => [range(1, 1000), 13],
            'mixed numeric data' => [[1, 2.5, 3, 4.7, 5], 3],
        ];
    }

    /**
     * Tests that Terrell-Scott rule throws exception for empty dataset.
     */
    #[Test]
    public function terrellScottThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BinSelection::terrellScott([]);
    }

    // ===================================================
    // Test methods for Square Root Rule
    // ===================================================

    /**
     * Tests that Square Root rule returns expected bin counts for various dataset sizes.
     *
     * @param array<float|int> $data         Test dataset
     * @param int              $expectedBins Expected number of bins
     */
    #[Test]
    #[DataProvider('squareRootDataProvider')]
    public function squareRootReturnsExpectedValues(array $data, int $expectedBins): void
    {
        $result = BinSelection::squareRoot($data);
        $this->assertEquals($expectedBins, $result);
    }

    /**
     * @return array<string, array{array<float|int>, int}>
     */
    public static function squareRootDataProvider(): array
    {
        return [
            'single element' => [[42], 1],
            'four elements' => [[1, 2, 3, 4], 2],
            'nine elements' => [range(1, 9), 3],
            'sixteen elements' => [range(1, 16), 4],
            'twenty-five elements' => [range(1, 25), 5],
            'hundred elements' => [range(1, 100), 10],
            'non-perfect square' => [range(1, 10), 4],
            'mixed numeric data' => [[1, 2.5, 3, 4.7, 5], 3],
        ];
    }

    /**
     * Tests that Square Root rule throws exception for empty dataset.
     */
    #[Test]
    public function squareRootThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dataset cannot be empty to apply the Square Root Rule.');

        BinSelection::squareRoot([]);
    }

    // ===================================================
    // Test methods for Scott's Rule
    // ===================================================

    /**
     * Tests that Scott's rule returns expected minimum bin counts for various dataset types.
     *
     * @param array<float|int> $data            Test dataset
     * @param int              $expectedMinBins Expected minimum number of bins
     */
    #[Test]
    #[DataProvider('scottDataProvider')]
    public function scottReturnsExpectedValues(array $data, int $expectedMinBins): void
    {
        $result = BinSelection::scott($data);

        $this->assertArrayHasKey('bins', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('stddev', $result);
        $this->assertArrayHasKey('range', $result);

        $k = $result['bins'];

        $this->assertIsInt($k);
        $this->assertGreaterThan(0, $k);
        $this->assertGreaterThanOrEqual($expectedMinBins, $k);
    }

    /**
     * @return array<string, array{array<float|int>, int}>
     */
    public static function scottDataProvider(): array
    {
        return [
            'single element' => [[42], 1],
            'uniform small dataset' => [[1, 2, 3, 4, 5], 1],
            'uniform medium dataset' => [range(1, 20), 2],
            'normal-like distribution' => [[1, 2, 2, 3, 3, 3, 4, 4, 5], 2],
            'wide range dataset' => [[1, 5, 10, 15, 20, 25, 30], 2],
            'narrow range dataset' => [[10.1, 10.2, 10.3, 10.4, 10.5], 1],
            'large dataset' => [range(1, 100), 5],
            'constant dataset' => [[5, 5, 5, 5, 5], 1],
        ];
    }

    /**
     * Tests that Scott's rule throws exception for empty dataset.
     */
    #[Test]
    public function scottThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Dataset cannot be empty to apply the Scott's Rule.");

        BinSelection::scott([]);
    }

    // ===================================================
    // Test methods for Freedman-Diaconis Rule
    // ===================================================

    /**
     * Tests that Freedman-Diaconis rule returns expected minimum bin counts for various dataset types.
     *
     * @param array<float|int> $data            Test dataset
     * @param int              $expectedMinBins Expected minimum number of bins
     */
    #[Test]
    #[DataProvider('freedmanDiaconisDataProvider')]
    public function freedmanDiaconisReturnsExpectedValues(array $data, int $expectedMinBins): void
    {
        $result = BinSelection::freedmanDiaconis($data);

        $this->assertArrayHasKey('range', $result);
        $this->assertArrayHasKey('IQR', $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('bins', $result);

        $k = $result['bins'];

        $this->assertGreaterThan(0, $k);
        $this->assertGreaterThanOrEqual($expectedMinBins, $k);
    }

    /**
     * @return array<string, array{array<float|int>, int}>
     */
    public static function freedmanDiaconisDataProvider(): array
    {
        return [
            'single element' => [[42], 1],
            'uniform small dataset' => [[1, 2, 3, 4, 5], 1],
            'uniform medium dataset' => [range(1, 20), 2],
            'normal-like distribution' => [[1, 2, 2, 3, 3, 3, 4, 4, 5], 2],
            'wide range dataset' => [[1, 5, 10, 15, 20, 25, 30], 2],
            'narrow range dataset' => [[10.1, 10.2, 10.3, 10.4, 10.5], 1],
            'large dataset' => [range(1, 100), 5],
            'constant dataset' => [[5, 5, 5, 5, 5], 1],
            'outliers present' => [[1, 2, 3, 4, 5, 100], 2],
        ];
    }

    /**
     * Tests that Freedman-Diaconis rule throws exception for empty dataset.
     */
    #[Test]
    public function freedmanDiaconisThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Dataset cannot be empty to apply the Freedman-Diaconis Rule.');

        BinSelection::freedmanDiaconis([]);
    }
}
