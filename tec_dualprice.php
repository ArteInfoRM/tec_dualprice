<?php
/**
 * Copyright 2026 Arte e Informatica di Loris Modena e C. s.a.s.
 *
 * @author    Tecnoacquisti.com <helpdesk@tecnoacquisti.com>
 * @copyright 2026 Arte e Informatica di Loris Modena e C. s.a.s.
 * @license   https://opensource.org/licenses/MIT MIT License
 * @version   1.0.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Display the complementary tax price using the native front-office pricing engine.
 * This entry point retains PHP 5.6 syntax for PrestaShop 1.7 compatibility.
 */
class Tec_Dualprice extends Module
{
    /** Initialize module metadata. */
    public function __construct()
    {
        $this->name = 'tec_dualprice';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'Tecnoacquisti.com®';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => '9.99.99'];
        parent::__construct();
        $this->displayName = $this->l('Dual tax price');
        $this->description = $this->l('Show the complementary tax-inclusive or tax-exclusive price below the product price.');
    }

    /** @return bool Whether all display hooks were registered. */
    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayProductPriceBlock');
    }

    /**
     * Assets are also needed on listing pages that can open a quick view.
     *
     * @param array $params Hook parameters
     *
     * @return void
     */
    public function hookDisplayHeader($params)
    {
        $this->context->controller->registerStylesheet(
            'module-tec-dualprice',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 150]
        );
        $this->context->controller->registerJavascript(
            'module-tec-dualprice',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 200]
        );
    }

    /**
     * Render inside the price fragment, outside tax labels replaced by other modules.
     *
     * @param array $params Presented product and price block type
     *
     * @return string Escaped Smarty output, or an empty string
     */
    public function hookDisplayProductPriceBlock($params)
    {
        if (!isset($params['type'], $params['hook_origin'], $params['product'])
            || $params['type'] !== 'weight'
            || $params['hook_origin'] !== 'product_sheet'
            || !(bool) Configuration::get('PS_TAX')) {
            return '';
        }

        $product = $params['product'];
        if ((!is_array($product) && !($product instanceof ArrayAccess))
            || empty($product['show_price'])) {
            return '';
        }

        $id = isset($product['id_product']) ? $product['id_product'] : null;
        $attribute = isset($product['id_product_attribute']) ? $product['id_product_attribute'] : 0;
        $quantity = isset($product['quantity_wanted']) ? $product['quantity_wanted'] : 1;
        $minimum = isset($product['minimal_quantity']) ? $product['minimal_quantity'] : 1;
        $customization = isset($product['id_customization']) ? $product['id_customization'] : 0;
        foreach ([$id, $attribute, $quantity, $minimum, $customization] as $value) {
            if (!$this->isUnsignedInteger($value)) {
                return '';
            }
        }
        if ((int) $id < 1 || (int) $quantity < 1 || (int) $minimum < 1) {
            return '';
        }

        $includeTaxes = (bool) (new TaxConfiguration())->includeTaxes();
        $specificPrice = null;
        // Use the same engine as the presenter, including tax overrides and ecotax.
        $amount = Product::getPriceStatic(
            (int) $id,
            !$includeTaxes,
            (int) $attribute,
            6,
            null,
            false,
            true,
            max((int) $quantity, (int) $minimum),
            false,
            null,
            null,
            null,
            $specificPrice,
            true,
            true,
            $this->context,
            true,
            (int) $customization
        );
        if (!is_numeric($amount) || !is_finite((float) $amount) || $amount < 0) {
            return '';
        }

        // The modern locale formatter is isolated from the legacy formatting path.
        if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
            $formatted = $this->context->getCurrentLocale()->formatPrice(
                (float) $amount,
                $this->context->currency->iso_code
            );
        } else {
            $formatter = new \PrestaShop\PrestaShop\Adapter\Product\PriceFormatter();
            $formatted = $formatter->format((float) $amount);
        }

        $template = $this->context->smarty->createTemplate(
            'module:tec_dualprice/views/templates/hook/price.tpl',
            $this->context->smarty
        );
        $template->assign([
            'tec_dualprice_price' => $formatted,
            'tec_dualprice_label' => $includeTaxes ? $this->l('VAT excluded') : $this->l('VAT included'),
            'tec_dualprice_id' => (int) $id,
        ]);

        return $template->fetch();
    }

    /**
     * Reject arrays, negative values, partial numbers and oversized identifiers.
     *
     * @param mixed $value Untrusted product field
     *
     * @return bool Whether the value is an unsigned 32-bit integer
     */
    private function isUnsignedInteger($value)
    {
        return (is_int($value) || is_string($value))
            && preg_match('/\A[0-9]{1,10}\z/D', (string) $value) === 1
            && (float) $value <= 2147483647;
    }
}
