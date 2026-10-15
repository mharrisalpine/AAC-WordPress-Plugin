<?php
// Only fresh, authenticated upgrade forms may use stored profile defaults.
if (!defined('ABSPATH')) { exit; }
?>
const fillUpgradeProfileDefaults = () => {
    if (!<?php echo wp_json_encode(is_user_logged_in() && strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && sanitize_key(wp_unslash($_GET['aac_membership_action'] ?? '')) === 'upgrade'); ?>) return;
    const fields = [
        ['country', ['pmpro_scountry', 'scountry', 'bcountry']],
        ['first_name', ['pmpro_sfirstname', 'sfirstname', 'first_name', 'bfirstname']],
        ['last_name', ['pmpro_slastname', 'slastname', 'last_name', 'blastname']],
        ['email', ['bemail', 'bconfirmemail']],
        ['phone', ['pmpro_sphone', 'sphone', 'bphone']],
        ['birthdate', ['birthdate']],
        ['street', ['pmpro_saddress1', 'saddress1', 'baddress1']],
        ['address2', ['pmpro_saddress2', 'saddress2', 'baddress2']],
        ['city', ['pmpro_scity', 'scity', 'bcity']],
        ['state', ['pmpro_sstate', 'sstate', 'bstate']],
        ['zip', ['pmpro_szipcode', 'szipcode', 'bzipcode']],
    ];
    // An explicit allowlist intentionally excludes discount eligibility and payment fields.
    fields.forEach(([key, names]) => {
        const saved = String(checkoutProfileDefaults[key] || '').trim();
        names.forEach((name) => {
            const input = document.querySelector(`[name="${name}"]`) || document.getElementById(name);
            if (!input || input.dataset.aacProfilePrefilled === 'true') return;
            input.dataset.aacProfilePrefilled = 'true';
            // Country selects have a default US value even when no member value was loaded.
            if (!saved || (key !== 'country' && String(input.value || '').trim())) return;
            let value = saved;
            if (input.tagName === 'SELECT') {
                const option = Array.from(input.options).find((item) =>
                    item.value.toLowerCase() === saved.toLowerCase() || item.textContent.trim().toLowerCase() === saved.toLowerCase());
                if (!option) return;
                value = option.value;
            }
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
};
