// A restored browser page keeps React state. Reconcile it with the server on return.
export const bindBillingProfileRefresh = ({ windowTarget, documentTarget, refresh, onChecking = () => {} }) => {
  let disposed = false;
  let refreshing = false;
  let queued = false;
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
  const onReturn = () => { void reconcile(); };
  const onPageShow = (event) => { if (event.persisted) onReturn(); };
  windowTarget.addEventListener('focus', onReturn);
  windowTarget.addEventListener('pageshow', onPageShow);
  documentTarget.addEventListener('visibilitychange', onReturn);
  onReturn();
  return () => {
    disposed = true;
    windowTarget.removeEventListener('focus', onReturn);
    windowTarget.removeEventListener('pageshow', onPageShow);
    documentTarget.removeEventListener('visibilitychange', onReturn);
  };
};
