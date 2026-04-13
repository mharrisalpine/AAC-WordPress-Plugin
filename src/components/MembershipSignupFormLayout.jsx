import React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MembershipTierSelect } from '@/components/MembershipTierSelect';
import { DONATION_OPTIONS_USD, PHONE_TYPE_OPTIONS, TSHIRT_SIZES } from '@/lib/membershipTiers';
import { cn } from '@/lib/utils';

export function MembershipSignupFormLayout({
  form,
  setForm,
  onSubmit,
  busy,
  isSignup,
  tierVariant = 'compact',
  showCancel,
  onCancel,
  showDonationSection = true,
}) {
  return (
    <form onSubmit={onSubmit} className="space-y-8">
      <div>
        <p className="mb-4 text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-stone-600">Membership level</p>
        <MembershipTierSelect
          variant={tierVariant}
          selectedId={form.tierId}
          onSelect={(id) => setForm((f) => ({ ...f, tierId: id }))}
        />
      </div>

      <div className="rounded-[1.75rem] border border-black/10 bg-white/92 p-6 shadow-[0_18px_40px_rgba(0,0,0,0.05)] sm:p-8">
        <h3 className="mb-6 text-xl text-stone-900">Your details</h3>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="sm:col-span-2">
            <Label htmlFor="su-email" className="text-stone-800">
              Email
            </Label>
            <Input
              id="su-email"
              type="email"
              required
              value={form.email}
              onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-fn" className="text-stone-800">
              First name
            </Label>
            <Input
              id="su-fn"
              required
              value={form.firstName}
              onChange={(e) => setForm((f) => ({ ...f, firstName: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-ln" className="text-stone-800">
              Last name
            </Label>
            <Input
              id="su-ln"
              required
              value={form.lastName}
              onChange={(e) => setForm((f) => ({ ...f, lastName: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-phone" className="text-stone-800">
              Phone
            </Label>
            <Input
              id="su-phone"
              type="tel"
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-ptype" className="text-stone-800">
              Phone type
            </Label>
            <select
              id="su-ptype"
              value={form.phoneType}
              onChange={(e) => setForm((f) => ({ ...f, phoneType: e.target.value }))}
              className="mt-1 h-10 w-full rounded-md border border-input bg-white px-3 text-stone-900"
            >
              {PHONE_TYPE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </div>
          <div className="sm:col-span-2">
            <Label htmlFor="su-street" className="text-stone-800">
              Street address
            </Label>
            <Input
              id="su-street"
              value={form.street}
              onChange={(e) => setForm((f) => ({ ...f, street: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-city" className="text-stone-800">
              City
            </Label>
            <Input
              id="su-city"
              value={form.city}
              onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-state" className="text-stone-800">
              State / Province
            </Label>
            <Input
              id="su-state"
              value={form.state}
              onChange={(e) => setForm((f) => ({ ...f, state: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-zip" className="text-stone-800">
              ZIP / Postal code
            </Label>
            <Input
              id="su-zip"
              value={form.zip}
              onChange={(e) => setForm((f) => ({ ...f, zip: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-country" className="text-stone-800">
              Country
            </Label>
            <Input
              id="su-country"
              value={form.country}
              onChange={(e) => setForm((f) => ({ ...f, country: e.target.value }))}
              className="mt-1 bg-white text-stone-900"
            />
          </div>
          <div>
            <Label htmlFor="su-guide" className="text-stone-800">
              Guidebook to Membership
            </Label>
            <select
              id="su-guide"
              value={form.guidebookPref}
              onChange={(e) => setForm((f) => ({ ...f, guidebookPref: e.target.value }))}
              className="mt-1 h-10 w-full rounded-md border border-input bg-white px-3 text-stone-900"
            >
              <option value="Digital">Digital</option>
              <option value="Print">Print</option>
            </select>
          </div>
          <div>
            <Label htmlFor="su-shirt" className="text-stone-800">
              T-shirt size
            </Label>
            <select
              id="su-shirt"
              value={form.size}
              onChange={(e) => setForm((f) => ({ ...f, size: e.target.value }))}
              className="mt-1 h-10 w-full rounded-md border border-input bg-white px-3 text-stone-900"
            >
              {TSHIRT_SIZES.map((s) => (
                <option key={s} value={s}>
                  {s}
                </option>
              ))}
            </select>
          </div>
          {isSignup ? (
            <div className="sm:col-span-2">
              <Label htmlFor="su-pw" className="text-stone-800">
                Password
              </Label>
              <Input
                id="su-pw"
                type="password"
                required={isSignup}
                autoComplete="new-password"
                value={form.password}
                onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                className="mt-1 bg-white text-stone-900"
              />
              <p className="mt-1 text-xs text-stone-500">At least 8 characters. Used to sign in to your member portal.</p>
            </div>
          ) : null}
        </div>
      </div>

      {showDonationSection ? (
      <div className="rounded-[1.75rem] border border-black/10 bg-white/92 p-6 shadow-[0_18px_40px_rgba(0,0,0,0.05)] sm:p-8">
          <p className="text-sm font-semibold text-stone-900">Add a donation?</p>
          <p className="mb-4 text-xs text-stone-600">Optional — included with membership dues at checkout.</p>
          <div className="flex flex-wrap gap-2">
            {DONATION_OPTIONS_USD.map((d) => (
              <button
                key={d.value}
                type="button"
                onClick={() => setForm((f) => ({ ...f, donationUsd: d.value }))}
                className={cn(
                  'rounded-lg border px-3 py-2 text-sm transition',
                  form.donationUsd === d.value
                    ? 'border-[#f8c235] bg-[rgba(248,194,53,0.18)] text-stone-900'
                    : 'border-stone-200 text-stone-700 hover:border-stone-400',
                )}
              >
                {d.label}
              </button>
            ))}
          </div>
        </div>
      ) : null}

      <div className="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
        {showCancel ? (
          <Button type="button" variant="outline" onClick={onCancel} disabled={busy}>
            Cancel
          </Button>
        ) : null}
        <Button
          type="submit"
          className="bg-[#f8c235] px-6 text-black hover:bg-[#dda914] sm:min-w-[220px]"
          disabled={busy}
        >
          {busy ? 'Please wait…' : isSignup ? 'Create account & checkout' : 'Save & checkout'}
        </Button>
      </div>
    </form>
  );
}
