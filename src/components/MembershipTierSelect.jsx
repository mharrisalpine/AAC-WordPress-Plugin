import React from 'react';
import { Check } from 'lucide-react';
import { MEMBERSHIP_TIER_OPTIONS, isOneTimeMembershipTierId, isPublicMembershipTierId } from '@/lib/membershipTiers';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { cn } from '@/lib/utils';

function getPriceLabel(tier) {
  return tier.priceCents === 0
    ? 'Free'
    : `$${(tier.priceCents / 100).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
}

function getBenefitBaseLabel(value) {
  return String(value || '').trim().replace(/:\s*\$[\d,.]+\s*$/, '').trim();
}

function getBenefitCell(benefits, row) {
  const benefit = benefits.find((item) => getBenefitBaseLabel(item).toLocaleLowerCase() === row.label.toLocaleLowerCase());
  if (!benefit) return null;

  const detail = String(benefit).slice(getBenefitBaseLabel(benefit).length).replace(/^\s*[:\-–—]\s*/, '').trim();

  return { detail: detail.includes('$') ? detail : '' };
}

function TierBenefitsList({ benefits, dense, maxItems }) {
  if (!benefits?.length) {
    return null;
  }
  const visibleBenefits = Number.isInteger(maxItems) ? benefits.slice(0, maxItems) : benefits;
  const hiddenCount = Math.max(0, benefits.length - visibleBenefits.length);
  return (
    <ul className={cn('!ml-0 !mt-6 w-full min-w-0 max-w-full space-y-1.5 !pl-0 text-left !text-[11px] !leading-4')}>
      {visibleBenefits.map((line) => (
        <li key={line} className="flex min-w-0 max-w-full gap-2 px-2 py-1 text-[#514a40] odd:bg-white even:bg-stone-100">
          <Check
            className="mt-0.5 h-3 w-3 shrink-0 text-[#9e1b1e]"
            strokeWidth={2.5}
            aria-hidden
          />
          <span className="min-w-0 flex-1 break-words !text-[11px] !leading-4 [overflow-wrap:anywhere]">{line}</span>
        </li>
      ))}
      {hiddenCount > 0 ? (
        <li className="text-xs font-semibold uppercase tracking-[0.12em] text-[#8f877a]">
          + {hiddenCount} more benefits
        </li>
      ) : null}
    </ul>
  );
}

/**
 * @param {object} props
 * @param {string} props.selectedId
 * @param {(id: string) => void} props.onSelect
 * @param {'compact' | 'full'} [props.variant]
 */
export function MembershipTierSelect({ selectedId, onSelect, variant = 'compact' }) {
  const portalContent = getPortalUiSettings().content || {};
  const membershipLevelBenefits = portalContent.membershipLevelBenefits || {};
  const getTierBenefits = (tier) => {
    const benefits = Object.prototype.hasOwnProperty.call(membershipLevelBenefits, tier.id) && Array.isArray(membershipLevelBenefits[tier.id])
      ? membershipLevelBenefits[tier.id]
      : tier.benefits;

    return benefits;
  };
  const visibleTiers = MEMBERSHIP_TIER_OPTIONS
    .filter((tier) => isPublicMembershipTierId(tier.id))
    .map((tier) => ({
      ...tier,
      benefits: getTierBenefits(tier),
    }));
  const fallbackCatalog = visibleTiers.flatMap((tier) => tier.benefits || []);
  const configuredCatalog = Array.isArray(portalContent.membershipBenefitCatalog)
    ? portalContent.membershipBenefitCatalog
    : [];
  const matrixBenefitRows = [];
  const seenBenefitLabels = new Set();
  (configuredCatalog.length ? configuredCatalog : fallbackCatalog).forEach((benefit, index) => {
    const label = getBenefitBaseLabel(benefit);
    const normalized = label.toLocaleLowerCase();
    if (!label || seenBenefitLabels.has(normalized)) return;
    seenBenefitLabels.add(normalized);
    matrixBenefitRows.push({ id: `${normalized.replace(/[^a-z0-9]+/g, '-')}-${index}`, label });
  });

  if (variant === 'full') {
    return (
      <div role="radiogroup" aria-label="Membership level">
        <div className="grid grid-cols-1 gap-4 lg:hidden">
          {visibleTiers.map((tier) => {
            const selected = selectedId === tier.id;
            const priceLabel = getPriceLabel(tier);
            return (
              <button
                key={tier.id}
                type="button"
                onClick={() => onSelect(tier.id)}
                role="radio"
                aria-checked={selected}
                className={cn(
                  'aac-membership-tier-card relative flex min-h-0 flex-col rounded-none border bg-white p-5 text-left shadow-sm transition',
                  selected
                    ? 'border-[3px] border-[#ffc72c] shadow-md'
                    : 'border border-black hover:border-black hover:shadow-md',
                )}
              >
                <span
                  className={cn(
                    'absolute right-4 top-4 flex h-7 w-7 items-center justify-center rounded-full border-2 transition',
                    selected ? 'border-[#8f1515] bg-[#8f1515] text-white' : 'border-stone-300 bg-white text-transparent',
                  )}
                  aria-hidden
                >
                  <Check className="h-4 w-4" strokeWidth={3} />
                </span>
                <span className="pr-10 text-xl font-bold text-stone-900">{tier.label}</span>
                <span className="aac-membership-tier-card__price mt-2 text-[26px] font-semibold leading-8 tracking-tight text-[#8f1515]">
                  <span className="aac-membership-tier-card__price-value">{priceLabel}</span>
                  {tier.priceCents === 0 ? null : isOneTimeMembershipTierId(tier.id) ? (
                    <span className="text-sm font-medium text-stone-500"> one-time</span>
                  ) : (
                    <span className="text-sm font-medium text-stone-500">/yr</span>
                  )}
                </span>
                <p className="mt-2 pr-8 text-sm leading-5 text-stone-600">{tier.blurb}</p>
                <TierBenefitsList benefits={tier.benefits} />
                <span className={cn('mt-4 text-xs font-semibold uppercase tracking-wide', selected ? 'text-[#8f1515]' : 'text-stone-400')}>
                  {selected ? 'Selected' : `Select ${tier.label}`}
                </span>
              </button>
            );
          })}
        </div>

        <div className="hidden overflow-x-auto border border-black bg-white lg:block">
          <table className="w-full min-w-[980px] table-fixed border-collapse text-left">
            <caption className="sr-only">Compare and select an AAC membership level</caption>
            <colgroup>
              <col className="w-[28%]" />
              {visibleTiers.map((tier) => <col key={tier.id} />)}
            </colgroup>
            <thead>
              <tr>
                <th scope="col" className="border-b border-r border-stone-300 bg-stone-100 px-5 py-5 align-bottom">
                  <span className="text-xs font-bold uppercase tracking-[0.16em] text-stone-600">Benefits</span>
                </th>
                {visibleTiers.map((tier) => {
                  const selected = selectedId === tier.id;
                  return (
                    <th
                      key={tier.id}
                      scope="col"
                      className={cn(
                        'border-b border-r border-stone-300 p-0 align-top last:border-r-0',
                        selected ? 'bg-[#fff8dc]' : 'bg-white',
                      )}
                    >
                      <button
                        type="button"
                        onClick={() => onSelect(tier.id)}
                        role="radio"
                        aria-checked={selected}
                        className={cn(
                          'relative flex min-h-[176px] w-full flex-col items-center justify-center px-4 py-5 text-center transition hover:bg-stone-50 focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-3px] focus-visible:outline-[#8f1515]',
                          selected && 'bg-[#fff8dc] shadow-[inset_0_4px_0_#ffc72c]',
                        )}
                      >
                        <span
                          className={cn(
                            'mb-3 flex h-8 w-8 items-center justify-center rounded-full border-2',
                            selected ? 'border-[#8f1515] bg-[#8f1515] text-white' : 'border-stone-300 bg-white text-transparent',
                          )}
                          aria-hidden
                        >
                          <Check className="h-5 w-5" strokeWidth={3} />
                        </span>
                        <span className="text-xl font-bold text-stone-950">{tier.label}</span>
                        <span className="mt-2 text-[26px] font-bold leading-none text-[#8f1515]">
                          {getPriceLabel(tier)}
                          {tier.priceCents > 0 ? <span className="text-sm font-medium text-stone-500">/yr</span> : null}
                        </span>
                        <span className={cn('mt-3 text-[11px] font-bold uppercase tracking-[0.12em]', selected ? 'text-[#8f1515]' : 'text-stone-500')}>
                          {selected ? 'Selected' : `Select ${tier.label}`}
                        </span>
                      </button>
                    </th>
                  );
                })}
              </tr>
            </thead>
            <tbody>
              {matrixBenefitRows.map((row, rowIndex) => (
                <tr key={row.id} className={rowIndex % 2 ? 'bg-stone-50' : 'bg-white'}>
                  <th scope="row" className="border-r border-t border-stone-300 px-5 py-4 text-sm font-semibold leading-5 text-stone-800">
                    {row.label}
                  </th>
                  {visibleTiers.map((tier) => {
                    const benefit = getBenefitCell(tier.benefits, row);
                    const selected = selectedId === tier.id;
                    return (
                      <td
                        key={tier.id}
                        onClick={() => onSelect(tier.id)}
                        className={cn(
                          'cursor-pointer border-r border-t border-stone-300 px-3 py-4 text-center last:border-r-0',
                          selected && 'bg-[#fff8dc]',
                        )}
                      >
                        {benefit ? (
                          <span className="inline-flex flex-col items-center gap-1 text-sm font-semibold text-stone-800">
                            <Check className="h-5 w-5 text-[#8f1515]" strokeWidth={3} aria-label="Included" />
                            {benefit.detail ? <span className="text-xs leading-4 text-stone-600">{benefit.detail}</span> : null}
                          </span>
                        ) : (
                          <span className="text-stone-300" aria-label="Not included">—</span>
                        )}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {visibleTiers.map((t) => (
        <button
          key={t.id}
          type="button"
          onClick={() => onSelect(t.id)}
          className={cn(
            'rounded-xl border p-4 text-left transition',
            selectedId === t.id
              ? 'border-[#c8a43a] bg-[rgba(200,164,58,0.15)] ring-1 ring-[#c8a43a]'
              : 'border-stone-200 bg-stone-50 hover:border-stone-300',
          )}
        >
          <div className="flex items-baseline justify-between gap-2">
            <span className="font-semibold text-black">{t.label}</span>
            <span className="aac-membership-tier-card__price-value text-sm font-medium text-[#c8a43a]">
              {t.priceCents === 0 ? 'Free' : `$${(t.priceCents / 100).toFixed(0)}`}
            </span>
          </div>
          <p className="mt-1 text-xs text-black/65">{t.blurb}</p>
          <TierBenefitsList benefits={t.benefits} dense />
        </button>
      ))}
    </div>
  );
}
