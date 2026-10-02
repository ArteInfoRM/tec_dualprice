/**
 * Copyright 2026 Arte e Informatica di Loris Modena e C. s.a.s.
 * @license https://opensource.org/licenses/MIT MIT License
 */
(() => {
  const states = new WeakMap();
  let scheduled = false;
  const blocks = '.js-product-prices, .product-prices, .product__prices';
  const prices = '.current-price-value, .current-price .product-price, .product__current-price, .current-price';

  const synchronize = () => {
    scheduled = false;
    document.querySelectorAll('.tec-dualprice').forEach((secondary) => {
      const block = secondary.closest(blocks);
      const primary = block && block.querySelector(prices);
      if (!primary) return;

      const fingerprint = primary.textContent.trim();
      const previous = states.get(secondary);
      if (previous && (previous.primary !== primary || previous.fingerprint !== fingerprint)) {
        // A browser-only change has no trustworthy tax information. Hide stale output.
        secondary.hidden = true;
        return;
      }
      if (!previous) states.set(secondary, { primary, fingerprint });

      // Keep the complementary price after tax, delivery and other price details.
      if (block.lastElementChild !== secondary) {
        block.append(secondary);
      }
    });
  };

  const schedule = () => {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(synchronize);
  };

  const start = () => {
    synchronize();
    const observer = new MutationObserver((mutations) => {
      if (mutations.some((mutation) => {
        const target = mutation.target.nodeType === 1
          ? mutation.target : mutation.target.parentElement;
        return (target && target.closest(blocks))
          || Array.from(mutation.addedNodes).some((node) => node.nodeType === 1
            && (node.matches(blocks) || node.querySelector('.tec-dualprice')));
      })) schedule();
    });
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });

    if (window.prestashop && typeof window.prestashop.on === 'function') {
      window.prestashop.on('updatedProduct', schedule);
    }

    // Browser-only pricing integrations must provide their calculated, formatted price.
    document.addEventListener('tecDualPrice:update', (event) => {
      const detail = event.detail;
      const block = event.target instanceof Element ? event.target.closest(blocks) : null;
      if (!block || !detail || typeof detail.price !== 'string'
        || detail.price.length === 0 || detail.price.length > 100) return;
      const secondary = block.querySelector('.tec-dualprice');
      const primary = block.querySelector(prices);
      if (!secondary || !primary) return;
      secondary.querySelector('.tec-dualprice__amount').textContent = detail.price;
      states.set(secondary, { primary, fingerprint: primary.textContent.trim() });
      secondary.hidden = false;
      schedule();
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
