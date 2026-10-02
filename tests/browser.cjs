/**
 * Copyright 2026 Arte e Informatica di Loris Modena e C. s.a.s.
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const secondary = '<div class="tec-dualprice"><span class="tec-dualprice__amount">100.00 EUR</span><span class="tec-dualprice__label">VAT excluded</span></div>';
    await page.setContent(`<div class="js-product-prices"><div class="current-price"><span class="current-price-value">122.00 EUR</span></div>${secondary}<div class="tax-shipping-delivery-label">VAT included</div></div>`);
    await page.evaluate(() => {
      window.prestashop = {
        events: {},
        on(name, callback) { this.events[name] = callback; },
        emit(name) { if (this.events[name]) this.events[name](); },
      };
    });
    await page.addScriptTag({ path: path.join(__dirname, '../views/js/front.js') });
    await page.waitForFunction(() => document.querySelector('.tax-shipping-delivery-label').nextElementSibling?.matches('.tec-dualprice'));
    await page.evaluate(() => {
      document.querySelector('.tax-shipping-delivery-label').innerHTML = 'Reduced VAT';
    });
    assert.equal(await page.locator('.tec-dualprice').isVisible(), true);
    await page.evaluate(() => {
      document.querySelector('.current-price-value').textContent = '104.00 EUR';
    });
    await page.waitForFunction(() => document.querySelector('.tec-dualprice').hidden);
    await page.evaluate(() => {
      document.querySelector('.js-product-prices').dispatchEvent(new CustomEvent('tecDualPrice:update', {
        bubbles: true, detail: { price: '100.00 EUR' },
      }));
    });
    assert.equal(await page.locator('.tec-dualprice').isVisible(), true);
    await page.evaluate((markup) => {
      document.querySelector('.js-product-prices').innerHTML = `<div class="current-price"><span class="current-price-value">156.00 EUR</span></div>${markup}`;
      document.querySelector('.tec-dualprice__amount').textContent = '150.00 EUR';
      window.prestashop.emit('updatedProduct');
    }, secondary);
    await page.waitForFunction(() => document.querySelector('.tec-dualprice').textContent.includes('150.00 EUR'));
    assert.equal(await page.locator('.tec-dualprice').isVisible(), true);
    await page.evaluate((markup) => {
      const modal = document.createElement('div');
      modal.id = 'quickview';
      modal.innerHTML = `<div class="js-product-prices"><div class="prices__wrapper"><div class="d-flex"><div class="product__current-price">122.00 EUR</div></div></div>${markup}</div>`;
      document.body.append(modal);
    }, secondary);
    await page.waitForFunction(() => document.querySelector('#quickview .js-product-prices').lastElementChild.matches('.tec-dualprice'));
    assert.equal(await page.locator('.tec-dualprice').count(), 2);
    await page.evaluate(() => {
      document.querySelector('#quickview .js-product-prices').dispatchEvent(new CustomEvent('tecDualPrice:update', {
        bubbles: true, detail: { price: '<img src=x onerror=alert(1)>' },
      }));
    });
    assert.equal(await page.locator('#quickview .tec-dualprice img').count(), 0);
    assert.equal(await page.locator('body > .js-product-prices .tec-dualprice__amount').textContent(), '150.00 EUR');
    assert.deepEqual(errors, []);
    process.stdout.write('Browser checks passed: placement, VAT label replacement, stale amount, integration event, refresh, quick view, text safety.\n');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  process.stderr.write(`${error.stack}\n`);
  process.exitCode = 1;
});
