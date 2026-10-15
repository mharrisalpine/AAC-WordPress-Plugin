/* Keep PMPro's own validation/payment handlers; refresh before they receive submit. */
(() => {
  const config = window.aacCheckoutSecurity;
  if (!config) return;
  let pending = false;
  let replay = false;
  let readyUntil = 0;

  const syncStateControl = () => {
    const country = document.querySelector('#bcountry, #pmpro_scountry');
    const current = document.querySelector('#bstate, #pmpro_sstate');
    if (!country || !current) return;

    const options = config.states?.[country.value] || {};
    const shouldSelect = Object.keys(options).length > 0;
    const currentValue = current.value || '';
    let replacement = current;

    if (shouldSelect && current.tagName !== 'SELECT') {
      replacement = document.createElement('select');
      replacement.append(new Option('Select state', ''));
      Object.entries(options).forEach(([value, label]) => replacement.append(new Option(label, value)));
    } else if (!shouldSelect && current.tagName === 'SELECT') {
      replacement = document.createElement('input');
      replacement.type = 'text';
    }

    if (replacement !== current) {
      replacement.id = current.id;
      replacement.name = current.name;
      replacement.required = current.required;
      replacement.autocomplete = current.autocomplete;
      replacement.className = current.className
        .replace(/pmpro_form_input-(?:text|select)/g, '')
        .trim();
      replacement.classList.add(`pmpro_form_input-${shouldSelect ? 'select' : 'text'}`);
      current.replaceWith(replacement);
      replacement.closest('.pmpro_form_field')?.classList.toggle('pmpro_form_field-select', shouldSelect);
      replacement.closest('.pmpro_form_field')?.classList.toggle('pmpro_form_field-text', !shouldSelect);
    }

    if (shouldSelect) {
      replacement.value = Object.prototype.hasOwnProperty.call(options, currentValue)
        ? currentValue
        : (Object.entries(options).find(([, label]) => label === currentValue)?.[0] || '');
    } else {
      replacement.value = currentValue;
    }
  };

  syncStateControl();
  document.addEventListener('DOMContentLoaded', syncStateControl, { once: true });
  document.addEventListener('change', event => {
    if (event.target?.matches('#bcountry, #pmpro_scountry')) syncStateControl();
  });

  const parseMoney = value => {
    const amount = Number.parseFloat(String(value || '').replace(/[^0-9.-]+/g, ''));
    return Number.isFinite(amount) ? amount : 0;
  };
  const formatMoney = amount => new Intl.NumberFormat('en-US', {
    style: 'currency', currency: 'USD', minimumFractionDigits: 2, maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);

  const syncMagazineOrderSummary = () => {
    const selection = document.querySelector('.aac-magazine-selection');
    const summary = document.getElementById('pmpro_pricing_fields');
    if (!selection || !summary) return;

    const marker = selection.querySelector('input[name="aac_magazine_addons_present"]');
    if (marker) marker.value = '1';

    const rows = summary.querySelector('.aac-checkout-summary-rows');
    const totalRow = rows?.querySelector('.aac-checkout-summary-row--total');
    const totalAmount = totalRow?.querySelector('strong');
    if (!rows || !totalRow || !totalAmount) return;

    if (!summary.dataset.aacMagazineBaseTotal) {
      summary.dataset.aacMagazineBaseTotal = String(parseMoney(totalAmount.textContent));
    }
    rows.querySelectorAll('[data-aac-magazine-line]').forEach(row => row.remove());

    let magazineTotal = 0;
    selection.querySelectorAll('input[name^="aac_magazine_choice["]:checked').forEach(input => {
      const amount = parseMoney(input.dataset.aacMagazinePrice);
      if (amount <= 0) return;
      magazineTotal += amount;
      const row = document.createElement('div');
      row.className = 'aac-checkout-summary-row';
      row.dataset.aacMagazineLine = 'true';
      const label = document.createElement('span');
      label.textContent = input.dataset.aacMagazineLabel || 'Magazine subscription';
      const price = document.createElement('strong');
      price.textContent = formatMoney(amount);
      row.append(label, price);
      rows.insertBefore(row, totalRow);
    });

    const baseTotal = parseMoney(summary.dataset.aacMagazineBaseTotal);
    totalAmount.textContent = formatMoney(baseTotal + magazineTotal);
  };

  const scheduleMagazineOrderSummary = () => {
    syncMagazineOrderSummary();
    [40, 150, 350, 800].forEach(delay => window.setTimeout(syncMagazineOrderSummary, delay));
  };

  const bindMagazineOrderSummary = () => {
    const selection = document.querySelector('.aac-magazine-selection');
    if (!selection) return;
    selection.querySelectorAll('input[name^="aac_magazine_choice["]').forEach(input => {
      if (input.dataset.aacExternalSummaryBound === 'true') return;
      input.addEventListener('change', scheduleMagazineOrderSummary);
      input.dataset.aacExternalSummaryBound = 'true';
    });
    scheduleMagazineOrderSummary();
  };

  bindMagazineOrderSummary();
  document.addEventListener('DOMContentLoaded', bindMagazineOrderSummary, { once: true });
  window.addEventListener('load', bindMagazineOrderSummary, { once: true });
  document.addEventListener('click', event => {
    if (event.target?.closest('.aac-checkout-wizard__next')) scheduleMagazineOrderSummary();
  });
  document.addEventListener('submit', event => {
    if (event.target?.matches('form#pmpro_form')) syncMagazineOrderSummary();
  }, true);

  const showError = (form, message) => {
    let notice = form.querySelector('[data-aac-security-error]');
    if (!notice) {
      notice = document.createElement('div');
      notice.dataset.aacSecurityError = 'true';
      notice.className = 'pmpro_message pmpro_error';
      notice.setAttribute('role', 'alert');
      form.prepend(notice);
    }
    notice.textContent = message;
    notice.scrollIntoView({ block: 'center', behavior: 'smooth' });
  };
  async function intercept(event) {
    const button = event.type === 'click' ? event.target.closest('button, input[type="submit"], input[type="image"]') : null;
    const form = event.type === 'submit' ? event.target : button?.form;
    if (!form?.matches('form#pmpro_form') || (button && !['submit', 'image'].includes(button.type))) return;
    if (replay || Date.now() < readyUntil) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    if (pending) return;
    pending = true;
    form.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(config.url, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        body: new URLSearchParams({ action: 'aac_refresh_pmpro_checkout_nonce' }),
        // WordPress may take more than 15 seconds to bootstrap on this host.
        signal: AbortSignal.timeout(60000),
      });
      const result = await response.json();
      if (!response.ok || !result.success || !result.data?.nonce) throw new Error('refresh');
      if (result.data.context !== config.context) {
        showError(form, 'Your sign-in session changed while this form was open. Please reload and confirm which account you are using before checking out. No payment was submitted by this attempt.');
        return;
      }
      const tokens = form.querySelectorAll('[name="pmpro_checkout_nonce"]');
      if (!tokens.length) throw new Error('missing token');
      tokens.forEach(input => { input.value = result.data.nonce; });
      form.querySelectorAll('[name="aac_communications_nonce"]').forEach(input => { input.value = result.data.communicationsNonce; });
      form.querySelector('[data-aac-security-error]')?.remove();
      readyUntil = Date.now() + 30000;
      replay = true;
      try {
        if (button) button.click();
        else form.requestSubmit(event.submitter || undefined);
      } finally { replay = false; }
    } catch (error) {
      const timedOut = error?.name === 'TimeoutError' || error?.name === 'AbortError';
      showError(form, timedOut
        ? 'Checkout security took too long to respond. Your entries are still here and no payment was submitted. Please try again.'
        : 'We could not refresh checkout security. Your entries are still here and no payment was submitted. Please try again.');
    } finally {
      pending = false;
      form.removeAttribute('aria-busy');
    }
  }
  document.addEventListener('click', intercept, true);
  document.addEventListener('submit', intercept, true);
})();
