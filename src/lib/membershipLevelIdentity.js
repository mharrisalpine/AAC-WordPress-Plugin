// Benefit tiers can group several PMPro levels. Checkout must use level identity.
export function isCurrentMembershipLevel(actions, targetTier, currentLevelName) {
  const currentId = Number(actions?.current_level_id);
  const targetId = Number(actions?.levels?.[targetTier]?.level_id);
  if (currentId > 0 && targetId > 0) return currentId === targetId;
  return Boolean(currentLevelName) && currentLevelName === targetTier;
}
