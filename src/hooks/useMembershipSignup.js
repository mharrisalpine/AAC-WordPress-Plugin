import { useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '@/hooks/useAuth';
import { useCart, buildSignupCartLineItems } from '@/hooks/useCart';
import { useToast } from '@/components/ui/use-toast';
import { useFakePayment } from '@/hooks/useFakePayment';
import { createMembershipSignupPaymentIntent } from '@/lib/fakePaymentFlows';
import { getTierById, normalizeTierId } from '@/lib/membershipTiers';

export const emptyMembershipSignupForm = {
  email: '',
  firstName: '',
  lastName: '',
  phone: '',
  phoneType: 'mobile',
  street: '',
  city: '',
  state: '',
  zip: '',
  country: 'US',
  guidebookPref: 'Digital',
  size: 'M',
  password: '',
  tierId: 'Partner',
  donationUsd: 0,
};

/**
 * @param {object} opts
 * @param {'signup' | 'renewal'} opts.mode
 * @param {boolean} opts.isActive When false (e.g. dialog closed), form sync is skipped.
 * @param {() => void} [opts.onDismiss] Called after successful submit before checkout redirect (e.g. close modal).
 */
export function useMembershipSignup({ mode, isActive, onDismiss }) {
  const isSignup = mode === 'signup';
  const { signUpWithProfile, updateProfile, profile, loading } = useAuth();
  const { setSignupCartLines } = useCart();
  const { toast } = useToast();
  const { startPaymentFlow } = useFakePayment();
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState(emptyMembershipSignupForm);
  const signupResetReadyRef = useRef(true);

  useEffect(() => {
    if (!isActive) {
      signupResetReadyRef.current = true;
      return;
    }
    if (mode === 'renewal' && profile?.account_info) {
      const a = profile.account_info;
      const matchTier = normalizeTierId(profile.profile_info?.tier);
      setForm({
        email: a.email || '',
        firstName: a.first_name || '',
        lastName: a.last_name || '',
        phone: a.phone || '',
        phoneType: a.phone_type || 'mobile',
        street: a.street || '',
        city: a.city || '',
        state: a.state || '',
        zip: a.zip || '',
        country: a.country || 'US',
        guidebookPref: a.guidebook_pref || 'Digital',
        size: a.size || 'M',
        password: '',
        tierId: matchTier,
        donationUsd: 0,
      });
      return;
    }
    if (mode === 'signup' && signupResetReadyRef.current) {
      signupResetReadyRef.current = false;
      setForm({ ...emptyMembershipSignupForm, tierId: 'Partner' });
    }
  }, [isActive, mode, profile]);

  const tier = useMemo(() => getTierById(form.tierId), [form.tierId]);

  const buildAccountPayload = () => ({
    first_name: form.firstName.trim(),
    last_name: form.lastName.trim(),
    name: `${form.firstName.trim()} ${form.lastName.trim()}`.trim(),
    email: form.email.trim(),
    phone: form.phone.trim(),
    phone_type: form.phoneType,
    street: form.street.trim(),
    city: form.city.trim(),
    state: form.state.trim(),
    zip: form.zip.trim(),
    country: form.country.trim(),
    guidebook_pref: form.guidebookPref,
    size: form.size,
    publication_pref: profile?.account_info?.publication_pref || 'Digital',
    auto_renew: profile?.account_info?.auto_renew ?? false,
    payment_method: profile?.account_info?.payment_method || '',
    photo_url: profile?.account_info?.photo_url || '',
  });

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!form.tierId) {
      toast({ variant: 'destructive', title: 'Select a membership level' });
      return;
    }
    setSubmitting(true);
    try {
      if (isSignup) {
        if (!form.password || form.password.length < 8) {
          toast({ variant: 'destructive', title: 'Password must be at least 8 characters' });
          setSubmitting(false);
          return;
        }
        const { error } = await signUpWithProfile(
          form.email.trim(),
          form.password,
          { data: { first_name: form.firstName.trim(), last_name: form.lastName.trim() } },
          {
            account_info: buildAccountPayload(),
            profile_info: { tier: normalizeTierId(form.tierId) },
          }
        );
        if (error) {
          setSubmitting(false);
          return;
        }
      } else {
        const { error: upErr } = await updateProfile({
          account_info: {
            ...profile.account_info,
            ...buildAccountPayload(),
          },
          profile_info: {
            ...profile.profile_info,
            tier: normalizeTierId(form.tierId),
          },
        });
        if (upErr) {
          setSubmitting(false);
          return;
        }
      }

      const donationForCheckout = isSignup ? form.donationUsd : 0;
      const signupLines = buildSignupCartLineItems({
        membershipLabel: `${tier.label} membership`,
        membershipCents: tier.priceCents,
        donationUsd: donationForCheckout,
      });
      setSignupCartLines({
        membershipLabel: `${tier.label} membership`,
        membershipCents: tier.priceCents,
        donationUsd: donationForCheckout,
      });

      const totalCents = signupLines.reduce(
        (sum, item) => sum + (item.variant.price_in_cents ?? 0) * item.quantity,
        0
      );

      onDismiss?.();

      if (totalCents <= 0) {
        toast({
          title: isSignup ? 'Account created' : 'Information saved',
          description: 'Your profile has been updated.',
        });
        return;
      }

      toast({
        title: isSignup ? 'Account created' : 'Information saved',
        description: isSignup
          ? 'Continue to checkout to pay membership dues and any donation.'
          : 'Continue to checkout to pay your membership dues.',
      });

      startPaymentFlow(
        createMembershipSignupPaymentIntent({
          cartItems: signupLines,
          accountInfo: buildAccountPayload(),
          targetTier: normalizeTierId(form.tierId),
        })
      );
    } finally {
      setSubmitting(false);
    }
  };

  const busy = loading || submitting;

  return { form, setForm, handleSubmit, busy, tier };
}
