import React from 'react';

export default function CommunicationConsentFields({ values, onChange }) {
  const fields = [
    { name: 'aac_email_marketing_opt_in', label: 'Yes, I’d like to receive occasional emails from the American Alpine Club featuring climbing stories, events, member benefits, and opportunities to get involved.' },
    { name: 'aac_sms_marketing_opt_in', label: 'Yes, I’d like to receive occasional marketing text messages from the American Alpine Club featuring climbing stories, events, and opportunities to get involved. Message and data rates may apply.' },
  ];
  return (
    <div className="grid grid-cols-1 gap-5 border-t border-stone-200 pt-5 md:grid-cols-2">
      {fields.map(({ name, label }) => (
        <label key={name} className="flex cursor-pointer items-start gap-3 text-base leading-relaxed text-black">
          <input
            type="checkbox"
            name={name}
            checked={['1', 'true', 'yes', 'on'].includes(String(values[name]).toLowerCase())}
            onChange={(event) => onChange({ [name]: event.target.checked })}
            className="aac-consent-checkbox"
          />
          <span>{label}</span>
        </label>
      ))}
    </div>
  );
}
