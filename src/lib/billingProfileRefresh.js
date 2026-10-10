// A restored browser page keeps React state. Reconcile it with the server on return.
export const bindBillingProfileRefresh = ({ windowTarget, documentTarget, refresh, onChecking = () => {} }) => {
  let disposed = false;
  let refreshing = false;
  let queued = false;
  let wasHidden = documentTarget.visibilityState === 'hidden';
  const reconcile = async () => {
    if (disposed || documentTarget.visibilityState === 'hidden') return;
    if (refreshing) { queued = true; return; }
    refreshing = true;
    onChecking(true);
    try { await refresh(); }
    finally {
      refreshing = false;
      if (!disposed) {
        onChecking(false);
        if (queued) { queued = false; void reconcile(); }
      }
    }
  };
  const onPageShow = (event) => { if (event.persisted) void reconcile(); };
  const onVisibilityChange = () => {
    if (documentTarget.visibilityState === 'hidden') {
      wasHidden = true;
      return;
    }
    if (wasHidden) {
      wasHidden = false;
      void reconcile();
    }
  };
  windowTarget.addEventListener('pageshow', onPageShow);
  documentTarget.addEventListener('visibilitychange', onVisibilityChange);
  onChecking(false);
  return () => {
    disposed = true;
    windowTarget.removeEventListener('pageshow', onPageShow);
    documentTarget.removeEventListener('visibilitychange', onVisibilityChange);
  };
};
