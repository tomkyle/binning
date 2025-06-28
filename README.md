
# tomkyle/binning

[![Composer Version](https://img.shields.io/packagist/v/tomkyle/binning)](https://packagist.org/packages/tomkyle/binning )
[![PHP version](https://img.shields.io/packagist/php-v/tomkyle/binning)](https://packagist.org/packages/tomkyle/binning )
[![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/tomkyle/binning/php.yml)](https://github.com/tomkyle/binning/actions/workflows/php.yml)
[![Packagist License](https://img.shields.io/packagist/l/tomkyle/binning)](LICENSE)

**Determine the optimal 𝒌 number of bins for histogram creation and optimal bin width 𝒉 using various statistical methods. Its unified interface includes implementations of well-known binning rules such as:**

- Square Root Rule (1892)
- Sturges’ Rule (1926)
- Doane’s Rule (1976)
- Scott’s Rule (1979)
- Freedman-Diaconis Rule (1981)
- Terrell-Scott’s Rule (1985)
- Rice University Rule



## Requirements

This library requires PHP 8.3 or newer. Support of older versions like [markrogoyski/math-php](https://github.com/markrogoyski/math-php) provides for PHP 7.2+ is not planned.



## Installation

```bash
composer require tomkyle/binning
```



## Usage

The **BinSelection** class provides several methods for determining the optimal number of bins for histogram creation and optimal bin width. You can either use specific methods directly or the general `suggestBins()` and `suggestBinWidth()` methods with different strategies.



### Determine Bin Width

Use the **suggestBinWidth** method to get the *optimal bin width* based on the selected method. The method returns the bin width, often referred to as 𝒉, as a float value.

```php
<?php
use tomkyle\Binning\BinSelection;

$data = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

// Default method: Freedman-Diaconis Rule (1981)
$h = BinSelection::suggestBinWidth($data);
$h = BinSelection::suggestBinWidth($data, BinSelection::DEFAULT);

// Explicitly set method
$h = BinSelection::suggestBinWidth($data, BinSelection::FREEDMAN_DIACONIS);
$h = BinSelection::suggestBinWidth($data, BinSelection::SCOTT);
```



### Determine Number of Bins

Use the **suggestBins** method to get the *optimal number of bins* based on the selected method. The method returns the number of bins, often referred to as 𝒌, as an integer value.

```php
<?php
use tomkyle\Binning\BinSelection;

$data = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

// Defaults to Freedman-Diaconis Rule
$k = BinSelection::suggestBins($data);
$k = BinSelection::suggestBins($data, BinSelection::DEFAULT);

// Square Root Rule (Pearson, 1892)
$k = BinSelection::suggestBins($data, BinSelection::SQUARE_ROOT);
$k = BinSelection::suggestBins($data, BinSelection::PEARSON);

// Sturges' Rule (1926)
$k = BinSelection::suggestBins($data, BinSelection::STURGES);

// Doane's Rule (1976) in 2 variants for samples (default) or populations
$k = BinSelection::suggestBins($data, BinSelection::DOANE);
$k = BinSelection::suggestBins($data, BinSelection::DOANE, population: true); 

// Scott's Rule (1979)
$k = BinSelection::suggestBins($data, BinSelection::SCOTT);

// Freedman-Diaconis Rule (1981)
$k = BinSelection::suggestBins($data, BinSelection::FREEDMAN_DIACONIS);

// Terrell-Scott’s Rule (1985)
$k = BinSelection::suggestBins($data, BinSelection::TERRELL_SCOTT);

// Rice University Rule
$k = BinSelection::suggestBins($data, BinSelection::RICE);
```



---



### Explicit method calls

You can also call the specific methods directly to get the bin width 𝒉 or number of bins 𝒌.

- Most of the methods return the bin number 𝒌 as an *integer* value. 
- Two methods, **Scotts’ Rule** and **Freedman-Diaconis Rule**, provide both 𝒌 and 𝒉 as an *array*. 

The result array contains additional information like the data range 𝑹, the inter-quartile range ***IQR***, or standard deviation **stddev**, which can be useful for further analysis.



---



#### 1. Pearson’s Square Root Rule (1892)

Simple rule using the square root of the sample size.

$$
k = \left \lceil \sqrt{n} \ \right \rceil 
$$

```php
$k = BinSelection::squareRoot($data);
```



---



#### 2. Sturges’s Rule (1926)

Based on the logarithm of the sample size. Good for normal distributions.

$$
k = 1 + \left \lceil \ \log_2(n) \  \right \rceil
$$

```php
$k = BinSelection::sturges($data);
```



---



#### 3. Doane’s Rule (1976)

Improvement of *Sturges*’ rule that accounts for data skewness.

$$
k = 1 + \left\lceil \  \log_2(n) + \log_2\left(1 + \frac{|g_1|}{\sigma_{g_1}}\right) \  \right \rceil 
$$

```php
// Using sample-based calculation (default)
$k = BinSelection::doane($data);

// Using population-based calculation
$k = BinSelection::doane($data, population: true);
```



---



#### 4. Scott’s Rule (1979)

Based on the standard deviation and sample size. Good for continuous data.

$$
h = \frac{3.49\,\hat{\sigma}}{\sqrt[3]{n}} 
$$

$$
R = \max_i x_i - \min_i x_i 
$$

$$
k = \left \lceil \ \frac{R}{h} \ \right \rceil 
$$

The result is an array with keys `width`, `bins`, `range`, and `stddev`. Map them to variables like so:

```php
list($h, $k, $R, stddev) = BinSelection::scott($data);
```



---



#### 5. Freedman-Diaconis Rule (1981)

Based on the interquartile range (IQR). Robust against outliers.

$$
IQR = Q_3 - Q_1 
$$

$$
h = 2 \times \frac{\mathrm{IQR}}{\sqrt[3]{n}} 
$$

$$
R = \text{max}_i x_i - \text{min}_i x_i 
$$

$$
k = \left \lceil \frac{R}{h} \right \rceil 
$$

The result is an array with keys `width`, `bins`, `range`, and `IQR`. Map them to variables like so:

```php
list($h, $k, $R, $IQR) = BinSelection::freedmanDiaconis($data);
```



---



#### 6. Terrell-Scott’s Rule (1985)

Uses the cube root of the sample size, generally provides more bins than *Sturges*. This is the original *Rice Rule*:

$$
k = \left \lceil \  \sqrt[3]{2n} \enspace \right \rceil = \left \lceil \  (2n)^{1/3} \  \right \rceil 
$$

```php
$k = BinSelection::terrellScott($data);
```



---



#### 7. Rice University Rule

Uses the cube root of the sample size, generally provides more bins than *Sturges*. Formula as taught by David M. Lane at Rice University. — **N.B.** This *Rice Rule* seems to be not the original. In fact, *Terrell-Scott’s* (1985) seems to be. Also note that both variants can yield different results under certain circumstances. This Lane’s variant from the early 2000s is however more commonly cited:

$$
k = 2 \times \left \lceil \  \sqrt[3]{n} \enspace \right \rceil =  2 \times \left \lceil \  n^{1/3} \  \right \rceil 
$$

```php
$k = BinSelection::rice($data);
```



---



## Method Selection Guidelines

| Rule                  | Strengths & Weaknesses                                       |
| --------------------- | ------------------------------------------------------------ |
| **Freedman–Diaconis** | Uses the IQR to set 𝒉, so it is robust against outliers and adapts to data spread. <br />⚠️ May over‐smooth heavily skewed or multi‐modal data when IQR is small. |
| **Sturges’ Rule**     | Very simple, works well for roughly normal, moderate-sized datasets. <br />⚠️ Ignores outliers and underestimates bin count for large or skewed samples. |
| **Rice Rule**         | Independent of data shape and easy to compute. <br />⚠️ Prone to over‐ or under‐smoothing when the distribution is heavy‐tailed or skewed. |
| **Terrell–Scott**     | Similar approach as *Rice Rule* but with asymptotically optimal MISE properties; gives more bins than Sturges and adapts better at large 𝒏. <br />⚠️ Still ignores skewness and outliers. |
| **Square Root Rule**  | Simply the square root, so it requires no distributional estimates. <br />⚠️ May produce too few bins for complex distributions — or too many for very noisy data. |
| **Doane’s Rule**      | Extends *Sturges’ Rule* by adding a skewness correction. Improving performance on asymmetric data.<br />⚠️ Requires estimating the third moment (skewness), which can be unstable for small 𝒏. |
| **Scott’s Rule**      | Uses standard deviation to minimize MISE, providing good balance for unimodal, symmetric data. <br />⚠️  Sensitive to outliers (inflated $\sigma$) and may underperform on skewed distributions. |



## Literature

Rubia, J.M.D.L. (2024): 
**Rice University Rule to Determine the Number of Bins.**
Open Journal of Statistics, 14, 119-149.
DOI: [10.4236/ojs.2024.141006](https://doi.org/10.4236/ojs.2024.141006) 

Wikipedia: 
**Histogram / Number of bins and width**
https://en.wikipedia.org/wiki/Histogram#Number_of_bins_and_width



## Practical Example

```php
<?php
use tomkyle\Binning\BinSelection;

// Generate sample data (e.g., from measurements)
$measurements = [
	12.3, 14.1, 13.8, 15.2, 12.9, 14.7, 13.1, 15.8, 12.5, 14.3,
	13.6, 15.1, 12.8, 14.9, 13.4, 15.5, 12.7, 14.2, 13.9, 15.0
];

echo "Data points: " . count($measurements) . "\n\n";

// Compare different methods
$methods = [
	'Sturges’s Rule' => BinSelection::STURGES,
	'Rice University Rule' => BinSelection::RICE,
	'Terrell-Scott’s Rule' => BinSelection::TERRELL_SCOTT,
	'Square Root Rule' => BinSelection::SQUARE_ROOT,
	'Doane’s Rule' => BinSelection::DOANE,
	'Scott’s Rule' => BinSelection::SCOTT,
	'Freedman-Diaconis Rule' => BinSelection::FREEDMAN_DIACONIS,
];

foreach ($methods as $name => $method) {
	$bins = BinSelection::suggestBins($measurements, $method);
	echo sprintf("%-18s: %2d bins\n", $name, $bins);
}
```



## Error Handling

All methods will throw `InvalidArgumentException` for invalid inputs:

```php
try {
	// This will throw an exception
	$bins = BinSelection::sturges([]);
} catch (InvalidArgumentException $e) {
	echo "Error: " . $e->getMessage();
	// Output: "Dataset cannot be empty to apply the Sturges' Rule."
}

try {
	// This will throw an exception  
	$bins = BinSelection::suggestBins($data, 'invalid-method');
} catch (InvalidArgumentException $e) {
	echo "Error: " . $e->getMessage();
	// Output: "Unknown binning method: invalid-method"
}
```





## Development

### Clone repo and install requirements

```bash
$ git clone git@github.com:tomkyle/binning.git
$ composer install
$ pnpm install
```

### Watch source and run various tests

This will watch changes inside the **src/** and **tests/** directories and run a series of tests:

1. Find and run the according unit test with *PHPUnit*.
2. Find possible bugs and documentation isses using *phpstan*. 
3. Analyse code style and give hints on newer syntax using *Rector*.

```bash
$ npm run watch
```

**Run PhpUnit**

```bash
$ npm run phpunit
```

