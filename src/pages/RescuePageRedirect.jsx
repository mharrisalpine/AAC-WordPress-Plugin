import React from 'react';
import { getRescuePageUrl } from '@/lib/backendConfig';

const RescuePageRedirect = () => {
  const rescuePageUrl = React.useMemo(() => getRescuePageUrl(), []);

  React.useEffect(() => {
    if (!rescuePageUrl || typeof window === 'undefined') {
      return;
    }

    window.location.assign(rescuePageUrl);
  }, [rescuePageUrl]);

  return (
    <div className="flex min-h-[40vh] items-center justify-center px-6 py-16 text-center text-stone-800">
      <div>
        <p className="text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-stone-500">Redirecting</p>
        <h1 className="mt-3 text-3xl font-bold text-stone-900">Opening Rescue Page</h1>
        <p className="mt-3 text-base text-stone-600">
          We&apos;re sending you to the WordPress-managed rescue page now.
        </p>
        {rescuePageUrl ? (
          <a
            href={rescuePageUrl}
            className="mt-5 inline-flex min-h-[3rem] items-center justify-center border border-stone-900 px-5 text-sm font-semibold uppercase tracking-[0.14em] text-stone-900 transition hover:bg-stone-900 hover:text-white"
          >
            Open Rescue Page
          </a>
        ) : null}
      </div>
    </div>
  );
};

export default RescuePageRedirect;
