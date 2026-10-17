(() => {
  const header = document.querySelector('#site-header');
  if (!header || !document.querySelector('#pmprogroupacct_manage_group')) return;
  // Use layout height, not the scrolling transform, to keep content stable.
  const measure = () => {
    const position = getComputedStyle(header).position;
    const top = parseFloat(getComputedStyle(header).top) || 0;
    const height = position === 'fixed' || position === 'absolute' ? header.offsetHeight + Math.max(0, top) : 0;
    document.documentElement.style.setProperty('--aac-group-header-height', `${height}px`);
  };
  measure();
  if ('ResizeObserver' in window) new ResizeObserver(measure).observe(header);
  window.addEventListener('resize', measure, { passive: true });
})();
