<?php
/**
 * Copyright 2026 Arte e Informatica di Loris Modena e C. s.a.s.
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
define('_PS_VERSION_', isset($argv[1]) ? $argv[1] : '9.1.0');

/** Minimal module environment for price behavior checks. */
class Module
{
    public $context;
    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $bootstrap;
    public $ps_versions_compliancy;
    public $displayName;
    public $description;

    public function __construct()
    {
        $this->context = (object) ['smarty' => new TestSmarty(), 'currency' => (object) ['iso_code' => 'EUR']];
        if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
            $this->context = new TestContext();
        }
    }

    public function l($message)
    {
        return $message;
    }
}

/** Formatting context for modern PrestaShop. */
class TestContext
{
    public $smarty;
    public $currency;

    public function __construct()
    {
        $this->smarty = new TestSmarty();
        $this->currency = (object) ['iso_code' => 'EUR'];
    }

    public function getCurrentLocale()
    {
        return $this;
    }

    public function formatPrice($price, $currency)
    {
        return number_format($price, 2, '.', '') . ' ' . $currency;
    }
}

/** Capture assigned fields without copying Smarty into the module. */
class TestSmarty
{
    public $values;

    public function createTemplate($path, $parent)
    {
        return $this;
    }

    public function assign($values)
    {
        $this->values = $values;
    }

    public function fetch()
    {
        return json_encode($this->values);
    }
}

/** Shop tax switch. */
class Configuration
{
    public static $taxes = true;

    public static function get($key)
    {
        return self::$taxes;
    }
}

/** Customer tax display setting. */
class TaxConfiguration
{
    public static $included = true;

    public function includeTaxes()
    {
        return self::$included;
    }
}

/** Pricing fixture with combination, quantity and selectable tax rate. */
class Product
{
    public static $rate = 0.22;
    public static $calls = [];

    public static function getPriceStatic(...$arguments)
    {
        self::$calls[] = $arguments;
        $net = ($arguments[2] === 2 ? 150 : 100) * ($arguments[7] >= 10 ? 0.9 : 1);

        return $net * ($arguments[1] ? 1 + self::$rate : 1);
    }
}

/** Legacy formatting fixture. */
class Tools
{
    public static function displayPrice($price, $currency)
    {
        return number_format($price, 2, '.', '') . ' ' . $currency->iso_code;
    }
}

require dirname(__DIR__) . '/tec_dualprice.php';
$module = new Tec_Dualprice();
$params = ['type' => 'weight', 'hook_origin' => 'product_sheet', 'product' => [
    'id_product' => 1, 'id_product_attribute' => 0, 'quantity_wanted' => 1,
    'minimal_quantity' => 1, 'show_price' => true,
]];
$check = function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$read = function () use ($module, &$params) {
    return json_decode($module->hookDisplayProductPriceBlock($params), true);
};
$result = $read();
$check($result['tec_dualprice_price'] === '100.00 EUR' && $result['tec_dualprice_label'] === 'VAT excluded', 'Gross to net');
TaxConfiguration::$included = false;
$result = $read();
$check($result['tec_dualprice_price'] === '122.00 EUR' && $result['tec_dualprice_label'] === 'VAT included', 'Net to gross');
Product::$rate = 0.04;
$check($read()['tec_dualprice_price'] === '104.00 EUR', 'Reduced VAT');
$params['product']['id_product_attribute'] = 2;
$params['product']['quantity_wanted'] = 10;
$check($read()['tec_dualprice_price'] === '140.40 EUR', 'Combination and quantity discount');
Product::$rate = 0;
$check($read()['tec_dualprice_price'] === '135.00 EUR', 'Zero tax');
$params['product']['id_customization'] = 8;
$read();
$lastCall = end(Product::$calls);
$check($lastCall[17] === 8 && $lastCall[13] === true, 'Customization and ecotax forwarded');
foreach (['-1', '1x', [], '2147483648', '1e2'] as $invalid) {
    $params['product']['id_product'] = $invalid;
    $before = count(Product::$calls);
    $check($module->hookDisplayProductPriceBlock($params) === '' && count(Product::$calls) === $before, 'Reject malformed identifiers');
}
$params['product']['id_product'] = 1;
Configuration::$taxes = false;
$check($module->hookDisplayProductPriceBlock($params) === '', 'Taxes disabled');
Configuration::$taxes = true;
$params['product']['show_price'] = false;
$check($module->hookDisplayProductPriceBlock($params) === '', 'Hidden prices');
$params['product']['show_price'] = true;
$params['type'] = 'after_price';
$check($module->hookDisplayProductPriceBlock($params) === '', 'No duplicate hook output');
echo 'Price behavior checks passed for ' . _PS_VERSION_ . PHP_EOL;
