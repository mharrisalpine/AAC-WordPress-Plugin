import React, { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { ArrowLeft, CreditCard, Lock, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/components/ui/use-toast';
import { useAuth } from '@/hooks/useAuth';
import { formatDollars, getMembershipBenefits } from '@/lib/fakePaymentFlows';
import { normalizeTierId } from '@/lib/membershipTiers';
import { getFullName } from '@/lib/memberProfile';
import { recordMemberTransaction } from '@/lib/transactions';
import { useCart } from '@/hooks/useCart';
import { registerForFakeEvent } from '@/lib/fakeEvents';
import { registerFakeLodgingReservation } from '@/lib/fakeLodging';

const onlyDigits = (value) => value.replace(/\D/g, '');

const formatCardNumber = (value) => {
  const digits = onlyDigits(value).slice(0, 16);
  return digits.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
};

const formatExpiry = (value) => {
  const digits = onlyDigits(value).slice(0, 4);
  if (digits.length <= 2) {
    return digits;
  }
  return `${digits.slice(0, 2)}/${digits.slice(2)}`;
};

const maskCard = (value) => {
  const digits = onlyDigits(value);
  const last4 = digits.slice(-4) || '4242';
  return `Visa ending in ${last4}`;
};

const FakePaymentPage = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, profile, updateProfile } = useAuth();
  const { clearCart, clearSignupCart } = useCart();
  const { toast } = useToast();
  const intent = location.state?.intent;

  const [cardholderName, setCardholderName] = useState(getFullName(profile?.account_info));
  const [cardNumber, setCardNumber] = useState('4242 4242 4242 4242');
  const [expiry, setExpiry] = useState('12/34');
  const [cvc, setCvc] = useState('123');
  const [billingZip, setBillingZip] = useState(profile?.account_info?.zip || '');
  const [addressLine1, setAddressLine1] = useState(profile?.account_info?.street || '');
  const [billingCity, setBillingCity] = useState(profile?.account_info?.city || '');
  const [billingState, setBillingState] = useState(profile?.account_info?.state || '');
  const [billingCountry, setBillingCountry] = useState(profile?.account_info?.country || 'US');
  const [processing, setProcessing] = useState(false);

  const amountLabel = useMemo(() => formatDollars(intent?.amount || 0), [intent?.amount]);
  const showAddressForm =
    intent?.kind === 'merchandise' ||
    intent?.kind === 'event' ||
    intent?.kind === 'membership_cart' ||
    intent?.kind === 'lodging';

  const applyPaymentResult = async () => {
    if (!intent) {
      return;
    }

    if (intent.kind === 'membership') {
      const accountInfo = {
        ...(profile?.account_info || {}),
        payment_method: maskCard(cardNumber),
      };

      const updates = { account_info: accountInfo };

      if (intent.metadata?.membershipAction !== 'manage_payment') {
        const targetTier = normalizeTierId(
          intent.metadata?.targetTier || profile?.profile_info?.tier || 'Partner',
        );
        updates.profile_info = {
          ...(profile?.profile_info || {}),
          tier: targetTier,
          status: 'Active',
          renewal_date: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString(),
        };
        updates.benefits_info = getMembershipBenefits(targetTier);
      }

      await updateProfile(updates);
      return;
    }

    if (intent.kind === 'merchandise') {
      clearCart();
      return;
    }

    if (intent.kind === 'membership_cart') {
      const targetTier = normalizeTierId(
        intent.metadata?.targetTier || profile?.profile_info?.tier || 'Partner',
      );
      const accountInfo = {
        ...(profile?.account_info || {}),
        payment_method: maskCard(cardNumber),
      };
      const renewalMs =
        targetTier === 'Advocate'
          ? 80 * 365 * 24 * 60 * 60 * 1000
          : 365 * 24 * 60 * 60 * 1000;

      await updateProfile({
        account_info: accountInfo,
        profile_info: {
          ...(profile?.profile_info || {}),
          tier: targetTier,
          status: 'Active',
          renewal_date: new Date(Date.now() + renewalMs).toISOString(),
        },
        benefits_info: getMembershipBenefits(targetTier),
      });
      clearSignupCart();
      return;
    }

    if (intent.kind === 'event' && user?.id) {
      registerForFakeEvent({
        memberId: user.id,
        eventId: intent.metadata?.eventId,
        registration: intent.metadata?.registration || {},
      });
      return;
    }

    if (intent.kind === 'lodging' && user?.id) {
      registerFakeLodgingReservation({
        memberId: user.id,
        reservation: {
          referenceId: intent.referenceId,
          siteId: intent.metadata?.siteId,
          siteName: intent.metadata?.siteName,
          checkIn: intent.metadata?.stay?.checkIn,
          checkOut: intent.metadata?.stay?.checkOut,
          nights: intent.metadata?.stay?.nights || 0,
          partySize: intent.metadata?.stay?.partySize || 1,
          total: intent.amount || 0,
          primaryGuest: {
            name: cardholderName || getFullName(intent.metadata?.registration || profile?.account_info),
            email: intent.metadata?.registration?.email || '',
            phone: intent.metadata?.registration?.phone || '',
          },
          guests: intent.metadata?.registration?.guests || [],
          emergencyContact: intent.metadata?.registration?.emergency_contact || '',
          specialRequests: intent.metadata?.registration?.special_requests || '',
          paymentMethod: maskCard(cardNumber),
          status: 'Confirmed',
        },
      });
    }
  };

  const recordTransaction = () => {
    if (!user?.id || !intent || intent.type === 'manage_payment') {
      return;
    }

    const transactionKindMap = {
      donation: 'Donation',
      membership: 'Membership',
      merchandise: 'Merchandise',
      membership_cart: 'Membership',
      event: 'Events',
      lodging: 'Lodging',
    };

    const lineItemsDescription = (intent.metadata?.items || [])
      .map((item) => `${item.title} x${item.quantity}`)
      .join(', ');

    const merchandiseDescription =
      intent.kind === 'merchandise' || intent.kind === 'membership_cart'
        ? lineItemsDescription || intent.title
        : intent.title;

    const transactionDescription =
      intent.kind === 'merchandise' || intent.kind === 'membership_cart'
        ? merchandiseDescription
        : intent.kind === 'event'
          ? `${intent.metadata?.eventTitle || intent.title} registration`
          : intent.kind === 'lodging'
            ? `${intent.metadata?.siteName || intent.title} reservation`
          : intent.title;

    recordMemberTransaction({
      memberId: user.id,
      kind: transactionKindMap[intent.kind] || 'Other',
      amount: intent.amount || 0,
      description: transactionDescription,
      referenceId: intent.referenceId || `${intent.kind}_${intent.type || 'payment'}_${intent.metadata?.targetTier || 'general'}_${intent.amount || 0}`,
      metadata: {
        membershipAction: intent.metadata?.membershipAction || null,
        targetTier: intent.metadata?.targetTier || null,
        items: intent.metadata?.items || [],
        eventTitle: intent.metadata?.eventTitle || null,
        siteName: intent.metadata?.siteName || null,
        checkIn: intent.metadata?.stay?.checkIn || null,
        checkOut: intent.metadata?.stay?.checkOut || null,
        fund: intent.metadata?.fund || null,
        tributeType: intent.metadata?.tributeType || null,
        tributeName: intent.metadata?.tributeName || null,
      },
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!intent) {
      toast({
        variant: 'destructive',
        title: 'Missing payment details',
        description: 'Start your payment flow again from the donation or membership screen.',
      });
      return;
    }

    if (!cardholderName || onlyDigits(cardNumber).length < 13 || expiry.length < 5 || cvc.length < 3) {
      toast({
        variant: 'destructive',
        title: 'Incomplete card details',
        description: 'Enter a fake card number, expiry date, and CVC to continue.',
      });
      return;
    }

    if (showAddressForm && (!addressLine1 || !billingCity || !billingState || !billingZip || !billingCountry)) {
      toast({
        variant: 'destructive',
        title: 'Missing billing details',
        description: 'Complete the billing address fields before submitting payment.',
      });
      return;
    }

    setProcessing(true);
    try {
      await new Promise((resolve) => setTimeout(resolve, 1200));
      await applyPaymentResult();
      recordTransaction();
      navigate('/success', {
        state: {
          headline: intent.successHeadline,
          message: intent.successMessage,
          returnPath: intent.successPath || '/',
          returnLabel:
            intent.kind === 'donation'
              ? 'Back to Donations'
              : intent.kind === 'merchandise'
                ? 'Back to Store'
                : intent.kind === 'event'
                  ? 'Back to Events'
                  : intent.kind === 'lodging'
                    ? 'Back to Lodging'
                  : intent.kind === 'membership_cart'
                    ? 'Go to My Portal'
                    : 'Go to My Portal',
          amount: intent.amount || 0,
          kind: intent.kind,
        },
      });
    } catch (error) {
      toast({
        variant: 'destructive',
        title: 'Payment failed',
        description: error.message || 'There was a problem applying the fake payment.',
      });
    } finally {
      setProcessing(false);
    }
  };

  if (!intent) {
    return (
      <div className="max-w-3xl mx-auto pt-28 px-4 text-center text-stone-900">
        <h1 className="text-3xl font-bold mb-4">No payment in progress</h1>
        <p className="text-stone-600 mb-6">Start from donations or your membership screen to open the fake card processor.</p>
        <Button asChild>
          <Link to="/">Return to portal</Link>
        </Button>
      </div>
    );
  }

  return (
    <>
      <Helmet>
        <title>{intent.title} - American Alpine Club</title>
        <meta name="description" content="Complete a fake payment for the AAC member portal demo." />
      </Helmet>
      <div className="max-w-5xl mx-auto pt-28 pb-12 px-4">
        <Link to={intent.successPath || '/'} className="inline-flex items-center gap-2 text-stone-800 hover:text-[#a07f21] transition-colors mb-6">
          <ArrowLeft size={16} />
          Back
        </Link>
        <div className="grid lg:grid-cols-[1.1fr,0.9fr] gap-8">
          <motion.form
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            onSubmit={handleSubmit}
            className="card-gradient rounded-3xl border border-stone-200/80 p-8 shadow-2xl"
          >
            <div className="flex items-center justify-between mb-6">
              <div>
                <p className="text-sm uppercase tracking-[0.3em] text-[#c8a43a] mb-2">Demo Processor</p>
                <h1 className="text-3xl font-bold text-stone-900">{intent.title}</h1>
                {intent.description && <p className="text-stone-600 mt-2">{intent.description}</p>}
              </div>
              <div className="rounded-2xl bg-stone-100 p-4 border border-stone-200">
                <CreditCard className="w-8 h-8 text-[#c8a43a]" />
              </div>
            </div>

            <div className="space-y-5">
              <div>
                <Label htmlFor="cardholder" className="text-stone-900">Cardholder Name</Label>
                <Input
                  id="cardholder"
                  value={cardholderName}
                  onChange={(e) => setCardholderName(e.target.value)}
                  className="bg-white border-[#d9d9d9] text-black mt-1"
                  placeholder="Jane Member"
                />
              </div>
              <div>
                <Label htmlFor="cardnumber" className="text-stone-900">Card Number</Label>
                <Input
                  id="cardnumber"
                  inputMode="numeric"
                  value={cardNumber}
                  onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
                  className="bg-white border-[#d9d9d9] text-black mt-1 tracking-[0.2em]"
                  placeholder="4242 4242 4242 4242"
                />
              </div>
              <div className="grid grid-cols-3 gap-4">
                <div className="col-span-1">
                  <Label htmlFor="expiry" className="text-stone-900">Expiry</Label>
                  <Input
                    id="expiry"
                    inputMode="numeric"
                    value={expiry}
                    onChange={(e) => setExpiry(formatExpiry(e.target.value))}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                    placeholder="12/34"
                  />
                </div>
                <div className="col-span-1">
                  <Label htmlFor="cvc" className="text-stone-900">CVC</Label>
                  <Input
                    id="cvc"
                    inputMode="numeric"
                    value={cvc}
                    onChange={(e) => setCvc(onlyDigits(e.target.value).slice(0, 4))}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                    placeholder="123"
                  />
                </div>
                <div className="col-span-1">
                  <Label htmlFor="zip" className="text-stone-900">ZIP</Label>
                  <Input
                    id="zip"
                    value={billingZip}
                    onChange={(e) => setBillingZip(e.target.value)}
                    className="bg-white border-[#d9d9d9] text-black mt-1"
                    placeholder="80301"
                  />
                </div>
              </div>

              {showAddressForm && (
                <div className="space-y-4 rounded-2xl border border-stone-200 bg-stone-50 p-5">
                  <div>
                    <p className="text-sm uppercase tracking-[0.3em] text-[#a07f21] mb-1">Billing Information</p>
                    <p className="text-sm text-stone-600">
                      {intent.kind === 'merchandise'
                        ? 'Shipping and billing details were pulled from your member profile.'
                        : 'Billing details were pulled from your member profile.'}
                    </p>
                  </div>
                  <div>
                    <Label htmlFor="billing-address" className="text-stone-900">Street Address</Label>
                    <Input
                      id="billing-address"
                      value={addressLine1}
                      onChange={(e) => setAddressLine1(e.target.value)}
                      className="bg-white border-[#d9d9d9] text-black mt-1"
                    />
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="billing-city" className="text-stone-900">City</Label>
                      <Input
                        id="billing-city"
                        value={billingCity}
                        onChange={(e) => setBillingCity(e.target.value)}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="billing-state" className="text-stone-900">State</Label>
                      <Input
                        id="billing-state"
                        value={billingState}
                        onChange={(e) => setBillingState(e.target.value)}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="billing-country" className="text-stone-900">Country</Label>
                      <Input
                        id="billing-country"
                        value={billingCountry}
                        onChange={(e) => setBillingCountry(e.target.value)}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                    <div>
                      <Label htmlFor="billing-zip" className="text-stone-900">ZIP</Label>
                      <Input
                        id="billing-zip"
                        value={billingZip}
                        onChange={(e) => setBillingZip(e.target.value)}
                        className="bg-white border-[#d9d9d9] text-black mt-1"
                      />
                    </div>
                  </div>
                </div>
              )}
            </div>

            <div className="mt-8 flex items-center gap-3 rounded-2xl border border-[rgba(200,164,58,0.35)] bg-[rgba(200,164,58,0.12)] px-4 py-3 text-sm text-[#6b5310]">
              <ShieldCheck className="w-5 h-5 flex-shrink-0" />
              Any fake card details will be accepted. This processor is for demo transactions only.
            </div>

            <Button
              type="submit"
              disabled={processing}
              className="w-full mt-8 bg-[#b71c1c] hover:bg-[#8f1515] text-white h-12 text-lg"
            >
              <Lock className="w-5 h-5 mr-2" />
              {processing ? 'Processing...' : `${intent.submitLabel} ${intent.amount ? `• ${amountLabel}` : ''}`}
            </Button>
          </motion.form>

          <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.1 }}
            className="rounded-3xl border border-stone-200 bg-white/90 p-8 backdrop-blur-sm shadow-sm"
          >
            <p className="text-sm uppercase tracking-[0.3em] text-stone-500 mb-3">Summary</p>
            <h2 className="text-2xl font-bold text-stone-900 mb-6">{intent.title}</h2>
            <div className="space-y-4 text-stone-600">
              <div className="flex justify-between gap-4">
                <span>Transaction Type</span>
                <span className="text-stone-900 capitalize">{intent.kind.replace('_', ' ')}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span>Amount</span>
                <span className="text-stone-900">{amountLabel}</span>
              </div>
              {intent.metadata?.targetTier && (
                <div className="flex justify-between gap-4">
                  <span>Membership Level</span>
                  <span className="text-stone-900">{intent.metadata.targetTier}</span>
                </div>
              )}
              {intent.metadata?.membershipAction && (
                <div className="flex justify-between gap-4">
                  <span>Action</span>
                  <span className="text-stone-900 capitalize">{intent.metadata.membershipAction.replace('_', ' ')}</span>
                </div>
              )}
              {intent.metadata?.fund && (
                <div className="flex justify-between gap-4">
                  <span>Fund</span>
                  <span className="text-right text-stone-900">{intent.metadata.fund}</span>
                </div>
              )}
              {intent.metadata?.tributeType && intent.metadata?.tributeName && (
                <div className="flex justify-between gap-4">
                  <span>Tribute</span>
                  <span className="text-right text-stone-900">
                    {intent.metadata.tributeType === 'honor' ? 'In honor of' : 'In memory of'} {intent.metadata.tributeName}
                  </span>
                </div>
              )}
            </div>
            <div className="mt-8 rounded-2xl bg-stone-100 p-5 border border-stone-200">
              <p className="text-stone-500 text-sm mb-2">Quick test card</p>
              <p className="text-stone-900 font-mono text-lg tracking-[0.15em]">4242 4242 4242 4242</p>
            </div>
          </motion.div>
        </div>
      </div>
    </>
  );
};

export default FakePaymentPage;
