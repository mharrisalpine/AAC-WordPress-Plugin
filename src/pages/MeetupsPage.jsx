import React, { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import { CalendarDays, CheckCircle2, MapPin, Mountain, Ticket } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/components/ui/use-toast';
import { useAuth } from '@/hooks/useAuth';
import { getMemberEventRegistrations, getFakeEvents, registerForFakeEvent } from '@/lib/fakeEvents';
import { getFullName } from '@/lib/memberProfile';
import { useFakePayment } from '@/hooks/useFakePayment';
import { createEventPaymentIntent } from '@/lib/fakePaymentFlows';

const MeetupsPage = () => {
  const { user, profile } = useAuth();
  const { startPaymentFlow } = useFakePayment();
  const { toast } = useToast();
  const [registrations, setRegistrations] = useState([]);
  const [activeEventId, setActiveEventId] = useState(null);
  const [formState, setFormState] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    emergency_contact: '',
    emergency_contact_phone: '',
    notes: '',
  });

  useEffect(() => {
    if (!user?.id) return;
    setRegistrations(getMemberEventRegistrations(user.id));
  }, [user?.id]);

  const events = useMemo(() => getFakeEvents(), []);
  const registeredEventIds = useMemo(
    () => new Set(registrations.map((registration) => registration.eventId)),
    [registrations]
  );
  const myEvents = useMemo(
    () =>
      registrations
        .map((registration) => {
          const matchedEvent = events.find((event) => event.id === registration.eventId);
          return matchedEvent ? { ...matchedEvent, registration } : null;
        })
        .filter(Boolean),
    [events, registrations]
  );

  const prefillForm = () => {
    setFormState({
      first_name: profile?.account_info?.first_name || '',
      last_name: profile?.account_info?.last_name || '',
      email: profile?.account_info?.email || user?.email || '',
      phone: profile?.account_info?.phone || '',
      emergency_contact: '',
      emergency_contact_phone: '',
      notes: '',
    });
  };

  const handleOpenRegistration = (eventId) => {
    prefillForm();
    setActiveEventId((current) => (current === eventId ? null : eventId));
  };

  const handleRegister = (eventId) => {
    if (!user?.id) return;
    if (!formState.first_name || !formState.last_name || !formState.email) {
      toast({
        title: 'Missing registration details',
        description: 'First name, last name, and email are required to register.',
        variant: 'destructive',
      });
      return;
    }

    const event = events.find((item) => item.id === eventId);
    if (!event) return;

    if ((event.price_amount || 0) > 0) {
      startPaymentFlow(createEventPaymentIntent({
        event,
        registration: formState,
      }));
      setActiveEventId(null);
      return;
    }

    registerForFakeEvent({
      memberId: user.id,
      eventId,
      registration: formState,
    });

    setRegistrations(getMemberEventRegistrations(user.id));
    setActiveEventId(null);
    toast({
      title: 'Event registration complete',
      description: 'Your spot has been saved under My Events.',
    });
  };

  return (
    <>
      <Helmet>
        <title>AAC Events - American Alpine Club</title>
        <meta name="description" content="Register for American Alpine Club events and manage your upcoming event schedule." />
      </Helmet>
      <div className="pt-24 min-h-screen px-4 pb-12">
        <div className="max-w-6xl mx-auto space-y-8">
          <motion.div
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className="text-center"
          >
            <div className="inline-flex items-center justify-center rounded-full bg-[#c8a43a] p-4 text-black mb-4">
              <Mountain className="w-7 h-7" />
            </div>
            <h1 className="text-4xl font-bold text-black mb-3">Member Events</h1>
            <p className="text-lg text-black/75 max-w-3xl mx-auto">
              Register for guided clinics, social climbing nights, and community gatherings built for AAC members.
            </p>
          </motion.div>

          {myEvents.length > 0 && (
            <section className="space-y-4">
              <div className="flex items-center gap-3">
                <div className="rounded-full bg-[#b71c1c] p-3 text-white">
                  <Ticket className="w-5 h-5" />
                </div>
                <div>
                  <h2 className="text-2xl font-bold text-black">My Events</h2>
                  <p className="text-black/75">Your upcoming AAC registrations.</p>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                {myEvents.map((event) => (
                  <div
                    key={event.id}
                    className="card-gradient rounded-[24px] border border-stone-200 p-5"
                  >
                    <div className="flex items-start justify-between gap-4">
                      <div>
                        <p className="text-xs uppercase tracking-[0.25em] text-[#c8a43a] mb-2">
                          Registered
                        </p>
                        <h3 className="text-xl font-bold text-black">{event.title}</h3>
                      </div>
                      <div className="rounded-full bg-[rgba(200,164,58,0.2)] p-2 text-[#6b5310]">
                        <CheckCircle2 className="w-5 h-5" />
                      </div>
                    </div>

                    <div className="mt-4 space-y-2 text-sm text-black/80">
                      <div className="flex items-center gap-2">
                        <CalendarDays className="w-4 h-4 text-[#c8a43a]" />
                        <span>{new Date(event.date).toLocaleDateString()} • {event.time}</span>
                      </div>
                      <div className="flex items-center gap-2">
                        <MapPin className="w-4 h-4 text-[#c8a43a]" />
                        <span>{event.location}</span>
                      </div>
                    </div>

                    <div className="mt-4 rounded-2xl bg-stone-50 border border-stone-200 px-4 py-3 text-sm text-black/75">
                      Registered as {getFullName(event.registration)} ({event.registration.email})
                    </div>
                  </div>
                ))}
              </div>
            </section>
          )}

          <section className="space-y-4">
            <div>
              <h2 className="text-2xl font-bold text-black">Upcoming Events</h2>
              <p className="text-black/75 mt-1">Choose an event and we’ll prefill your registration from your member profile.</p>
            </div>

            <div className="grid gap-6">
              {events.map((event, index) => {
                const isRegistered = registeredEventIds.has(event.id);
                const isActiveForm = activeEventId === event.id;

                return (
                  <motion.div
                    key={event.id}
                    initial={{ opacity: 0, y: 16 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.35, delay: index * 0.06 }}
                    className="card-gradient rounded-[28px] border border-stone-200 p-6"
                  >
                    <div className="grid gap-6 lg:grid-cols-[1fr,0.72fr]">
                      <div>
                        <div className="flex flex-wrap items-center gap-2 mb-3">
                          <span className="rounded-full bg-[rgba(200,164,58,0.2)] px-3 py-1 text-xs uppercase tracking-[0.2em] text-[#5c4a12]">
                            {event.level}
                          </span>
                          <span className="rounded-full bg-stone-100 px-3 py-1 text-xs uppercase tracking-[0.2em] text-black/80">
                            {event.price}
                          </span>
                        </div>
                        <h3 className="text-2xl font-bold text-black">{event.title}</h3>
                        <p className="text-black/75 mt-3">{event.summary}</p>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2 text-sm text-black/80">
                          <div className="rounded-2xl bg-stone-50 px-4 py-3 border border-stone-200">
                            <div className="flex items-center gap-2 mb-1">
                              <CalendarDays className="w-4 h-4 text-[#a07f21]" />
                              <span className="text-black font-medium">When</span>
                            </div>
                            {new Date(event.date).toLocaleDateString()} • {event.time}
                          </div>
                          <div className="rounded-2xl bg-stone-50 px-4 py-3 border border-stone-200">
                            <div className="flex items-center gap-2 mb-1">
                              <MapPin className="w-4 h-4 text-[#a07f21]" />
                              <span className="text-black font-medium">Where</span>
                            </div>
                            {event.location}
                          </div>
                        </div>
                      </div>

                      <div className="rounded-[24px] border border-stone-200 bg-white/60 p-5 flex flex-col justify-between">
                        <div>
                          <p className="text-sm uppercase tracking-[0.25em] text-[#a07f21] mb-2">Registration</p>
                          <p className="text-black/75">
                            {isRegistered
                              ? 'You are already registered for this event.'
                              : 'Reserve your spot and we’ll use your member profile to speed up sign-up.'}
                          </p>
                        </div>
                        <Button
                          type="button"
                          disabled={isRegistered}
                          onClick={() => handleOpenRegistration(event.id)}
                          className="mt-6 w-full bg-[#b71c1c] hover:bg-[#8f1515] text-white disabled:bg-stone-200 disabled:text-black/40"
                        >
                          {isRegistered ? 'Registered' : 'Register'}
                        </Button>
                      </div>
                    </div>

                    {isActiveForm && !isRegistered && (
                      <div className="mt-6 rounded-[24px] border border-stone-200 bg-stone-100/90 p-5">
                        <h4 className="text-xl font-bold text-black mb-4">Registration Form</h4>
                        <div className="grid gap-4 md:grid-cols-2">
                          <div>
                            <Label htmlFor={`${event.id}-first`} className="text-black">First Name</Label>
                            <Input
                              id={`${event.id}-first`}
                              value={formState.first_name}
                              onChange={(e) => setFormState({ ...formState, first_name: e.target.value })}
                              className="bg-white border-[#d9d9d9] text-black mt-1"
                            />
                          </div>
                          <div>
                            <Label htmlFor={`${event.id}-last`} className="text-black">Last Name</Label>
                            <Input
                              id={`${event.id}-last`}
                              value={formState.last_name}
                              onChange={(e) => setFormState({ ...formState, last_name: e.target.value })}
                              className="bg-white border-[#d9d9d9] text-black mt-1"
                            />
                          </div>
                          <div>
                            <Label htmlFor={`${event.id}-email`} className="text-black">Email</Label>
                            <Input
                              id={`${event.id}-email`}
                              value={formState.email}
                              onChange={(e) => setFormState({ ...formState, email: e.target.value })}
                              className="bg-white border-[#d9d9d9] text-black mt-1"
                            />
                          </div>
                          <div>
                            <Label htmlFor={`${event.id}-phone`} className="text-black">Phone</Label>
                            <Input
                              id={`${event.id}-phone`}
                              value={formState.phone}
                              onChange={(e) => setFormState({ ...formState, phone: e.target.value })}
                              className="bg-white border-[#d9d9d9] text-black mt-1"
                            />
                          </div>
                          <div>
                            <Label htmlFor={`${event.id}-emergency`} className="text-black">Emergency Contact</Label>
                            <Input
                              id={`${event.id}-emergency`}
                              value={formState.emergency_contact}
                              onChange={(e) => setFormState({ ...formState, emergency_contact: e.target.value })}
                              className="bg-white border-[#d9d9d9] text-black mt-1"
                              placeholder="Emergency contact name"
                            />
                          </div>
                          <div>
                            <Label htmlFor={`${event.id}-emergency-phone`} className="text-black">Emergency Contact Phone Number</Label>
                            <Input
                              id={`${event.id}-emergency-phone`}
                              value={formState.emergency_contact_phone}
                              onChange={(e) => setFormState({ ...formState, emergency_contact_phone: e.target.value })}
                              className="bg-white border-[#d9d9d9] text-black mt-1"
                              placeholder="Emergency contact phone number"
                            />
                          </div>
                          <div>
                            <Label htmlFor={`${event.id}-notes`} className="text-black">Notes</Label>
                            <Input
                              id={`${event.id}-notes`}
                              value={formState.notes}
                              onChange={(e) => setFormState({ ...formState, notes: e.target.value })}
                              className="bg-white border-[#d9d9d9] text-black mt-1"
                              placeholder="Gear needs, partner requests, etc."
                            />
                          </div>
                        </div>

                        <div className="mt-5 flex flex-col sm:flex-row gap-3">
                          <Button
                            type="button"
                            onClick={() => handleRegister(event.id)}
                            className="bg-[#c8a43a] hover:bg-[#a07f21] text-black"
                          >
                            {event.price_amount > 0 ? `Proceed to Checkout • $${event.price_amount}` : 'Confirm Registration'}
                          </Button>
                          <Button
                            type="button"
                            variant="outline"
                            onClick={() => setActiveEventId(null)}
                            className="border-stone-400 text-black hover:bg-stone-200"
                          >
                            Cancel
                          </Button>
                        </div>
                      </div>
                    )}
                  </motion.div>
                );
              })}
            </div>
          </section>
        </div>
      </div>
    </>
  );
};

export default MeetupsPage;
