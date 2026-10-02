# Dual tax price

A PrestaShop module by Tecnoacquisti.com® that displays the complementary VAT price on product pages and quick views:

- Main price including VAT: additional price excluding VAT.
- Main price excluding VAT: additional price including VAT.

The additional amount uses PrestaShop's product pricing engine, current customer, shop, currency, address, combination, quantity, discounts, customization and ecotax. No fixed VAT percentage is assumed. Taxes disabled or hidden product prices suppress the additional price. Zero-rated products may show two identical amounts.

## Compatibility

Designed for PrestaShop 1.7, 8.x and 9.x, with a PHP 5.6-compatible module entry point and separate legacy/modern currency formatting. Minimum PHP requirements otherwise follow the installed PrestaShop release. Runtime validation results are recorded under Development; the compatibility range does not mean every minor release has been tested.

Classic, Warehouse and Hummingbird expose the required `displayProductPriceBlock` hook with `type=weight` and `hook_origin=product_sheet`. The block stays within the refreshed product price fragment. JavaScript places it at the bottom of the complete price block, after tax labels, delivery information and other price details. Parent themes and core files are not modified. Custom themes must expose the same hook within their price fragment and may need selector adaptation in `views/js/front.js`.

This first version covers product details and quick views, not catalog cards, cart totals or checkout summaries. There are no configuration values or database tables.

## Installation

Copy `tec_dualprice` into the shop's `modules` directory and install it in the back office. No configuration is required. Uninstalling removes the display hooks; product and tax data are unchanged.

## Price updates and integrations

Combination, quantity and other native AJAX refreshes regenerate both prices in the same response. `tec_vatrates` saves the VAT choice then emits `updateProduct`, so its selected tax rules are applied by the native engine on refresh. The extra block is deliberately outside the tax label which `tec_vatrates` replaces.

A module that changes prices through the native engine and refreshes the product needs no special integration. A module that only rewrites text in the browser must also supply the complementary amount: the displayed gross amount alone cannot identify a changed tax rate, an exemption or differently taxed ecotax. The observer hides an outdated additional amount instead of guessing a tax calculation.

Browser integrations can dispatch a bubbling `tecDualPrice:update` CustomEvent from the relevant `.js-product-prices` element after changing the primary price. Its `detail.price` must contain the complementary price already calculated and formatted by that integration. The module inserts this as plain text, never HTML. Tax-mode changes require a full product refresh to regenerate the correct label. Prefer PrestaShop's `updateProduct` event whenever possible.

The browser receives only a product ID and the visible formatted price/label, not the full product payload. DOM mutations are batched into an animation frame; no polling or additional price endpoint is used.

## Development

Run PHP syntax checks and `node --check views/js/front.js`. Run the standalone behavior checks with `php tests/price-hook.php` and `node tests/browser.cjs` (the latter uses Playwright supplied by the workspace, not shipped with the module).

Development checks on 2026-10-02: source installation and rendering on PrestaShop 9.0 (Classic) and 9.1 (Warehouse); real `tec_vatrates` selection from 22% to 4% (23.33 EUR to 19.88 EUR gross, 19.12 EUR net unchanged); combination refresh after the VAT change. Standalone tests cover both tax display modes, quantity discounts, zero tax, invalid input, browser placement, quick-view insertion and the browser integration event. The entry point also passed syntax checking in the PrestaShop 1.7.8 container; PrestaShop 8.2 (Warehouse) was also checked for rendering below the complete price block and combination refresh. Full 1.7 runtime compatibility remains to be verified.

Before release, verify both tax display modes, combinations with different prices, quantity discounts, zero-rated items, currency/address changes, ecotax, customizations, quick views and `tec_vatrates` selection/reset in the target shop. Installation/upgrade from a release ZIP and PrestaShop validator checks are manual owner controls; they have not been performed for version 1.0.0.

## Support and license

Website: https://www.tecnoacquisti.com

Support: helpdesk@tecnoacquisti.com

Help center: https://help.tecnoacquisti.com

MIT License. See LICENSE.md for the complete license text. PrestaShop, Smarty and browser APIs are environment dependencies; no third-party runtime library is bundled.
