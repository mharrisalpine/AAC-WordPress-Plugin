/**
 * Prompt members to verify / renew when renewal is within this many days (or already past).
 */
export const RENEWAL_PROMPT_DAYS = 90;

export function getDaysUntilRenewal(renewalDateStr) {
  if (!renewalDateStr || typeof renewalDateStr !== 'string') {
    return null;
  }
  const end = new Date(`${renewalDateStr.trim()}T23:59:59`);
  if (Number.isNaN(end.getTime())) {
    return null;
  }
  const now = new Date();
  return Math.ceil((end.getTime() - now.getTime()) / 86400000);
}

/** @param {object | null | undefined} profile */
export function shouldPromptMembershipVerification(profile) {
  const rd = profile?.profile_info?.renewal_date;
  const days = getDaysUntilRenewal(rd);
  if (days === null) {
    return false;
  }
  return days <= RENEWAL_PROMPT_DAYS;
}
