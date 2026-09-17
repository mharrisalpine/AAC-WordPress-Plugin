// Stripe amounts use minor units; ISK and UGX retain two-decimal API amounts.
export const formatUpcomingAmount = (amount, currency) => {
  if (!Number.isInteger(amount) || !/^[A-Z]{3}$/.test(currency || '')) return 'Unavailable';
  const zeroDecimal = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
  const threeDecimal = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];
  const divisor = zeroDecimal.includes(currency) ? 1 : threeDecimal.includes(currency) ? 1000 : 100;
  try {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency, currencyDisplay: 'code' }).format(amount / divisor);
  } catch {
    return 'Unavailable';
  }
};

export const formatUpcomingDate = (date) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date || '')) return 'Unavailable';
  const parsed = new Date(`${date}T12:00:00Z`);
  if (Number.isNaN(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== date) return 'Unavailable';
  return new Intl.DateTimeFormat('en-US', { dateStyle: 'long', timeZone: 'UTC' }).format(parsed);
};
