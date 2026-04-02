import React, { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import {
  ArrowUpRight,
  BedDouble,
  CalendarDays,
  CheckCircle2,
  CreditCard,
  MapPin,
  Mountain,
  Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/components/ui/use-toast';
import { useAuth } from '@/hooks/useAuth';
import { useFakePayment } from '@/hooks/useFakePayment';
import { createLodgingPaymentIntent, formatDollars } from '@/lib/fakePaymentFlows';
import {
  AAC_LODGING_SOURCE_URL,
  calculateLodgingEstimate,
  formatLodgingDate,
  formatLodgingDateRange,
  getFakeLodgingSites,
  getLodgingSiteById,
  getMemberLodgingReservations,
} from '@/lib/fakeLodging';

const addDays = (date, amount) => {
  const nextDate = new Date(date);
  nextDate.setDate(nextDate.getDate() + amount);
  return nextDate;
};

const toIsoDate = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const getDefaultCheckIn = () => toIsoDate(addDays(new Date(), 7));

const getDefaultCheckOut = () => toIsoDate(addDays(new Date(), 9));

const buildCalendarDays = (referenceDate, checkIn, checkOut) => {
  const sourceDate = referenceDate ? new Date(`${referenceDate}T12:00:00`) : new Date();
  const monthStart = new Date(sourceDate.getFullYear(), sourceDate.getMonth(), 1);
  const calendarStart = addDays(monthStart, -monthStart.getDay());

  return Array.from({ length: 35 }, (_, index) => {
    const date = addDays(calendarStart, index);
    const iso = toIsoDate(date);
    const inSelectedRange = Boolean(checkIn && checkOut && iso >= checkIn && iso < checkOut);

    return {
      iso,
      dayLabel: date.getDate(),
      isCurrentMonth: date.getMonth() === sourceDate.getMonth(),
      isCheckIn: checkIn === iso,
      isCheckOut: checkOut === iso,
      inSelectedRange,
    };
  });
};

const ensureGuestSlots = (guests, additionalGuestCount) => {
  const nextGuests = Array.isArray(guests) ? [...guests] : [];

  while (nextGuests.length < additionalGuestCount) {
    nextGuests.push({ name: '', email: '', notes: '' });
  }

  return nextGuests.slice(0, additionalGuestCount);
};

const LodgingPage = () => {
  const { user, profile } = useAuth();
  const { toast } = useToast();
  const { startPaymentFlow } = useFakePayment();
  const [selectedSiteId, setSelectedSiteId] = useState(getFakeLodgingSites()[0]?.id || '');
  const [reservations, setReservations] = useState([]);
  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    emergency_contact: '',
    check_in: getDefaultCheckIn(),
    check_out: getDefaultCheckOut(),
    party_size: '2',
    guests: [{ name: '', email: '', notes: '' }],
    special_requests: '',
  });

  const lodgingSites = useMemo(() => getFakeLodgingSites(), []);
  const selectedSite = useMemo(() => getLodgingSiteById(selectedSiteId), [selectedSiteId]);
  const additionalGuestCount = Math.max(0, Number(form.party_size || 1) - 1);
  const estimatedStay = useMemo(
    () => calculateLodgingEstimate(selectedSite, form.check_in, form.check_out, Number(form.party_size || 1)),
    [selectedSite, form.check_in, form.check_out, form.party_size],
  );
  const calendarDays = useMemo(
    () => buildCalendarDays(form.check_in || getDefaultCheckIn(), form.check_in, form.check_out),
    [form.check_in, form.check_out],
  );

  useEffect(() => {
    if (!user?.id) {
      return;
    }

    setReservations(getMemberLodgingReservations(user.id));
  }, [user?.id]);

  useEffect(() => {
    setForm((current) => ({
      ...current,
      first_name: current.first_name || profile?.account_info?.first_name || '',
      last_name: current.last_name || profile?.account_info?.last_name || '',
      email: current.email || profile?.account_info?.email || user?.email || '',
      phone: current.phone || profile?.account_info?.phone || '',
    }));
  }, [profile?.account_info, user?.email]);

  useEffect(() => {
    setForm((current) => {
      const maxPartySize = Number(selectedSite?.maxPartySize || 1);
      const nextPartySize = Math.min(maxPartySize, Math.max(1, Number(current.party_size) || 1));
      const nextAdditionalGuestCount = Math.max(0, nextPartySize - 1);

      return {
        ...current,
        party_size: String(nextPartySize),
        guests: ensureGuestSlots(current.guests, nextAdditionalGuestCount),
      };
    });
  }, [selectedSite?.maxPartySize]);

  const handleFieldChange = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }));
  };

  const handlePartySizeChange = (value) => {
    const parsedValue = Math.max(1, Math.min(Number(selectedSite?.maxPartySize || 1), Number(value) || 1));
    setForm((current) => ({
      ...current,
      party_size: String(parsedValue),
      guests: ensureGuestSlots(current.guests, Math.max(0, parsedValue - 1)),
    }));
  };

  const handleGuestChange = (index, field, value) => {
    setForm((current) => ({
      ...current,
      guests: current.guests.map((guest, guestIndex) =>
        guestIndex === index
          ? {
              ...guest,
              [field]: value,
            }
          : guest,
      ),
    }));
  };

  const handleCalendarSelect = (isoDate) => {
    setForm((current) => {
      if (!current.check_in || (current.check_in && current.check_out)) {
        return {
          ...current,
          check_in: isoDate,
          check_out: '',
        };
      }

      if (isoDate <= current.check_in) {
        return {
          ...current,
          check_in: isoDate,
          check_out: '',
        };
      }

      return {
        ...current,
        check_out: isoDate,
      };
    });
  };

  const handleReservationSubmit = () => {
    if (!selectedSite?.id) {
      toast({
        title: 'Choose a lodging site',
        description: 'Select an AAC lodging destination before continuing.',
        variant: 'destructive',
      });
      return;
    }

    if (!form.first_name || !form.last_name || !form.email || !form.phone) {
      toast({
        title: 'Complete member information',
        description: 'First name, last name, email, and phone are required to reserve lodging.',
        variant: 'destructive',
      });
      return;
    }

    if (!form.check_in || !form.check_out || estimatedStay.nights <= 0) {
      toast({
        title: 'Select valid stay dates',
        description: 'Choose a check-in and check-out date with at least one night.',
        variant: 'destructive',
      });
      return;
    }

    const unnamedGuest = form.guests
      .slice(0, additionalGuestCount)
      .find((guest) => !guest.name.trim());

    if (unnamedGuest) {
      toast({
        title: 'Complete guest information',
        description: 'Add a name for each guest included in the reservation.',
        variant: 'destructive',
      });
      return;
    }

    startPaymentFlow(
      createLodgingPaymentIntent({
        site: selectedSite,
        registration: {
          first_name: form.first_name,
          last_name: form.last_name,
          email: form.email,
          phone: form.phone,
          emergency_contact: form.emergency_contact,
          special_requests: form.special_requests,
          guests: form.guests.slice(0, additionalGuestCount),
        },
        stay: {
          checkIn: form.check_in,
          checkOut: form.check_out,
          partySize: Number(form.party_size || 1),
          nights: estimatedStay.nights,
          baseAmount: estimatedStay.baseAmount,
          extraGuestAmount: estimatedStay.extraGuestAmount,
          total: estimatedStay.total,
        },
      }),
    );
  };

  return (
    <>
      <Helmet>
        <title>AAC Lodging - American Alpine Club</title>
        <meta
          name="description"
          content="Reserve a demo AAC lodging stay with prefilled member details, guest information, and placeholder payment checkout."
        />
      </Helmet>

      <div className="space-y-6 py-6">
        <motion.section
          initial={{ opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.45 }}
          className="overflow-hidden rounded-[32px] border border-black/10 bg-[#030000] text-white shadow-[0_24px_70px_rgba(3,0,0,0.18)]"
        >
          <div className="grid gap-8 px-6 py-8 md:px-8 lg:grid-cols-[1.05fr,0.95fr] lg:items-end">
            <div>
              <div className="inline-flex items-center gap-2 rounded-full border border-[#f8c235]/35 bg-[#f8c235]/10 px-4 py-2 text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-[#f8c235]">
                <BedDouble className="h-4 w-4" />
                AAC Lodging
              </div>
              <h1 className="mt-4 max-w-3xl text-4xl font-bold leading-tight md:text-5xl">
                Reserve a member stay without leaving the portal.
              </h1>
              <p className="mt-4 max-w-3xl text-base leading-7 text-white/75">
                This is a fake lodging registration flow based on AAC lodging destinations. Member details are
                prefilled automatically, the stay calendar is selectable in-app, and checkout uses the same
                placeholder payment processor as the rest of the portal.
              </p>
              <div className="mt-6 flex flex-wrap gap-3">
                <Button asChild className="bg-[#f8c235] text-black hover:bg-[#ddb01d]">
                  <a href={AAC_LODGING_SOURCE_URL} target="_blank" rel="noreferrer">
                    View AAC Lodging
                    <ArrowUpRight className="ml-2 h-4 w-4" />
                  </a>
                </Button>
                <div className="rounded-full border border-white/10 bg-white/[0.04] px-4 py-3 text-sm text-white/80">
                  Placeholder member rates shown below for demo checkout.
                </div>
              </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
              <div className="rounded-[24px] border border-white/10 bg-white/[0.05] p-5">
                <p className="text-xs uppercase tracking-[0.2em] text-[#f8c235]">Primary guest</p>
                <p className="mt-2 text-lg font-semibold">
                  {`${form.first_name} ${form.last_name}`.trim() || 'Member details will prefill here'}
                </p>
              </div>
              <div className="rounded-[24px] border border-white/10 bg-white/[0.05] p-5">
                <p className="text-xs uppercase tracking-[0.2em] text-[#f8c235]">Selected site</p>
                <p className="mt-2 text-sm text-white/80">{selectedSite?.name}</p>
              </div>
              <div className="rounded-[24px] border border-white/10 bg-white/[0.05] p-5">
                <p className="text-xs uppercase tracking-[0.2em] text-[#f8c235]">Estimated total</p>
                <p className="mt-2 text-lg font-semibold">{formatDollars(estimatedStay.total)}</p>
              </div>
            </div>
          </div>
        </motion.section>

        {reservations.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.03 }}
            className="card-gradient rounded-[28px] border border-stone-200/80 p-6"
          >
            <div className="mb-5 flex items-start gap-3">
              <div className="rounded-2xl bg-[#b71c1c]/10 p-3 text-[#8f1515]">
                <CheckCircle2 className="h-5 w-5" />
              </div>
              <div>
                <h2 className="text-xl font-bold text-stone-900">My Lodging Reservations</h2>
                <p className="mt-1 text-sm text-stone-600">
                  Confirmed placeholder lodging reservations completed through the member portal.
                </p>
              </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
              {reservations.slice(0, 4).map((reservation) => (
                <div key={reservation.referenceId} className="rounded-[24px] border border-stone-200 bg-white/90 p-5">
                  <div className="flex items-start justify-between gap-4">
                    <div>
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#8a6a19]">
                        {reservation.status || 'Confirmed'}
                      </p>
                      <h3 className="mt-2 text-lg font-semibold text-stone-900">{reservation.siteName}</h3>
                    </div>
                    <span className="rounded-full bg-[#edf7ee] px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-[#1f7a34]">
                      Paid
                    </span>
                  </div>

                  <div className="mt-4 grid gap-3 text-sm text-stone-700 sm:grid-cols-2">
                    <div className="rounded-[18px] bg-stone-50 px-4 py-3">
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Stay</p>
                      <p className="mt-1">{formatLodgingDateRange(reservation.checkIn, reservation.checkOut)}</p>
                    </div>
                    <div className="rounded-[18px] bg-stone-50 px-4 py-3">
                      <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Party</p>
                      <p className="mt-1">{reservation.partySize} guests</p>
                    </div>
                  </div>

                  <div className="mt-4 flex items-center justify-between text-sm">
                    <span className="text-stone-500">Booked {formatLodgingDate(reservation.createdAt?.slice(0, 10))}</span>
                    <span className="font-semibold text-stone-900">{formatDollars(reservation.total)}</span>
                  </div>
                </div>
              ))}
            </div>
          </motion.section>
        )}

        <div className="grid gap-6 xl:grid-cols-[0.88fr,1.12fr]">
          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.05 }}
            className="card-gradient rounded-[28px] border border-stone-200/80 p-6"
          >
            <div className="mb-5 flex items-start gap-3">
              <div className="rounded-2xl bg-[#c8a43a]/18 p-3 text-[#6b5310]">
                <Mountain className="h-5 w-5" />
              </div>
              <div>
                <h2 className="text-xl font-bold text-stone-900">Choose a Lodging Site</h2>
                <p className="mt-1 text-sm text-stone-600">
                  Pick from AAC lodging destinations currently surfaced in the public lodging navigation.
                </p>
              </div>
            </div>

            <div className="space-y-3">
              {lodgingSites.map((site) => {
                const active = site.id === selectedSiteId;
                return (
                  <button
                    key={site.id}
                    type="button"
                    onClick={() => setSelectedSiteId(site.id)}
                    className={`w-full rounded-[22px] border p-4 text-left transition-all ${
                      active
                        ? 'border-[#c8a43a] bg-[#fff8ea] shadow-[0_16px_32px_rgba(200,164,58,0.16)]'
                        : 'border-stone-200 bg-white hover:border-[#c8a43a]/50 hover:bg-[#fffbf4]'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#8a6a19]">
                          {site.type}
                        </p>
                        <h3 className="mt-2 text-lg font-semibold text-stone-900">{site.name}</h3>
                      </div>
                      <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700">
                        {formatDollars(site.nightlyRate)}/night
                      </span>
                    </div>
                    <p className="mt-3 text-sm leading-6 text-stone-700">{site.summary}</p>
                    <div className="mt-4 flex flex-wrap gap-2">
                      {site.highlights.map((highlight) => (
                        <span
                          key={highlight}
                          className="rounded-full border border-stone-200 bg-white px-3 py-1 text-xs font-medium text-stone-700"
                        >
                          {highlight}
                        </span>
                      ))}
                    </div>
                  </button>
                );
              })}
            </div>
          </motion.section>

          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, delay: 0.08 }}
            className="card-gradient rounded-[28px] border border-stone-200/80 p-6"
          >
            <div className="mb-6 flex items-start gap-3">
              <div className="rounded-2xl bg-[#b71c1c]/10 p-3 text-[#8f1515]">
                <CalendarDays className="h-5 w-5" />
              </div>
              <div>
                <h2 className="text-xl font-bold text-stone-900">Lodging Registration</h2>
                <p className="mt-1 text-sm text-stone-600">
                  Member details prefill automatically. Finish the stay and guest details, then continue to the
                  placeholder payment processor.
                </p>
              </div>
            </div>

            <div className="grid gap-4 rounded-[24px] border border-stone-200 bg-stone-50/90 p-5 md:grid-cols-3">
              <div className="rounded-[20px] bg-white px-4 py-4">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-stone-500">Location</p>
                <p className="mt-2 text-sm font-medium text-stone-900">{selectedSite.location}</p>
              </div>
              <div className="rounded-[20px] bg-white px-4 py-4">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-stone-500">Season</p>
                <p className="mt-2 text-sm font-medium text-stone-900">{selectedSite.season}</p>
              </div>
              <div className="rounded-[20px] bg-white px-4 py-4">
                <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-stone-500">Capacity</p>
                <p className="mt-2 text-sm font-medium text-stone-900">Up to {selectedSite.maxPartySize} guests</p>
              </div>
            </div>

            <div className="mt-6 space-y-6">
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <Label htmlFor="lodging-first-name" className="text-black">First Name</Label>
                  <Input
                    id="lodging-first-name"
                    value={form.first_name}
                    onChange={(event) => handleFieldChange('first_name', event.target.value)}
                    className="mt-1 bg-white border-stone-300 text-black"
                  />
                </div>
                <div>
                  <Label htmlFor="lodging-last-name" className="text-black">Last Name</Label>
                  <Input
                    id="lodging-last-name"
                    value={form.last_name}
                    onChange={(event) => handleFieldChange('last_name', event.target.value)}
                    className="mt-1 bg-white border-stone-300 text-black"
                  />
                </div>
                <div>
                  <Label htmlFor="lodging-email" className="text-black">Email</Label>
                  <Input
                    id="lodging-email"
                    type="email"
                    value={form.email}
                    onChange={(event) => handleFieldChange('email', event.target.value)}
                    className="mt-1 bg-white border-stone-300 text-black"
                  />
                </div>
                <div>
                  <Label htmlFor="lodging-phone" className="text-black">Phone</Label>
                  <Input
                    id="lodging-phone"
                    value={form.phone}
                    onChange={(event) => handleFieldChange('phone', event.target.value)}
                    className="mt-1 bg-white border-stone-300 text-black"
                  />
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-[1.1fr,0.9fr]">
                <div>
                  <Label htmlFor="lodging-site-select" className="text-black">Lodging Site</Label>
                  <select
                    id="lodging-site-select"
                    value={selectedSiteId}
                    onChange={(event) => setSelectedSiteId(event.target.value)}
                    className="mt-1 h-11 w-full rounded-md border border-stone-300 bg-white px-3 text-sm text-black shadow-sm outline-none transition focus:border-[#c8a43a]"
                  >
                    {lodgingSites.map((site) => (
                      <option key={site.id} value={site.id}>
                        {site.name}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <Label htmlFor="lodging-party-size" className="text-black">Party Size</Label>
                  <select
                    id="lodging-party-size"
                    value={form.party_size}
                    onChange={(event) => handlePartySizeChange(event.target.value)}
                    className="mt-1 h-11 w-full rounded-md border border-stone-300 bg-white px-3 text-sm text-black shadow-sm outline-none transition focus:border-[#c8a43a]"
                  >
                    {Array.from({ length: selectedSite.maxPartySize }, (_, index) => index + 1).map((partySize) => (
                      <option key={partySize} value={partySize}>
                        {partySize} {partySize === 1 ? 'guest' : 'guests'}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="rounded-[24px] border border-stone-200 bg-white p-5">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <h3 className="text-lg font-semibold text-stone-900">Selection Calendar</h3>
                    <p className="mt-1 text-sm text-stone-600">
                      Click a start date and then an end date, or use the date inputs below for exact stay dates.
                    </p>
                  </div>
                  <div className="rounded-full border border-stone-200 bg-stone-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                    {estimatedStay.nights > 0 ? `${estimatedStay.nights} nights` : 'Choose dates'}
                  </div>
                </div>

                <div className="mt-5 grid grid-cols-7 gap-2 text-center text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">
                  {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => (
                    <span key={day}>{day}</span>
                  ))}
                </div>

                <div className="mt-3 grid grid-cols-7 gap-2">
                  {calendarDays.map((day) => {
                    const active = day.isCheckIn || day.isCheckOut || day.inSelectedRange;
                    return (
                      <button
                        key={day.iso}
                        type="button"
                        onClick={() => handleCalendarSelect(day.iso)}
                        className={`rounded-2xl border px-0 py-3 text-sm transition ${
                          day.isCheckIn || day.isCheckOut
                            ? 'border-[#b71c1c] bg-[#b71c1c] text-white'
                            : day.inSelectedRange
                              ? 'border-[#c8a43a]/35 bg-[#fff4d2] text-stone-900'
                              : day.isCurrentMonth
                                ? 'border-stone-200 bg-stone-50 text-stone-900 hover:border-[#c8a43a]/50 hover:bg-[#fffbf4]'
                                : 'border-stone-100 bg-stone-50/60 text-stone-400'
                        }`}
                        aria-pressed={active}
                      >
                        {day.dayLabel}
                      </button>
                    );
                  })}
                </div>

                <div className="mt-5 grid gap-4 md:grid-cols-2">
                  <div>
                    <Label htmlFor="lodging-check-in" className="text-black">Check-in</Label>
                    <Input
                      id="lodging-check-in"
                      type="date"
                      value={form.check_in}
                      min={toIsoDate(new Date())}
                      onChange={(event) => handleFieldChange('check_in', event.target.value)}
                      className="mt-1 bg-white border-stone-300 text-black"
                    />
                  </div>
                  <div>
                    <Label htmlFor="lodging-check-out" className="text-black">Check-out</Label>
                    <Input
                      id="lodging-check-out"
                      type="date"
                      value={form.check_out}
                      min={form.check_in || toIsoDate(new Date())}
                      onChange={(event) => handleFieldChange('check_out', event.target.value)}
                      className="mt-1 bg-white border-stone-300 text-black"
                    />
                  </div>
                </div>
              </div>

              <div className="rounded-[24px] border border-stone-200 bg-white p-5">
                <div className="flex items-start gap-3">
                  <div className="rounded-2xl bg-[#c8a43a]/18 p-3 text-[#6b5310]">
                    <Users className="h-5 w-5" />
                  </div>
                  <div>
                    <h3 className="text-lg font-semibold text-stone-900">Guest Information</h3>
                    <p className="mt-1 text-sm text-stone-600">
                      The member is the primary guest. Add any additional guests included in the reservation.
                    </p>
                  </div>
                </div>

                <div className="mt-5 grid gap-4 md:grid-cols-2">
                  <div>
                    <Label htmlFor="lodging-emergency-contact" className="text-black">Emergency Contact</Label>
                    <Input
                      id="lodging-emergency-contact"
                      value={form.emergency_contact}
                      onChange={(event) => handleFieldChange('emergency_contact', event.target.value)}
                      className="mt-1 bg-white border-stone-300 text-black"
                      placeholder="Name and phone number"
                    />
                  </div>
                  <div>
                    <Label htmlFor="lodging-special-requests" className="text-black">Special Requests</Label>
                    <Input
                      id="lodging-special-requests"
                      value={form.special_requests}
                      onChange={(event) => handleFieldChange('special_requests', event.target.value)}
                      className="mt-1 bg-white border-stone-300 text-black"
                      placeholder="Accessibility, arrival notes, bunk preference"
                    />
                  </div>
                </div>

                {additionalGuestCount > 0 ? (
                  <div className="mt-5 space-y-4">
                    {form.guests.slice(0, additionalGuestCount).map((guest, index) => (
                      <div key={`guest-${index}`} className="rounded-[20px] border border-stone-200 bg-stone-50 p-4">
                        <p className="text-sm font-semibold text-stone-900">Guest {index + 1}</p>
                        <div className="mt-3 grid gap-4 md:grid-cols-2">
                          <div>
                            <Label htmlFor={`lodging-guest-name-${index}`} className="text-black">Guest Name</Label>
                            <Input
                              id={`lodging-guest-name-${index}`}
                              value={guest.name}
                              onChange={(event) => handleGuestChange(index, 'name', event.target.value)}
                              className="mt-1 bg-white border-stone-300 text-black"
                            />
                          </div>
                          <div>
                            <Label htmlFor={`lodging-guest-email-${index}`} className="text-black">Guest Email</Label>
                            <Input
                              id={`lodging-guest-email-${index}`}
                              type="email"
                              value={guest.email}
                              onChange={(event) => handleGuestChange(index, 'email', event.target.value)}
                              className="mt-1 bg-white border-stone-300 text-black"
                              placeholder="Optional"
                            />
                          </div>
                          <div className="md:col-span-2">
                            <Label htmlFor={`lodging-guest-notes-${index}`} className="text-black">Guest Notes</Label>
                            <Input
                              id={`lodging-guest-notes-${index}`}
                              value={guest.notes}
                              onChange={(event) => handleGuestChange(index, 'notes', event.target.value)}
                              className="mt-1 bg-white border-stone-300 text-black"
                              placeholder="Arrival notes or access details"
                            />
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="mt-5 rounded-[20px] border border-dashed border-stone-200 bg-stone-50 px-4 py-4 text-sm text-stone-600">
                    This reservation is currently set for the member only. Increase party size to add guest details.
                  </div>
                )}
              </div>

              <div className="rounded-[24px] border border-stone-200 bg-[#fffaf0] p-5">
                <div className="flex items-start gap-3">
                  <div className="rounded-2xl bg-[#b71c1c]/10 p-3 text-[#8f1515]">
                    <CreditCard className="h-5 w-5" />
                  </div>
                  <div className="flex-1">
                    <h3 className="text-lg font-semibold text-stone-900">Placeholder Payment</h3>
                    <p className="mt-1 text-sm text-stone-600">
                      You’ll continue to the demo payment processor to finish this fake lodging reservation.
                    </p>
                  </div>
                </div>

                <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                  <div className="rounded-[18px] border border-stone-200 bg-white px-4 py-4">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Stay</p>
                    <p className="mt-2 text-sm font-medium text-stone-900">{formatLodgingDateRange(form.check_in, form.check_out)}</p>
                  </div>
                  <div className="rounded-[18px] border border-stone-200 bg-white px-4 py-4">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Nightly rate</p>
                    <p className="mt-2 text-sm font-medium text-stone-900">{formatDollars(selectedSite.nightlyRate)}</p>
                  </div>
                  <div className="rounded-[18px] border border-stone-200 bg-white px-4 py-4">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Party size</p>
                    <p className="mt-2 text-sm font-medium text-stone-900">{form.party_size} guests</p>
                  </div>
                  <div className="rounded-[18px] border border-stone-200 bg-white px-4 py-4">
                    <p className="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-stone-500">Estimated total</p>
                    <p className="mt-2 text-sm font-semibold text-stone-900">{formatDollars(estimatedStay.total)}</p>
                  </div>
                </div>

                <div className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center gap-2 text-sm text-stone-600">
                    <MapPin className="h-4 w-4 text-[#8a6a19]" />
                    <span>{selectedSite.location}</span>
                  </div>
                  <Button
                    type="button"
                    onClick={handleReservationSubmit}
                    className="bg-[#b71c1c] text-white hover:bg-[#8f1515]"
                  >
                    Continue to Placeholder Payment
                  </Button>
                </div>
              </div>
            </div>
          </motion.section>
        </div>
      </div>
    </>
  );
};

export default LodgingPage;
