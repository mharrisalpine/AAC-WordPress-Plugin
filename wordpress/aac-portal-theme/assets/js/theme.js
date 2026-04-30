document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('.aac-theme-header');
  const toggle = document.querySelector('[data-aac-nav-toggle]');
  const drawer = document.getElementById('aac-mobile-nav');
  const sliders = document.querySelectorAll('[data-aac-slider]');
  let hideTimer = null;
  let lastScrollY = window.scrollY;

  const syncHeaderHeight = () => {
    if (!header) {
      return;
    }

    document.documentElement.style.setProperty('--aac-header-height', `${header.offsetHeight}px`);
  };

  const syncHeaderState = () => {
    if (!header) {
      return;
    }

    header.classList.toggle('is-scrolled', window.scrollY > 8);
  };

  const showHeader = () => {
    if (!header) {
      return;
    }

    header.classList.remove('is-auto-hidden');
  };

  const scheduleHeaderHide = () => {
    if (!header) {
      return;
    }

    if (hideTimer) {
      window.clearTimeout(hideTimer);
    }

    hideTimer = window.setTimeout(() => {
      const drawerOpen = drawer && !drawer.hidden;
      if (window.scrollY > 32 && !drawerOpen) {
        header.classList.add('is-auto-hidden');
      }
    }, 1500);
  };

  const handleScroll = () => {
    const currentScrollY = window.scrollY;
    const scrolledEnough = Math.abs(currentScrollY - lastScrollY) > 2;
    const nearTop = currentScrollY <= 32;

    syncHeaderState();

    if (nearTop || scrolledEnough) {
      showHeader();
    }

    scheduleHeaderHide();
    lastScrollY = currentScrollY;
  };

  syncHeaderHeight();
  syncHeaderState();
  lastScrollY = window.scrollY;
  scheduleHeaderHide();
  window.addEventListener('resize', syncHeaderHeight);
  window.addEventListener('scroll', handleScroll, { passive: true });

  if (!toggle || !drawer) {
    return;
  }

  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    drawer.hidden = expanded;
    document.body.classList.toggle('aac-mobile-nav-open', !expanded);
    showHeader();
    if (!expanded) {
      if (hideTimer) {
        window.clearTimeout(hideTimer);
      }
    } else {
      scheduleHeaderHide();
    }
    window.requestAnimationFrame(syncHeaderHeight);
  });

  sliders.forEach((slider) => {
    const sliderId = slider.getAttribute('data-aac-slider');
    if (!sliderId) {
      return;
    }

    const prevButton = document.querySelector(`[data-aac-slider-prev="${sliderId}"]`);
    const nextButton = document.querySelector(`[data-aac-slider-next="${sliderId}"]`);
    const scrollAmount = () => Math.max(slider.clientWidth * 0.82, 280);

    if (prevButton) {
      prevButton.addEventListener('click', () => {
        slider.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', () => {
        slider.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
      });
    }
  });
});
