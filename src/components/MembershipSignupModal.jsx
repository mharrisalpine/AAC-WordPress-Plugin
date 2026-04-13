import React from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { MembershipSignupFormLayout } from '@/components/MembershipSignupFormLayout';
import { useMembershipSignup } from '@/hooks/useMembershipSignup';

/**
 * @param {object} props
 * @param {boolean} props.open
 * @param {(open: boolean) => void} props.onOpenChange
 * @param {'signup' | 'renewal'} props.mode
 */
export function MembershipSignupModal({ open, onOpenChange, mode }) {
  const isSignup = mode === 'signup';
  const { form, setForm, handleSubmit, busy } = useMembershipSignup({
    mode,
    isActive: open,
    onDismiss: () => onOpenChange(false),
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[min(90vh,900px)] w-[calc(100%-1.5rem)] max-w-2xl overflow-y-auto sm:w-full">
        <DialogHeader>
          <DialogTitle className="text-2xl">
            {isSignup ? 'Join the AAC' : 'Renew your membership'}
          </DialogTitle>
          <DialogDescription>
            {isSignup
              ? 'Choose your level, complete your profile, and optionally add a donation. You will go straight to checkout for dues and any donation (separate from the merchandise cart).'
              : 'Your membership expires within 30 days. Review your information and continue to checkout to renew before it lapses.'}
          </DialogDescription>
        </DialogHeader>

        <MembershipSignupFormLayout
          form={form}
          setForm={setForm}
          onSubmit={handleSubmit}
          busy={busy}
          isSignup={isSignup}
          tierVariant="compact"
          showCancel
          onCancel={() => onOpenChange(false)}
          showDonationSection={isSignup}
        />
      </DialogContent>
    </Dialog>
  );
}

export default MembershipSignupModal;
