import React, { useEffect, useRef } from 'react';
import { LogOut, Menu, Search, ShoppingCart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useCart } from '@/hooks/useCart';
import { Link, useLocation } from 'react-router-dom';
import { MainSiteNavigation } from '@/components/MainSiteNavigation';
import { mainSiteHref } from '@/lib/mainWebsiteNav';

const LIGHT_LOGO_URL = 'https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/09/light-header-logo.svg';

const ACTION_H = 'h-11 min-h-[2.75rem]';

const utilityLinkBase =
  'inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.03] px-3 py-2 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-white/80 transition-colors hover:border-[#f8c235]/45 hover:text-[#f8c235]';

const UtilityLinks = ({ className }) => {
  const items = [
    { label: 'Search', href: mainSiteHref('/search'), icon: Search },
  ];

  return (
    <div className={className}>
      {items.map((item) => {
        const Icon = item.icon;
        return (
          <a
            key={item.label}
            href={item.href}
            className={utilityLinkBase}
            {...(item.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
          >
            <Icon className="h-3.5 w-3.5" />
            <span>{item.label}</span>
          </a>
        );
      })}
    </div>
  );
};

const HeaderActions = ({ showCart, cartItemCount, onCartClick, onLogout, showLogout, showLogin, className }) => (
  <div className={className}>
    {showCart ? (
      <button
        type="button"
        onClick={onCartClick}
        className={`relative inline-flex ${ACTION_H} w-11 items-center justify-center rounded-full border border-white/10 bg-white/[0.03] text-white transition-colors hover:border-[#f8c235]/45 hover:text-[#f8c235]`}
        aria-label="Shopping cart"
      >
        <ShoppingCart className="h-6 w-6" />
        {cartItemCount > 0 ? (
          <span className="absolute -right-1 -top-0 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-[#f8c235] px-1 text-xs font-bold text-black">
            {cartItemCount}
          </span>
        ) : null}
      </button>
    ) : null}

    <Link
      to="/donate"
      className={`inline-flex ${ACTION_H} items-center justify-center rounded-full bg-[#8f1515] px-5 text-sm font-semibold uppercase tracking-[0.14em] text-white transition-colors hover:bg-[#6b1010]`}
    >
      Donate
    </Link>

    {showLogin ? (
      <Link
        to="/login"
        className={`inline-flex ${ACTION_H} items-center justify-center rounded-full border border-[#f8c235] bg-[#f8c235] px-5 text-sm font-semibold uppercase tracking-[0.14em] text-black transition-colors hover:bg-[#e1ae14]`}
      >
        Login
      </Link>
    ) : null}

    {showLogout ? (
      <Button
        type="button"
        onClick={onLogout}
        className={`${ACTION_H} rounded-full border border-[#f8c235] bg-[#f8c235] px-5 text-sm font-semibold uppercase tracking-[0.14em] text-black hover:bg-[#e1ae14]`}
      >
        <LogOut className="mr-1.5 hidden h-4 w-4 sm:inline" />
        Log Out
      </Button>
    ) : null}
  </div>
);

/**
 * @param {object} props
 * @param {'portal' | 'public'} [props.variant] public = join page (no portal menu, no log out)
 */
const Header = ({ variant = 'portal', onLogout, onCartClick, onOpenPortalMenu }) => {
  const isPublic = variant === 'public';
  const { cartItems } = useCart();
  const location = useLocation();
  const headerRef = useRef(null);
  const cartItemCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);
  const isLoginRoute = location.pathname === '/login';

  const isStoreRelatedPage = location.pathname.startsWith('/store') || location.pathname.startsWith('/product');
  const showCart = isStoreRelatedPage;

  useEffect(() => {
    const headerNode = headerRef.current;
    if (!headerNode || typeof document === 'undefined') {
      return undefined;
    }

    const updateHeaderHeight = () => {
      document.documentElement.style.setProperty('--aac-portal-header-height', `${headerNode.offsetHeight}px`);
    };

    updateHeaderHeight();

    let observer;
    if (typeof ResizeObserver !== 'undefined') {
      observer = new ResizeObserver(updateHeaderHeight);
      observer.observe(headerNode);
    }

    window.addEventListener('resize', updateHeaderHeight);

    return () => {
      window.removeEventListener('resize', updateHeaderHeight);
      observer?.disconnect();
    };
  }, [variant, location.pathname]);

  return (
    <header
      ref={headerRef}
      className="sticky top-0 z-50 border-b border-white/10 bg-[#030000]/95 text-white backdrop-blur"
      style={{ paddingTop: 'env(safe-area-inset-top, 0px)' }}
    >
      <div className="mx-auto max-w-[1600px] px-4 py-3 md:px-6">
        <div className="flex flex-col gap-3 xl:hidden">
          <div className="flex items-center justify-between gap-3">
            <div className="flex min-w-0 items-center gap-2">
              {!isPublic ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  onClick={onOpenPortalMenu}
                  className="shrink-0 rounded-full border border-white/10 bg-white/[0.03] text-white hover:bg-white/10 hover:text-white md:hidden"
                  aria-label="Open member portal menu"
                >
                  <Menu className="h-6 w-6" />
                </Button>
              ) : null}
              <Link to="/" className="flex shrink-0 items-center">
                <img
                  alt="American Alpine Club Logo"
                  className="h-10 w-auto sm:h-11"
                  src={LIGHT_LOGO_URL}
                />
              </Link>
            </div>

            <HeaderActions
              showCart={showCart && !isPublic}
              cartItemCount={cartItemCount}
              onCartClick={onCartClick}
              onLogout={onLogout}
              showLogout={!isPublic}
              showLogin={isPublic && !isLoginRoute}
              className="flex items-center gap-2"
            />
          </div>

          <UtilityLinks className="flex flex-wrap items-center gap-2 border-t border-white/10 pt-3" />

          <MainSiteNavigation className="min-w-0" />
        </div>

        <div className="hidden xl:flex xl:flex-col xl:gap-3">
          <div className="flex items-center justify-end gap-4 border-b border-white/10 pb-4">
            <UtilityLinks className="flex flex-wrap items-center justify-end gap-2" />

            <HeaderActions
              showCart={showCart && !isPublic}
              cartItemCount={cartItemCount}
              onCartClick={onCartClick}
              onLogout={onLogout}
              showLogout={!isPublic}
              showLogin={isPublic && !isLoginRoute}
              className="flex shrink-0 items-center gap-2"
            />
          </div>

          <div className="relative flex items-center justify-center pb-1">
            <Link to="/" className="absolute left-0 flex items-center">
              <img
                alt="American Alpine Club Logo"
                className="h-14 w-auto"
                src={LIGHT_LOGO_URL}
              />
            </Link>
            <MainSiteNavigation className="min-w-0 justify-center" />
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;
