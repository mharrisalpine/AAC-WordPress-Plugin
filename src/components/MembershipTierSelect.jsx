import React from 'react';
import { Check } from 'lucide-react';
import { MEMBERSHIP_TIER_OPTIONS, isOneTimeMembershipTierId, isPublicMembershipTierId } from '@/lib/membershipTiers';
import { cn } from '@/lib/utils';

function TierBenefitsList({ benefits, dense }) {
  if (!benefits?.length) {
    return null;
  }
  return (
    <ul className={cn('mt-3 space-y-2 text-left', dense ? 'text-[11px] leading-snug' : 'text-sm')}>
      {benefits.map((line) => (
        <li key={line} className="flex gap-2 text-stone-700">
          <Check
            className={cn(
              'mt-0.5 shrink-0 text-[#6b5310]',
              dense ? 'h-3.5 w-3.5' : 'h-4 w-4',
            )}
            strokeWidth={2.5}
            aria-hidden
          />
          <span>{line}</span>
        </li>
      ))}
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
  const visibleTiers = MEMBERSHIP_TIER_OPTIONS.filter((tier) => isPublicMembershipTierId(tier.id));

  if (variant === 'full') {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {visibleTiers.map((t) => {
          const selected = selectedId === t.id;
          const priceLabel =
            t.priceCents === 0
              ? 'Free'
              : `$${(t.priceCents / 100).toLocaleString('en-US', { maximumFractionDigits: 0 })}`;
          return (
            <button
              key={t.id}
              type="button"
              onClick={() => onSelect(t.id)}
              className={cn(
                'flex min-h-[340px] flex-col rounded-2xl border p-6 text-left shadow-sm transition',
                selected
                  ? 'border-[#c8a43a] bg-[rgba(200,164,58,0.12)] ring-2 ring-[#c8a43a] ring-offset-2 ring-offset-[#faf8f5]'
                  : 'border-stone-200 bg-white hover:border-stone-300 hover:shadow-md',
              )}
            >
              <div className="flex flex-1 flex-col">
                <span className="text-xl font-bold text-stone-900">{t.label}</span>
                <span className="mt-2 text-3xl font-semibold tracking-tight text-[#a07f21]">
                  {priceLabel}
                  {t.priceCents === 0 ? null : isOneTimeMembershipTierId(t.id) ? (
                    <span className="text-base font-medium text-stone-500"> one-time</span>
                  ) : (
                    <span className="text-base font-medium text-stone-500">/yr</span>
                  )}
                </span>
                <p className="mt-3 text-sm leading-relaxed text-stone-600">{t.blurb}</p>
                <TierBenefitsList benefits={t.benefits} />
                {selected ? (
                  <span className="mt-4 text-xs font-semibold uppercase tracking-wide text-[#6b5310]">Selected</span>
                ) : (
                  <span className="mt-4 text-xs font-medium text-stone-400">Tap to select</span>
                )}
              </div>
            </button>
          );
        })}
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
            <span className="text-sm font-medium text-[#c8a43a]">
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
