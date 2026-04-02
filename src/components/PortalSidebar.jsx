import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { User, Shield, Settings, Store, Tag, Mic2, Users, Mail, ScrollText, BedDouble, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function PortalNavLinks({ onNavigate, className }) {
  const { pathname } = useLocation();
  const portalSections = [
    {
      title: 'Your portal',
      items: [
        { to: '/profile', icon: User, label: 'Member Profile', match: (p) => p === '/' || p === '/profile' || p === '' },
        {
          to: '/store',
          icon: Store,
          label: 'Store',
          match: (p) => p.startsWith('/store') || p.startsWith('/product'),
        },
        { to: '/rescue', icon: Shield, label: 'Rescue', match: (p) => p === '/rescue' },
        { to: '/account', icon: Settings, label: 'Account', match: (p) => p === '/account' || p === '/change-password' },
      ],
    },
    {
      title: 'Explore',
      items: [
        { to: '/discounts', icon: Tag, label: 'Discounts', match: (p) => p === '/discounts' },
        { to: '/podcasts', icon: Mic2, label: 'Podcasts', match: (p) => p === '/podcasts' },
        { to: '/meetups', icon: Users, label: 'Events', match: (p) => p === '/meetups' },
        { to: '/lodging', icon: BedDouble, label: 'Lodging', match: (p) => p === '/lodging' },
        { to: '/grants', icon: ScrollText, label: 'Grants', match: (p) => p === '/grants' },
        { to: '/contact', icon: Mail, label: 'Contact Us', match: (p) => p === '/contact' },
      ],
    },
  ];

  return (
    <nav className={cn('flex flex-col gap-6 px-4 py-4', className)} aria-label="Member portal">
      {portalSections.map((section) => (
        <div key={section.title}>
          <p className="mb-2 px-3 text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-white/80">{section.title}</p>
          <ul className="space-y-1">
            {section.items.map((item) => {
              const active = item.match(pathname);
              const itemClasses = cn(
                'flex items-center gap-3 rounded-[1.1rem] border px-3 py-3 text-sm font-medium transition-all',
                active
                  ? 'border-[#f8c235]/65 bg-black/90 text-white shadow-[0_12px_28px_rgba(0,0,0,0.42)]'
                  : 'border-white/10 bg-black/72 text-white shadow-[0_10px_24px_rgba(0,0,0,0.28)] hover:border-[#f8c235]/35 hover:bg-black/84 hover:text-white',
              );
              const icon = <item.icon className={cn('h-5 w-5 shrink-0', active ? 'text-[#f8c235]' : 'text-white')} />;

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
  const runtimeApiBase =
    typeof window !== 'undefined' ? window.AAC_MEMBER_PORTAL_CONFIG?.apiBase : '';
  let sidebarTopoUrl = '/sidebar-topo.svg';

  if (runtimeApiBase) {
    try {
      sidebarTopoUrl = `${new URL(runtimeApiBase).origin}/wp-content/plugins/aac-member-portal/app/sidebar-topo.svg`;
    } catch (error) {
      sidebarTopoUrl = '/sidebar-topo.svg';
    }
  }

  const sidebarSurfaceStyle = {
    backgroundColor: '#030000',
    backgroundImage: `linear-gradient(180deg, rgba(5, 2, 2, 0.42), rgba(5, 2, 2, 0.56)), url("${sidebarTopoUrl}")`,
    backgroundPosition: 'center center, 46% 0%',
    backgroundRepeat: 'no-repeat, no-repeat',
    backgroundSize: '100% 100%, 185% auto',
  };

  return (
    <>
      <aside
        className="portal-sidebar-surface hidden min-h-0 w-[18.5rem] shrink-0 self-stretch overflow-y-auto border-r border-black/8 md:flex"
        style={sidebarSurfaceStyle}
        aria-label="Member portal navigation"
      >
        <PortalNavLinks className="pb-6" />
      </aside>

      {mobileOpen ? (
        <div
          className="fixed inset-0 z-[60] bg-black/60 md:hidden"
          onClick={onMobileClose}
          role="presentation"
        >
          <aside
            className="portal-sidebar-surface absolute left-0 top-0 flex h-full w-[min(100%,19rem)] flex-col overflow-y-auto border-r border-white/10 shadow-xl"
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
            <PortalNavLinks onNavigate={onMobileClose} className="pb-6" />
          </aside>
        </div>
      ) : null}
    </>
  );
};

export default PortalSidebar;
