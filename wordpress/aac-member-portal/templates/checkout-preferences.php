<?php
if (!defined('ABSPATH')) { exit; }
$checkout_consent_defaults = [];
foreach (['aac_email_marketing_opt_in', 'aac_sms_marketing_opt_in'] as $key) {
    $checkout_consent_defaults[$key] = isset($_POST[$key . '_present'])
        ? filter_var($_POST[$key] ?? false, FILTER_VALIDATE_BOOLEAN)
        : filter_var(get_user_meta(get_current_user_id(), $key, true), FILTER_VALIDATE_BOOLEAN);
}
?>
const bindCheckoutPreferences = () => {
    const form = document.getElementById('pmpro_form');
    if (!form || form.dataset.aacPreferencesBound) return;
    form.dataset.aacPreferencesBound = 'true';
    const defaults = <?php echo wp_json_encode($checkout_consent_defaults); ?>;
    // Upgrade checkout must not submit consent controls or opt-out markers.
    // Omitting both preserves the member's saved contact preferences.
    const isUpgradeCheckout = <?php echo wp_json_encode(sanitize_key(wp_unslash($_GET['aac_membership_action'] ?? '')) === 'upgrade'); ?>;
    const fields = isUpgradeCheckout ? [] : [
        ['aac_email_marketing_opt_in', 'Yes, I’d like to receive occasional emails from the American Alpine Club featuring climbing stories, events, member benefits, and opportunities to get involved.'],
        ['aac_sms_marketing_opt_in', 'Yes, I’d like to receive occasional marketing text messages from the American Alpine Club featuring climbing stories, events, and opportunities to get involved. Message and data rates may apply.'],
    ];
    let fallback;
    fields.forEach(([name, text]) => {
        let control = form.querySelector(`input[type="checkbox"][name="${name}"]`);
        if (!control) {
            if (!fallback) {
                fallback = document.createElement('div');
                fallback.className = 'pmpro_form_fieldset';
                const heading = document.createElement('h2');
                heading.className = 'pmpro_form_heading';
                heading.textContent = 'Contact Preferences (optional)';
                fallback.appendChild(heading);
                const anchor = form.querySelector('#pmpro_billing_address_fields');
                if (anchor) anchor.appendChild(fallback);
                else form.appendChild(fallback);
            }
            const row = document.createElement('p');
            const label = document.createElement('label');
            control = document.createElement('input');
            control.type = 'checkbox';
            control.name = name;
            control.value = '1';
            control.checked = Boolean(defaults[name]);
            label.append(control, document.createTextNode(` ${text}`));
            row.appendChild(label);
            fallback.appendChild(row);
        }
        const marker = document.createElement('input');
        marker.type = 'hidden';
        marker.name = `${name}_present`;
        marker.value = '1';
        form.appendChild(marker);
    });
    // Query current controls each time: the checkout wizard can replace nodes.
    const syncShirt = () => {
        const visible = form.querySelector('select[name="t_shirt_sizet_shirt"]');
        const canonical = form.querySelector('input[type="hidden"][name="t_shirt"]');
        if (visible && canonical) canonical.value = visible.value;
    };
    form.addEventListener('change', syncShirt);
    form.addEventListener('submit', syncShirt, true);
    syncShirt();
};

const bindCheckoutRequiredMarkers = () => {
    const form = document.getElementById('pmpro_form');
    if (!form || form.dataset.aacRequiredMarkersBound) return;
    form.dataset.aacRequiredMarkersBound = 'true';
    const standard = new Set(['bfirstname', 'blastname', 'baddress1', 'bcity', 'bstate', 'bzipcode', 'bcountry', 'bemail', 'bconfirmemail', 'password']);
    const refresh = () => {
        form.querySelectorAll('input:not([type="hidden"]),select,textarea').forEach(control => {
            const field = control.closest('.pmpro_form_field');
            const label = control.id && form.querySelector(`label[for="${control.id}"]`);
            if (!label) return;
            const optional = /shirt|phone|birthdate|marketing_opt_in|university|school/.test(control.name);
            if (optional) {
                control.required = false;
                control.removeAttribute('aria-required');
            }
            const required = !optional && !control.disabled && (standard.has(control.name) || control.required || control.getAttribute('aria-required') === 'true' || field?.classList.contains('pmpro_form_field-required'));
            if (required && standard.has(control.name)) {
                control.required = true;
                control.setAttribute('aria-required', 'true');
            }
            let star = label.querySelector('.pmpro_asterisk');
            if (required && !star) {
                star = document.createElement('span');
                star.className = 'pmpro_asterisk';
                star.textContent = ' *';
                star.setAttribute('aria-hidden', 'true');
                label.appendChild(star);
            } else if (!required && star) star.remove();
        });
        // Stripe Elements owns validation inside its secure iframes.
        form.querySelectorAll('#pmpro_payment_information_fields .pmpro_form_field').forEach(field => {
            const label = field.querySelector('label');
            if (field.querySelector('iframe') && label && !label.querySelector('.pmpro_asterisk')) {
                const star = document.createElement('span');
                star.className = 'pmpro_asterisk';
                star.textContent = ' *';
                label.appendChild(star);
            }
        });
    };
    let pending = false;
    const schedule = () => {
        if (pending) return;
        pending = true;
        requestAnimationFrame(() => { pending = false; refresh(); });
    };
    form.addEventListener('change', schedule);
    new MutationObserver(schedule).observe(form, {childList: true, subtree: true});
    refresh();
};
