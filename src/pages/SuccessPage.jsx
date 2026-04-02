import React, { useEffect } from 'react';
import { Helmet } from 'react-helmet';
import { Link, useLocation } from 'react-router-dom';
import { motion } from 'framer-motion';
import { CheckCircle, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDollars } from '@/lib/fakePaymentFlows';
import { useAuth } from '@/hooks/useAuth';
import { recordMemberTransaction } from '@/lib/transactions';

const SuccessPage = () => {
  const location = useLocation();
  const { user } = useAuth();
  const searchParams = new URLSearchParams(location.search);
  const headline = location.state?.headline || 'Payment Successful!';
  const message = location.state?.message || "Thank you for your order. We've received your payment and your order is now being processed.";
  const returnPath = location.state?.returnPath || '/';
  const returnLabel = location.state?.returnLabel || 'Go to My Portal';
  const amount = location.state?.amount ?? Number(searchParams.get('amount') || 0);
  const kind = location.state?.kind || searchParams.get('kind');
  const memberPortalKinds = new Set(['membership', 'membership_cart', 'event', 'lodging']);
  const primaryPath = kind === 'merchandise' ? '/store' : returnPath;
  const primaryLabel = kind === 'merchandise' ? 'Continue Shopping' : returnLabel;
  const secondaryPath =
    kind === 'merchandise'
      ? returnPath
      : memberPortalKinds.has(kind)
        ? '/profile'
        : '/';
  const secondaryLabel =
    kind === 'merchandise'
      ? returnLabel
      : memberPortalKinds.has(kind)
        ? 'Back to Member Profile'
        : 'Back to Home';

  useEffect(() => {
    const searchKind = searchParams.get('kind');
    const referenceId = searchParams.get('ref');

    if (!user?.id || searchKind !== 'merchandise' || !referenceId) {
      return;
    }

    recordMemberTransaction({
      memberId: user.id,
      kind: 'Merchandise',
      amount: Number(searchParams.get('amount') || 0),
      description: searchParams.get('description') || 'Merchandise order',
      referenceId,
    });
  }, [searchParams, user?.id]);

  return (
    <>
      <Helmet>
        <title>Payment Successful! - American Alpine Club</title>
        <meta name="description" content="Your payment was successful. Thank you for your order!" />
      </Helmet>
      <div className="min-h-screen topo-lines flex items-center justify-center p-4">
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.5, type: 'spring' }}
          className="w-full max-w-md text-center card-gradient border border-stone-200 rounded-[28px] shadow-2xl p-8"
        >
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            transition={{ delay: 0.2, duration: 0.5, type: 'spring', stiffness: 150 }}
          >
            <CheckCircle className="mx-auto h-20 w-20 text-[#c8a43a]" />
          </motion.div>
          <h1 className="mt-6 text-4xl font-bold text-black">{headline}</h1>
          <p className="mt-4 text-lg text-black/75">
            {message}
          </p>
          <p className="mt-2 text-black/60 text-sm">
            {amount ? `${formatDollars(amount)} processed through the fake card processor.` : 'You will receive an email confirmation shortly.'}
          </p>
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6, duration: 0.5 }}
          >
            <Button asChild className="mt-8 w-full bg-[#b71c1c] text-white font-bold py-3 text-base hover:bg-[#8f1515] transition-all duration-300 ease-in-out transform hover:scale-105">
              <Link to={primaryPath}>
                {primaryLabel}
                <ArrowRight className="ml-2 h-5 w-5" />
              </Link>
            </Button>
            <Button asChild variant="ghost" className="mt-4 w-full">
              <Link to={secondaryPath}>{secondaryLabel}</Link>
            </Button>
          </motion.div>
        </motion.div>
      </div>
    </>
  );
};

export default SuccessPage;
