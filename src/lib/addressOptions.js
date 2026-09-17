// Use the same value/label maps supplied to PMPro signup. Match legacy names
// without silently overwriting them when an unrelated profile field is saved.
export const resolveAddressCode = (value, options = {}) => {
  const current = String(value || '').trim();
  const match = Object.entries(options).find(([code, label]) =>
    code.toLowerCase() === current.toLowerCase() ||
    String(label).toLowerCase() === current.toLowerCase()
  );
  return match ? match[0] : current;
};

export const addressSelectOptions = (options = {}, value = '') => {
  const entries = Object.entries(options);
  const code = resolveAddressCode(value, options);
  return code && !Object.prototype.hasOwnProperty.call(options, code)
    ? [[code, String(value)], ...entries]
    : entries;
};
