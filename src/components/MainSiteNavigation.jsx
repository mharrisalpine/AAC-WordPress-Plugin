import React from 'react';
import { ChevronDown } from 'lucide-react';
import { AAC_MAIN_NAV, mainSiteHref, resolveNavChildHref } from '@/lib/mainWebsiteNav';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { cn } from '@/lib/utils';

const desktopLinkBase =
  'group inline-flex items-center gap-1 py-3 text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-white/84 transition-colors hover:text-[#f8c235]';
const dropdownPanel =
  'rounded-[1.75rem] border border-white/12 bg-[#0b0908]/95 p-5 shadow-[0_28px_80px_rgba(0,0,0,0.45)] ring-1 ring-white/8 backdrop-blur';
const dropdownLink =
  'block rounded-2xl px-4 py-3 text-sm font-medium text-[#f4efe7] transition-colors hover:bg-white/8 hover:text-[#f8c235]';

function NavLink({ href, external, className, children }) {
  return (
    <a
      href={href}
      className={className}
      {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
    >
      {children}
    </a>
  );
}

/**
 * Desktop + mobile navigation aligned with americanalpineclub.org (dark bar variant).
 */
export function MainSiteNavigation({ className }) {
  const navSections = getPortalUiSettings().navigation.topNavSections || AAC_MAIN_NAV;

  return (
    <nav className={cn('flex items-center', className)} aria-label="American Alpine Club website">
      <ul className="hidden flex-wrap items-center gap-7 xl:flex">
        {navSections.map((section) => {
          if (section.type === 'link') {
            return (
              <li key={section.label} className="flex items-center">
                <NavLink href={section.href} external={section.external} className={desktopLinkBase}>
                  <span className="relative pb-1">
                    {section.label}
                    <span className="absolute inset-x-0 bottom-0 h-px origin-left scale-x-0 bg-[#f8c235] transition-transform duration-200 group-hover:scale-x-100" />
                  </span>
                </NavLink>
              </li>
            );
          }

          return (
            <li key={section.label} className="group relative flex items-center">
              <a
                href={section.href || mainSiteHref(section.path)}
                className={cn(desktopLinkBase, 'relative')}
              >
                <span className="relative pb-1">
                  {section.label}
                  <span className="absolute inset-x-0 bottom-0 h-px origin-left scale-x-0 bg-[#f8c235] transition-transform duration-200 group-hover:scale-x-100 group-focus-within:scale-x-100" />
                </span>
                <ChevronDown
                  className="h-4 w-4 opacity-80 transition-transform group-hover:rotate-180 group-focus-within:rotate-180"
                  aria-hidden
                />
              </a>
              <div
                className={cn(
                  'invisible absolute left-0 top-full z-[80] min-w-[18rem] max-w-[22rem] pt-3 opacity-0 transition-all duration-150',
                  'group-hover:visible group-hover:opacity-100',
                  'group-focus-within:visible group-focus-within:opacity-100',
                )}
              >
                <div className={dropdownPanel}>
                  <span className="mb-3 block px-4 text-[0.68rem] font-semibold uppercase tracking-[0.25em] text-[#f8c235]">
                    {section.label}
                  </span>
                  <ul className="space-y-1">
                    <li>
                      <NavLink
                        href={section.href || mainSiteHref(section.path)}
                        className={cn(dropdownLink, 'font-semibold text-white')}
                      >
                        View all
                      </NavLink>
                    </li>
                    {section.children.map((child) => (
                      <li key={child.label}>
                        <NavLink href={child.href || resolveNavChildHref(child)} external={child.external} className={dropdownLink}>
                          {child.label}
                        </NavLink>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </li>
          );
        })}
      </ul>

      <div className="flex w-full flex-col gap-2 xl:hidden">
        {navSections.map((section) => {
          if (section.type === 'link') {
            return (
              <NavLink
                key={section.label}
                href={section.href}
                external={section.external}
                className="flex items-center justify-between rounded-[1.2rem] border border-white/10 bg-white/[0.03] px-4 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-white/88 transition-colors hover:border-[#f8c235]/40 hover:text-[#f8c235]"
              >
                {section.label}
              </NavLink>
            );
          }
          return (
            <div key={section.label} className="relative">
              <details className="group overflow-hidden rounded-[1.2rem] border border-white/10 bg-white/[0.03]">
                <summary
                  className="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-white/88 marker:hidden [&::-webkit-details-marker]:hidden"
                >
                  <span>{section.label}</span>
                  <ChevronDown className="h-4 w-4 opacity-80 transition-transform group-open:rotate-180" aria-hidden />
                </summary>
                <ul className="space-y-1 border-t border-white/8 px-2 py-2">
                  <li>
                    <NavLink
                      href={section.href || mainSiteHref(section.path)}
                      className={cn(dropdownLink, 'font-semibold text-white')}
                    >
                      {section.label} overview
                    </NavLink>
                  </li>
                  {section.children.map((child) => (
                    <li key={child.label}>
                        <NavLink href={child.href || resolveNavChildHref(child)} external={child.external} className={dropdownLink}>
                          {child.label}
                        </NavLink>
                    </li>
                  ))}
                </ul>
              </details>
            </div>
          );
        })}
      </div>
    </nav>
  );
}
