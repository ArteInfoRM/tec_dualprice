# Changelog

## [1.0.0] - 2026-10-02

### Fixed

- Use the native price formatter adapter on PrestaShop 1.7 instead of directly calling the removed Tools::displayPrice method, avoiding the validator compatibility error on newer versions.

### Changed

- Change the module license from AFL-3.0 to MIT and update source headers and documentation. Rebuild version 1.0.0 with the MIT license and formatter compatibility fix.

### Added

- Complementary VAT price on product details and quick views.
- Native combination, quantity and reduced-VAT price refreshes.
- Placement at the bottom of the complete price block in Classic, Warehouse and Hummingbird, after the tax label and other price details.
- Protection against stale browser-only price changes and an explicit integration event.
- Italian translations for the module name, description and VAT labels.
