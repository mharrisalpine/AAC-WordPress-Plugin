import React, { useEffect, useState } from 'react';
import { getUpcomingPayment } from '@/lib/memberApi';
import { formatUpcomingAmount, formatUpcomingDate } from '@/lib/upcomingPayment';

export default function UpcomingPayment({ subscriptionId }) {
  const cacheKey = `aac_upcoming_payment_v1_${subscriptionId || 'current'}`;
  const [payment, setPayment] = useState(() => {
    try {
      const cached = JSON.parse(window.sessionStorage.getItem(cacheKey) || 'null');
      if (cached && Date.now() - Number(cached.savedAt || 0) < 300000 && cached.payment) return cached.payment;
    } catch (_error) {
      window.sessionStorage.removeItem(cacheKey);
    }
    return { status: 'loading' };
  });
  useEffect(() => {
    let active = true;
    let hasFreshCache = false;
    try {
      const cached = JSON.parse(window.sessionStorage.getItem(cacheKey) || 'null');
      hasFreshCache = Boolean(cached && Date.now() - Number(cached.savedAt || 0) < 300000 && cached.payment);
      if (hasFreshCache) setPayment(cached.payment);
    } catch (_error) {
      window.sessionStorage.removeItem(cacheKey);
    }
    if (!hasFreshCache) setPayment({ status: 'loading' });
    getUpcomingPayment()
      .then((data) => {
        if (!active) return;
        const nextPayment = data.upcoming_payment || { status: 'unavailable' };
        setPayment(nextPayment);
        window.sessionStorage.setItem(cacheKey, JSON.stringify({ savedAt: Date.now(), payment: nextPayment }));
      })
      .catch(() => { if (active) setPayment({ status: 'unavailable' }); });
    return () => { active = false; };
  }, [cacheKey]);
  if (payment.status === 'hidden') return null;
  return (
    <section className="mt-6 border-t-2 border-[#b71c1c] bg-white py-5" aria-labelledby="upcoming-payment-heading" aria-live="polite">
      <h2 id="upcoming-payment-heading" className="text-xl font-bold text-black">Upcoming Payment</h2>
      {payment.status === 'loading' ? <p className="mt-3 text-stone-600">Loading upcoming payment…</p>
        : payment.status !== 'ready' ? <p className="mt-3 text-stone-600">Upcoming payment information is unavailable right now. Please try again later.</p>
          : <>
            <dl className="mt-4 grid gap-5 sm:grid-cols-3">
              {[
                ['Membership Level', payment.level || 'Unavailable'],
                ['Amount', formatUpcomingAmount(payment.amount_minor, payment.currency)],
                ['Scheduled Charge Date', formatUpcomingDate(payment.charge_date)],
              ].map(([label, value]) => <div key={label}><dt className="text-sm text-stone-600">{label}</dt><dd className="mt-1 text-lg font-bold text-black">{value}</dd></div>)}
            </dl>
            <p className="mt-4 text-sm text-stone-600">Preview of your next automatic payment. The amount or date may change if your subscription, discounts, or taxes change.</p>
          </>}
    </section>
  );
}
