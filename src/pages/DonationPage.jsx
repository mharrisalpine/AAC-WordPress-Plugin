
import React, { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { Heart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { createDonationPaymentIntent, formatDollars } from '@/lib/fakePaymentFlows';
import { useFakePayment } from '@/hooks/useFakePayment';

const DONATION_FUNDS = [
  'General AAC Fund',
  'Climbing Grief Fund',
  'Conservation & Advocacy',
  'Climber Rescue Benefit Fund',
  'Education & Publications',
];

const TRIBUTE_OPTIONS = [
  { value: '', label: 'No tribute' },
  { value: 'honor', label: 'In honor of someone' },
  { value: 'memory', label: 'In memory of someone' },
];

const DonationPage = () => {
  const [selectedAmount, setSelectedAmount] = useState(50);
  const [selectedFund, setSelectedFund] = useState(DONATION_FUNDS[0]);
  const [tributeType, setTributeType] = useState('');
  const [tributeName, setTributeName] = useState('');
  const [tributeMessage, setTributeMessage] = useState('');
  const { startPaymentFlow } = useFakePayment();
  const donationAmount = useMemo(() => Math.max(1, Number(selectedAmount) || 0), [selectedAmount]);
  const presetAmounts = [25, 50, 100, 250];
  const hasTribute = Boolean(tributeType);

  const handleDonate = () => {
    startPaymentFlow(createDonationPaymentIntent({
      amount: donationAmount,
      fund: selectedFund,
      tributeType,
      tributeName,
      tributeMessage,
    }));
  };

  return (
    <>
      <Helmet>
        <title>Donate - American Alpine Club</title>
        <meta name="description" content="Support the American Alpine Club by making a donation." />
      </Helmet>
      <div className="relative min-h-screen overflow-hidden pt-24">
        <div className="absolute inset-0 bg-[#030000]" />
        <motion.img
          src="https://americanalpine.wpenginepowered.com/wp-content/uploads/2025/12/Calder-Davey-Homepage-Fillers.jpg"
          alt=""
          aria-hidden="true"
          className="absolute inset-0 h-full w-full object-cover opacity-30"
          animate={{ scale: [1, 1.08, 1], x: [0, -22, 0], y: [0, 10, 0] }}
          transition={{ duration: 20, repeat: Infinity, ease: 'easeInOut' }}
        />
        <div className="absolute inset-0 bg-gradient-to-b from-black/65 via-black/45 to-[#030000]" />
        <motion.div
          aria-hidden="true"
          className="absolute bottom-0 left-0 h-[38vh] w-full bg-[#0a0b0d]/80"
          style={{ clipPath: 'polygon(0 78%, 14% 63%, 26% 70%, 41% 38%, 53% 58%, 66% 30%, 81% 54%, 100% 34%, 100% 100%, 0 100%)' }}
          animate={{ y: [0, 8, 0] }}
          transition={{ duration: 12, repeat: Infinity, ease: 'easeInOut' }}
        />
        <motion.div
          aria-hidden="true"
          className="absolute bottom-0 left-0 h-[32vh] w-full bg-[#11161a]"
          style={{ clipPath: 'polygon(0 86%, 12% 71%, 24% 79%, 39% 47%, 52% 66%, 64% 43%, 78% 69%, 100% 52%, 100% 100%, 0 100%)' }}
          animate={{ y: [0, -6, 0] }}
          transition={{ duration: 16, repeat: Infinity, ease: 'easeInOut' }}
        />

        <div className="relative mx-auto grid max-w-[1600px] gap-8 px-4 pb-16 sm:px-6 lg:grid-cols-[0.92fr,1.08fr] lg:items-start lg:px-10 xl:px-14">
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="pt-6 text-white lg:sticky lg:top-36"
          >
            <Heart className="mb-4 h-12 w-12 text-[#f8c235]" />
            <p className="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-[#f8c235]">Support AAC</p>
            <h1 className="mt-4 max-w-xl text-4xl leading-[0.95] text-white sm:text-5xl lg:text-6xl">
              Back the work behind every climb.
            </h1>
            <p className="mt-5 max-w-xl text-base leading-7 text-white/74 sm:text-lg">
              Direct your gift to the fund that matters most to you, or dedicate it in honor or memory of someone who shaped your climbing life.
            </p>
            <div className="mt-8 max-w-md rounded-[1.75rem] border border-white/12 bg-white/[0.07] p-5 backdrop-blur-sm">
              <p className="text-[0.72rem] font-semibold uppercase tracking-[0.24em] text-[#f8c235]">Current selection</p>
              <div className="mt-4 space-y-3 text-sm text-white/85">
                <div className="flex items-center justify-between gap-4">
                  <span className="text-white/60">Amount</span>
                  <span>{formatDollars(donationAmount)}</span>
                </div>
                <div className="flex items-center justify-between gap-4">
                  <span className="text-white/60">Fund</span>
                  <span className="text-right">{selectedFund}</span>
                </div>
                {hasTribute ? (
                  <div className="flex items-center justify-between gap-4">
                    <span className="text-white/60">Tribute</span>
                    <span className="text-right">
                      {tributeType === 'honor' ? 'In honor of' : 'In memory of'} {tributeName || 'someone'}
                    </span>
                  </div>
                ) : null}
              </div>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.5, delay: 0.2 }}
            className="relative w-full rounded-[2rem] border border-white/10 bg-black/35 p-6 text-white shadow-[0_24px_80px_rgba(0,0,0,0.28)] backdrop-blur-sm sm:p-8"
          >
            <div className="grid gap-6">
              <div>
                <p className="mb-3 text-sm uppercase tracking-[0.3em] text-[#f8c235]">Choose an amount</p>
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                  {presetAmounts.map((amount) => (
                    <Button
                      key={amount}
                      type="button"
                      onClick={() => setSelectedAmount(amount)}
                      variant={Number(selectedAmount) === amount ? 'default' : 'outline'}
                      className={Number(selectedAmount) === amount ? 'border-[#f8c235] bg-[#f8c235] text-black hover:bg-[#dda914]' : 'border-white/20 bg-white/[0.04] text-white hover:bg-white/[0.08] hover:text-white'}
                    >
                      {formatDollars(amount)}
                    </Button>
                  ))}
                </div>
              </div>

              <div>
                <label htmlFor="donation-amount" className="mb-2 block text-sm font-medium text-white">Custom amount</label>
                <Input
                  id="donation-amount"
                  type="number"
                  min="1"
                  value={selectedAmount}
                  onChange={(e) => setSelectedAmount(e.target.value)}
                  className="border-white/15 bg-white text-black"
                />
              </div>

              <div className="grid gap-6 lg:grid-cols-2">
                <div>
                  <label htmlFor="donation-fund" className="mb-2 block text-sm font-medium text-white">Fund</label>
                  <select
                    id="donation-fund"
                    value={selectedFund}
                    onChange={(e) => setSelectedFund(e.target.value)}
                    className="h-11 w-full rounded-md border border-white/15 bg-white px-3 text-black"
                  >
                    {DONATION_FUNDS.map((fund) => (
                      <option key={fund} value={fund}>
                        {fund}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label htmlFor="tribute-type" className="mb-2 block text-sm font-medium text-white">Tribute giving</label>
                  <select
                    id="tribute-type"
                    value={tributeType}
                    onChange={(e) => setTributeType(e.target.value)}
                    className="h-11 w-full rounded-md border border-white/15 bg-white px-3 text-black"
                  >
                    {TRIBUTE_OPTIONS.map((option) => (
                      <option key={option.value || 'none'} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {hasTribute ? (
                <div className="grid gap-4 rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-5">
                  <div>
                    <label htmlFor="tribute-name" className="mb-2 block text-sm font-medium text-white">
                      {tributeType === 'honor' ? 'Honoree name' : 'Remembered person'}
                    </label>
                    <Input
                      id="tribute-name"
                      value={tributeName}
                      onChange={(e) => setTributeName(e.target.value)}
                      className="border-white/15 bg-white text-black"
                      placeholder={tributeType === 'honor' ? 'Who is this gift honoring?' : 'Who is this gift remembering?'}
                    />
                  </div>
                  <div>
                    <label htmlFor="tribute-message" className="mb-2 block text-sm font-medium text-white">Tribute note</label>
                    <textarea
                      id="tribute-message"
                      value={tributeMessage}
                      onChange={(e) => setTributeMessage(e.target.value)}
                      rows={4}
                      className="w-full rounded-md border border-white/15 bg-white px-3 py-2 text-black"
                      placeholder="Optional note to capture why this gift matters."
                    />
                  </div>
                </div>
              ) : null}

              <div className="rounded-2xl border border-white/10 bg-white/[0.05] p-5 text-white/72">
                Any fake card number will succeed on the next screen. This is a demo donation processor only.
              </div>

              <Button
                type="button"
                onClick={handleDonate}
                className="h-12 w-full bg-[#f8c235] text-lg text-black hover:bg-[#dda914]"
              >
                Donate {formatDollars(donationAmount)}
              </Button>
            </div>
          </motion.div>
        </div>
      </div>
    </>
  );
};

export default DonationPage;
