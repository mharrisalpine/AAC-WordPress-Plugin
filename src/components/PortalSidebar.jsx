import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { User, Shield, Settings, Store, Tag, Mic2, Users, Mail, ScrollText, BedDouble, PenSquare, BookOpen, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { getPortalUiSettings } from '@/lib/portalSettings';
import { isPartnerOrAboveMembershipTierId } from '@/lib/membershipTiers';
import { cn } from '@/lib/utils';

export function PortalNavLinks({ onNavigate, className }) {
  const { pathname } = useLocation();
  const { profile } = useAuth();
  const portalSections = getPortalUiSettings().navigation.sidebarSections;
  const canAccessPublications = isPartnerOrAboveMembershipTierId(profile?.profile_info?.tier);
  const iconRegistry = {
    user: User,
    store: Store,
    shield: Shield,
    settings: Settings,
    tag: Tag,
    mic: Mic2,
    users: Users,
    mail: Mail,
    'scroll-text': ScrollText,
    bed: BedDouble,
    pen: PenSquare,
    book: BookOpen,
  };

  const isItemActive = (to, itemId) => {
    if (itemId === 'member_profile') {
      return pathname === '/' || pathname === '/profile' || pathname === '';
    }
    if (itemId === 'store') {
      return pathname.startsWith('/store') || pathname.startsWith('/product');
    }
    if (itemId === 'account') {
      return pathname === '/account' || pathname === '/change-password';
    }
    if (itemId === 'publications') {
      return pathname === '/publications';
    }
    if (itemId === 'manage') {
      return false;
    }
    return pathname === to;
  };

  return (
    <nav className={cn('flex flex-col gap-6 px-4 py-4', className)} aria-label="Member portal">
      {portalSections.map((section) => (
        <div key={section.title}>
          <p className="mb-2 px-3 text-[0.82rem] font-semibold uppercase tracking-[0.22em] text-white/85">{section.title}</p>
          <ul className="space-y-1">
            {section.items.filter((item) => {
              if (item.id === 'publications' && !canAccessPublications) {
                return false;
              }

              return true;
            }).map((item) => {
              const active = isItemActive(item.to, item.id);
              const Icon = iconRegistry[item.icon] || User;
              const itemClasses = cn(
                'portal-sidebar-link flex items-center gap-3 rounded-[1.1rem] border px-3 py-3 text-sm font-medium text-white transition-all',
                active ? 'portal-sidebar-link--active shadow-[0_12px_28px_rgba(0,0,0,0.42)]' : 'shadow-[0_10px_24px_rgba(0,0,0,0.28)]',
              );
              const icon = <Icon className={cn('h-5 w-5 shrink-0', active ? 'portal-sidebar-link__icon--active' : 'text-white')} />;

              if (item.href) {
                return (
                  <li key={item.href + item.label}>
                    <a href={item.href} onClick={onNavigate} className={itemClasses}>
                      {icon}
                      {item.label}
                    </a>
                  </li>
                );
              }

              return (
                <li key={item.to + item.label}>
                  <Link
                    to={item.to}
                    onClick={onNavigate}
                    className={itemClasses}
                  >
                    {icon}
                    {item.label}
                  </Link>
                </li>
              );
            })}
          </ul>
        </div>
      ))}
    </nav>
  );
}

const PortalSidebar = ({ mobileOpen, onMobileClose }) => {
  const portalUiSettings = getPortalUiSettings();
  const design = portalUiSettings.design;
  const sidebarTopoUrl = design.sidebarBackgroundUrl || '/sidebar-topo-v2.svg';

  const sidebarSurfaceStyle = {
    position: 'sticky',
    top: 'var(--aac-portal-header-height)',
    height: 'calc(100vh - var(--aac-portal-header-height))',
    maxHeight: 'calc(100vh - var(--aac-portal-header-height))',
    backgroundColor: '#030000',
    backgroundImage: `linear-gradient(180deg, rgba(5, 2, 2, ${design.sidebarOverlayStart || '0.18'}), rgba(5, 2, 2, ${design.sidebarOverlayEnd || '0.30'})), url("${sidebarTopoUrl}")`,
    backgroundPosition: 'center center, center top',
    backgroundRepeat: 'no-repeat, repeat-y',
    backgroundSize: '100% 100%, 100% auto',
    '--portal-sidebar-button-bg': design.sidebarButtonBackground || '#000000',
    '--portal-sidebar-button-hover-bg': design.sidebarButtonHoverBackground || '#111111',
    '--portal-sidebar-button-active-bg': design.sidebarButtonActiveBackground || '#000000',
    '--portal-sidebar-accent': design.sidebarAccentColor || '#f8c235',
  };

  return (
    <>
      <aside
        className="portal-sidebar-surface hidden w-[18.5rem] shrink-0 self-stretch border-r border-black/8 md:flex md:flex-col"
        style={sidebarSurfaceStyle}
        aria-label="Member portal navigation"
      >
        <div className="sticky top-0 flex min-h-full flex-1 flex-col justify-start overflow-y-auto">
          <PortalNavLinks className="pb-6" />
        </div>
      </aside>

      {mobileOpen ? (
        <div
          className="fixed inset-0 z-[60] bg-black/60 md:hidden"
          onClick={onMobileClose}
          role="presentation"
        >
          <aside
            className="portal-sidebar-surface absolute left-0 top-0 flex h-full w-[min(100%,19rem)] flex-col border-r border-white/10 shadow-xl"
            style={sidebarSurfaceStyle}
            onClick={(e) => e.stopPropagation()}
            role="dialog"
            aria-modal="true"
            aria-label="Member portal menu"
          >
            <div className="flex items-center justify-between border-b border-white/10 px-4 py-4">
              <span className="text-sm font-semibold uppercase tracking-[0.2em] text-[#f8c235]">Member portal</span>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onMobileClose}
                className="text-white hover:bg-white/10 hover:text-white"
                aria-label="Close menu"
              >
                <X className="h-5 w-5" />
              </Button>
            </div>
            <div className="flex min-h-0 flex-1 flex-col overflow-y-auto">
              <PortalNavLinks onNavigate={onMobileClose} className="pb-6" />
            </div>
          </aside>
        </div>
      ) : null}
    </>
  );
};

export default PortalSidebar;
