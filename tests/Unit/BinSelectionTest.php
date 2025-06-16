<?php

/**
 * This file is part of tomkyle/binning
 *
 * Methods for binning data into ranges, for histogram generation and statistical analysis.
 */

declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use tomkyle\Binning\BinSelection;

#[CoversClass(BinSelection::class)]
class BinSelectionTest extends TestCase
{
    #[Test]
    #[DataProvider('suggestBinsMethodProvider')]
    public function suggestBinsWorksWithAllMethods(string $method, array $data): void
    {
        $result = BinSelection::suggestBins($data, $method);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

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



    #[Test]
    #[DataProvider('suggestBinWidthMethodProvider')]
    public function suggestBinWidthWorksWithAllMethods(string $method, array $data): void
    {
        $result = BinSelection::suggestBinWidth($data, $method);
        $this->assertIsFloat($result);
    }

    public static function suggestBinWidthMethodProvider(): array
    {
        $data = range(1, 20);

        return [
            "default (no method specified)" => [BinSelection::DEFAULT, $data],
            BinSelection::FREEDMAN_DIACONIS => [BinSelection::FREEDMAN_DIACONIS, $data],
            BinSelection::SCOTT => [BinSelection::SCOTT, $data],
        ];
    }





    #[Test]
    public function suggestBinsThrowsExceptionForInvalidMethod(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown binning method: invalid');

        BinSelection::suggestBins([1, 2, 3], 'invalid');
    }


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


    #[Test]
    #[DataProvider('sturgesDataProvider')]
    public function sturgesReturnsExpectedValues(array $data, int $expectedBins): void
    {
        $result = BinSelection::sturges($data);
        $this->assertIsInt($result);
        $this->assertEquals($expectedBins, $result);
    }

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

    #[Test]
    public function sturgesThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BinSelection::sturges([]);
    }



    // ===================================================
    // Test methods for Doane's Rule
    // ===================================================


    #[Test]
    #[DataProvider('doaneDataProvider')]
    public function doaneReturnsExpectedValuesForSamples(array $data, int $expectedMinBins): void
    {
        $result = BinSelection::doane($data);

        // Doane's rule adjusts for skewness, so we test that
        // it returns at least as many bins as Sturges
        $sturgesResult = BinSelection::sturges($data);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual($expectedMinBins, $result);
        $this->assertGreaterThanOrEqual($sturgesResult, $result);
    }

    #[Test]
    #[DataProvider('doaneDataProvider')]
    public function doaneReturnsExpectedValuesForPopulation(array $data, int $expectedMinBins): void
    {
        $result = BinSelection::doane($data, population: true);

        // Doane's rule adjusts for skewness, so we test that it returns at least as many bins as Sturges
        $sturgesResult = BinSelection::sturges($data);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual($expectedMinBins, $result);
        $this->assertGreaterThanOrEqual($sturgesResult, $result);
    }

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

    #[Test]
    public function doaneThrowsExceptionForTooSmallDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Dataset must contain at least 3 numbers to apply the Doane's Rule.");

        BinSelection::doane([1,2]);
    }

    #[Test]
    #[DataProvider('skewnessAdjustmentDataProvider')]
    public function doaneAdjustsForSkewness(array $symmetricData, array $skewedData): void
    {
        $symmetricBins = BinSelection::doane($symmetricData);
        $skewedBins = BinSelection::doane($skewedData);

        // For datasets of similar size, Doane should suggest more bins for skewed data
        $this->assertGreaterThanOrEqual($symmetricBins, $skewedBins);
    }

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

    #[Test]
    #[DataProvider('riceDataProvider')]
    public function riceReturnsExpectedValues(array $data, int $expectedBins): void
    {
        $result = BinSelection::rice($data);
        $this->assertIsInt($result);
        $this->assertEquals($expectedBins, $result);
    }

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



    #[Test]
    public function riceThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BinSelection::rice([]);
    }



    // ===================================================
    // Test methods for Terrell-Scrott Rule
    // ===================================================

    #[Test]
    #[DataProvider('terrellScottDataProvider')]
    public function terrellScottReturnsExpectedValues(array $data, int $expectedBins): void
    {
        $result = BinSelection::terrellScott($data);
        $this->assertIsInt($result);
        $this->assertEquals($expectedBins, $result);
    }

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



    #[Test]
    public function terrellScottThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        BinSelection::terrellScott([]);
    }



    // ===================================================
    // Test methods for Square Root Rule
    // ===================================================


    #[Test]
    #[DataProvider('squareRootDataProvider')]
    public function squareRootReturnsExpectedValues(array $data, int $expectedBins): void
    {
        $result = BinSelection::squareRoot($data);
        $this->assertIsInt($result);
        $this->assertEquals($expectedBins, $result);
    }

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

    #[Test]
    public function squareRootThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Dataset cannot be empty to apply the Square Root Rule.");

        BinSelection::squareRoot([]);
    }



    // ===================================================
    // Test methods for Scott's Rule
    // ===================================================


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

    #[Test]
    public function freedmanDiaconisThrowsExceptionForEmptyDataset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Dataset cannot be empty to apply the Freedman-Diaconis Rule.");

        BinSelection::freedmanDiaconis([]);
    }



}
