/* Keep PMPro's own validation/payment handlers; refresh before they receive submit. */
(() => {
  const config = window.aacCheckoutSecurity;
  if (!config) return;
  let pending = false;
  let replay = false;
  let readyUntil = 0;
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
        signal: AbortSignal.timeout(15000),
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
      showError(form, 'We could not refresh checkout security. Your entries are still here. Please check your connection and try again.');
    } finally {
      pending = false;
      form.removeAttribute('aria-busy');
    }
  }
  document.addEventListener('click', intercept, true);
  document.addEventListener('submit', intercept, true);
})();
