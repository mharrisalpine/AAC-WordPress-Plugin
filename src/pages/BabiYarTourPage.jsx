import React, { useEffect, useState } from 'react';
import { Helmet } from 'react-helmet';
import { motion } from 'framer-motion';
import {
  ArrowUpRight,
  BookOpenText,
  Compass,
  Info,
  Leaf,
  LocateFixed,
  MapPinned,
  Play,
  Square,
  Volume2,
  VolumeX,
  Waves,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  BABI_YAR_PARK_SOURCES,
  BABI_YAR_TOUR_INTRO,
  BABI_YAR_TOUR_STOPS,
} from '@/data/babiYarTour';
import { formatDistance, useParkTour } from '@/hooks/useParkTour';
import { cn } from '@/lib/utils';

const orderedStops = [...BABI_YAR_TOUR_STOPS].sort((leftStop, rightStop) => leftStop.order - rightStop.order);
const routePath = orderedStops
  .map((stop, index) => `${index === 0 ? 'M' : 'L'} ${stop.mapPoint.x} ${stop.mapPoint.y}`)
  .join(' ');

const permissionCopy = {
  idle: 'Location is off. Turn it on when you are ready to walk the memorial.',
  prompt: 'This device can ask for your location when you start the live tour.',
  locating: 'Finding your position and listening for the next memorial zone.',
  granted: 'Live route guidance is active.',
  denied: 'Location was denied. You can still use preview mode to walk through the tour.',
  unsupported: 'This device does not expose browser geolocation, so preview mode is the fallback.',
  error: 'The app could not keep tracking your position. You can retry or continue in preview mode.',
};

const getStopById = (stopId) => orderedStops.find((stop) => stop.id === stopId) || orderedStops[0];

const ExternalLink = ({ href, children }) => (
  <a
    href={href}
    target="_blank"
    rel="noreferrer"
    className="inline-flex items-center gap-2 text-[0.72rem] font-medium uppercase tracking-[0.22em] text-[#c4b28e] transition hover:text-[#f4ead9]"
  >
    {children}
    <ArrowUpRight className="h-3.5 w-3.5" />
  </a>
);

function MemorialRouteMap({ activeStopId, onSelectStop, selectedStopId, visitedStopIds }) {
  return (
    <div className="memorial-panel memorial-grid relative overflow-hidden rounded-[28px] p-5">
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <p className="text-[0.68rem] uppercase tracking-[0.32em] text-[#9d8f76]">Memorial Route</p>
          <h2 className="mt-2 text-lg font-semibold text-[#f6efe3]">Spatial sequence</h2>
        </div>
        <div className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[0.7rem] uppercase tracking-[0.26em] text-[#c7b89d]">
          {visitedStopIds.length}/{orderedStops.length} reached
        </div>
      </div>

      <svg viewBox="0 0 100 100" className="h-[260px] w-full">
        <defs>
          <linearGradient id="routeGlow" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stopColor="#766451" />
            <stop offset="100%" stopColor="#e4d0ad" />
          </linearGradient>
        </defs>

        <path
          d={routePath}
          fill="none"
          stroke="rgba(223, 211, 187, 0.18)"
          strokeWidth="1.2"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        <path
          d="M18 80 L49 48 L79 54 L34 64 L66 35 L58 75 Z"
          fill="rgba(228, 208, 173, 0.04)"
          stroke="rgba(228, 208, 173, 0.1)"
          strokeWidth="0.8"
        />
        <path
          d={routePath}
          fill="none"
          stroke="url(#routeGlow)"
          strokeWidth="0.45"
          strokeDasharray="1.5 2.5"
          opacity="0.9"
        />

        {orderedStops.map((stop) => {
          const isActive = stop.id === activeStopId;
          const isSelected = stop.id === selectedStopId;
          const isVisited = visitedStopIds.includes(stop.id);

          return (
            <g key={stop.id}>
              <circle
                cx={stop.mapPoint.x}
                cy={stop.mapPoint.y}
                r={isActive ? 7.5 : 5.6}
                fill={isActive ? 'rgba(228, 208, 173, 0.16)' : 'rgba(228, 208, 173, 0.05)'}
                className={isActive ? 'memorial-pulse' : ''}
              />
              <circle
                cx={stop.mapPoint.x}
                cy={stop.mapPoint.y}
                r={isVisited ? 3.8 : 3.1}
                fill={isSelected ? '#f4ead9' : isVisited ? '#d6c19a' : '#665748'}
                stroke={isActive ? '#f4ead9' : 'rgba(244, 234, 217, 0.36)'}
                strokeWidth={isActive ? 1.6 : 0.8}
              />
              <text
                x={stop.mapPoint.x + 5}
                y={stop.mapPoint.y - 5}
                fill={isSelected ? '#f4ead9' : '#c4b28e'}
                fontSize="4.2"
                letterSpacing="0.18em"
              >
                {String(stop.order).padStart(2, '0')}
              </text>
            </g>
          );
        })}
      </svg>

      <div className="mt-4 grid grid-cols-2 gap-2">
        {orderedStops.map((stop) => {
          const isActive = stop.id === activeStopId;
          const isSelected = stop.id === selectedStopId;

          return (
            <button
              key={stop.id}
              type="button"
              onClick={() => onSelectStop(stop.id)}
              className={cn(
                'rounded-[20px] border px-3 py-3 text-left transition',
                isSelected
                  ? 'border-[#d8c19a] bg-[#ede0c7]/10'
                  : 'border-white/8 bg-black/10 hover:border-[#8d7a60] hover:bg-white/[0.04]'
              )}
            >
              <p className="text-[0.65rem] uppercase tracking-[0.24em] text-[#8f8169]">{String(stop.order).padStart(2, '0')}</p>
              <p className="mt-1 text-sm font-medium text-[#f6efe3]">{stop.shortTitle}</p>
              <p className="mt-1 text-xs leading-5 text-[#b8ab93]">{isActive ? 'Active stop' : stop.subtitle}</p>
            </button>
          );
        })}
      </div>
    </div>
  );
}

function StopDetailsDialog({ onReplay, open, onOpenChange, stop }) {
  if (!stop) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="border border-white/10 bg-[#11151b] p-0 text-[#f4ead9] shadow-[0_32px_80px_rgba(0,0,0,0.45)] sm:max-w-[680px]">
        <div className="memorial-grid rounded-[26px] p-6 sm:p-8">
          <DialogHeader className="pr-10 text-left">
            <p className="text-[0.68rem] uppercase tracking-[0.32em] text-[#9d8f76]">Historical Context</p>
            <DialogTitle className="mt-3 text-2xl font-semibold text-[#f8f3ea]">{stop.title}</DialogTitle>
            <DialogDescription className="mt-2 max-w-xl text-sm leading-7 text-[#cbbda4]">
              {stop.subtitle}
            </DialogDescription>
          </DialogHeader>

          <div className="mt-6 grid gap-5">
            <div className="rounded-[24px] border border-white/8 bg-black/20 p-5">
              <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">History</p>
              <p className="mt-3 text-sm leading-7 text-[#ece2d2]">{stop.history}</p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="rounded-[24px] border border-white/8 bg-white/[0.03] p-5">
                <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Why It Matters</p>
                <p className="mt-3 text-sm leading-7 text-[#ded1bb]">{stop.whyItMatters}</p>
              </div>
              <div className="rounded-[24px] border border-white/8 bg-white/[0.03] p-5">
                <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Reflection Prompt</p>
                <p className="mt-3 text-sm leading-7 text-[#ded1bb]">{stop.reflection}</p>
              </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-[24px] border border-white/8 bg-[#191f28] px-5 py-4">
              <div>
                <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Narration</p>
                <p className="mt-1 text-sm text-[#eaddc8]">Replay the spoken track for this stop.</p>
              </div>
              <Button
                type="button"
                onClick={() => onReplay(stop)}
                className="border border-[#d8c19a]/40 bg-[#d8c19a]/10 px-5 text-[#f8f1e4] hover:bg-[#d8c19a]/20"
              >
                <Play className="mr-2 h-4 w-4" />
                Replay narration
              </Button>
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}

export default function BabiYarTourPage() {
  const {
    activeStop,
    distanceToRoute,
    entryEvent,
    geolocationSupported,
    liveStopDistance,
    locationError,
    narrationEnabled,
    permissionState,
    position,
    previewMode,
    previewStopId,
    requestLocationAccess,
    setNarrationEnabled,
    setPreviewMode,
    setPreviewStopId,
    speechState,
    speechSupported,
    speakStop,
    stopNarration,
    visitedStopIds,
  } = useParkTour(orderedStops);
  const [detailsOpen, setDetailsOpen] = useState(false);
  const [selectedStopId, setSelectedStopId] = useState(activeStop?.id || orderedStops[0].id);

  const selectedStop = getStopById(selectedStopId);
  const locationSummary =
    permissionCopy[permissionState] || permissionCopy.idle;

  useEffect(() => {
    if (!activeStop) {
      return;
    }

    setSelectedStopId(activeStop.id);
  }, [activeStop?.id]);

  useEffect(() => {
    if (!entryEvent?.stopId) {
      return;
    }

    setSelectedStopId(entryEvent.stopId);
    setDetailsOpen(true);
  }, [entryEvent?.enteredAt]);

  const handleSelectStop = (stopId) => {
    setSelectedStopId(stopId);

    if (previewMode) {
      setPreviewStopId(stopId);
    }
  };

  const handleReplayNarration = (stop = selectedStop) => {
    if (!stop) {
      return;
    }

    speakStop(stop);
  };

  return (
    <>
      <Helmet>
        <title>Babi Yar Park Tour</title>
        <meta
          name="description"
          content="A mobile-first self-guided tour prototype for Babi Yar Park with location-aware narration and historical context."
        />
      </Helmet>

      <div className="memorial-app min-h-screen">
        <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 pb-[calc(2rem+env(safe-area-inset-bottom,0px))] pt-[calc(1rem+env(safe-area-inset-top,0px))] sm:px-6 lg:px-8">
          <motion.section
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.55, ease: [0.22, 1, 0.36, 1] }}
            className="memorial-panel memorial-grid overflow-hidden rounded-[34px] p-6 sm:p-8"
          >
            <div className="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
              <div className="max-w-3xl">
                <p className="text-[0.68rem] uppercase tracking-[0.36em] text-[#998b73]">
                  {BABI_YAR_TOUR_INTRO.eyebrow}
                </p>
                <h1 className="mt-4 max-w-2xl text-4xl font-semibold tracking-[0.02em] text-[#f8f3ea] sm:text-5xl">
                  {BABI_YAR_TOUR_INTRO.title}
                </h1>
                <p className="mt-4 max-w-2xl text-base leading-7 text-[#ded1bb] sm:text-lg">
                  {BABI_YAR_TOUR_INTRO.subtitle}
                </p>
                <p className="mt-5 max-w-2xl text-sm leading-7 text-[#b8ab93]">{BABI_YAR_TOUR_INTRO.body}</p>
              </div>

              <div className="max-w-md rounded-[28px] border border-white/10 bg-black/20 p-5">
                <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Content Advisory</p>
                <p className="mt-3 text-sm leading-7 text-[#eadcc8]">{BABI_YAR_TOUR_INTRO.advisory}</p>
              </div>
            </div>
          </motion.section>

          <section className="mt-5 grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
            <motion.div
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.06, duration: 0.5 }}
              className="memorial-panel rounded-[30px] p-5 sm:p-6"
            >
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Guide Status</p>
                  <h2 className="mt-2 text-xl font-semibold text-[#f8f3ea]">
                    {previewMode ? 'Preview mode is active' : 'Live route guidance'}
                  </h2>
                </div>
                <div className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-[0.7rem] uppercase tracking-[0.22em] text-[#c4b28e]">
                  {speechSupported ? 'Voice ready' : 'Text only'}
                </div>
              </div>

              <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <div className="rounded-[24px] border border-white/8 bg-black/20 p-4">
                  <div className="flex items-center gap-3 text-[#eadcc8]">
                    <LocateFixed className="h-4 w-4 text-[#d8c19a]" />
                    <p className="text-sm font-medium">Location</p>
                  </div>
                  <p className="mt-3 text-sm leading-7 text-[#c7b89d]">{locationSummary}</p>
                  {locationError ? <p className="mt-2 text-xs leading-6 text-[#d09b8e]">{locationError}</p> : null}
                  <div className="mt-4 flex flex-wrap gap-3">
                    <Button
                      type="button"
                      onClick={requestLocationAccess}
                      className="border border-[#d8c19a]/40 bg-[#d8c19a]/10 px-5 text-[#f8f1e4] hover:bg-[#d8c19a]/20"
                    >
                      <LocateFixed className="mr-2 h-4 w-4" />
                      Enable live location
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={() => setPreviewMode(!previewMode)}
                      className="border border-white/10 px-5 text-[#e7dac6] hover:bg-white/[0.06] hover:text-white"
                    >
                      <Compass className="mr-2 h-4 w-4" />
                      {previewMode ? 'Leave preview mode' : 'Preview stops'}
                    </Button>
                  </div>
                </div>

                <div className="rounded-[24px] border border-white/8 bg-black/20 p-4">
                  <div className="flex items-center justify-between gap-4">
                    <div>
                      <div className="flex items-center gap-3 text-[#eadcc8]">
                        {narrationEnabled ? (
                          <Volume2 className="h-4 w-4 text-[#d8c19a]" />
                        ) : (
                          <VolumeX className="h-4 w-4 text-[#8f8169]" />
                        )}
                        <p className="text-sm font-medium">Narration</p>
                      </div>
                      <p className="mt-3 text-sm leading-7 text-[#c7b89d]">
                        Spoken context can trigger automatically when you enter a stop, or stay muted if you prefer a quieter visit.
                      </p>
                    </div>
                    <Switch checked={narrationEnabled} onCheckedChange={setNarrationEnabled} />
                  </div>

                  <div className="mt-4 flex flex-wrap gap-3">
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={() => handleReplayNarration(activeStop)}
                      className="border border-white/10 px-5 text-[#e7dac6] hover:bg-white/[0.06] hover:text-white"
                    >
                      <Play className="mr-2 h-4 w-4" />
                      Replay current stop
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      onClick={stopNarration}
                      className="border border-white/10 px-5 text-[#e7dac6] hover:bg-white/[0.06] hover:text-white"
                    >
                      <Square className="mr-2 h-4 w-4" />
                      Stop audio
                    </Button>
                  </div>

                  <p className="mt-3 text-xs uppercase tracking-[0.22em] text-[#8f8169]">
                    Voice state: {speechState}
                  </p>
                </div>
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.12, duration: 0.5 }}
              className="memorial-panel rounded-[30px] p-5 sm:p-6"
            >
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Current Focus</p>
                  <h2 className="mt-2 text-xl font-semibold text-[#f8f3ea]">{activeStop.title}</h2>
                  <p className="mt-2 text-sm leading-7 text-[#cbbda4]">{activeStop.subtitle}</p>
                </div>
                <button
                  type="button"
                  onClick={() => setDetailsOpen(true)}
                  className="rounded-full border border-white/10 bg-white/[0.04] p-3 text-[#eadcc8] transition hover:bg-white/[0.1]"
                  aria-label="Open stop details"
                >
                  <Info className="h-4 w-4" />
                </button>
              </div>

              <div className="mt-5 grid gap-4">
                <div className="rounded-[24px] border border-white/8 bg-black/20 p-5">
                  <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Why This Stop Matters</p>
                  <p className="mt-3 text-sm leading-7 text-[#ece2d2]">{activeStop.significance}</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="rounded-[24px] border border-white/8 bg-white/[0.03] p-5">
                    <div className="flex items-center gap-3 text-[#eadcc8]">
                      <MapPinned className="h-4 w-4 text-[#d8c19a]" />
                      <p className="text-sm font-medium">Positioning</p>
                    </div>
                    <p className="mt-3 text-sm leading-7 text-[#d7cab6]">
                      {previewMode
                        ? `Previewing stop ${String(activeStop.order).padStart(2, '0')} of ${orderedStops.length}.`
                        : liveStopDistance !== null
                          ? `Inside the stop zone. Approximate distance to marker: ${formatDistance(liveStopDistance)}.`
                          : distanceToRoute !== null
                            ? `Nearest memorial stop is ${formatDistance(distanceToRoute)} away.`
                            : 'Position data is not active yet.'}
                    </p>
                  </div>
                  <div className="rounded-[24px] border border-white/8 bg-white/[0.03] p-5">
                    <div className="flex items-center gap-3 text-[#eadcc8]">
                      <Waves className="h-4 w-4 text-[#d8c19a]" />
                      <p className="text-sm font-medium">Reflection</p>
                    </div>
                    <p className="mt-3 text-sm leading-7 text-[#d7cab6]">{activeStop.reflection}</p>
                  </div>
                </div>

                {position ? (
                  <p className="text-xs uppercase tracking-[0.18em] text-[#867864]">
                    Live position accuracy: {Math.round(position.accuracy || 0)} m
                  </p>
                ) : null}
              </div>
            </motion.div>
          </section>

          <section className="mt-5 grid gap-5 lg:grid-cols-[0.95fr_1.05fr]">
            <motion.div
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.18, duration: 0.5 }}
            >
              <MemorialRouteMap
                activeStopId={activeStop.id}
                onSelectStop={handleSelectStop}
                selectedStopId={selectedStop.id}
                visitedStopIds={visitedStopIds}
              />
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.24, duration: 0.5 }}
              className="grid gap-5"
            >
              <div className="memorial-panel rounded-[30px] p-5 sm:p-6">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Selected Stop</p>
                    <h2 className="mt-2 text-xl font-semibold text-[#f8f3ea]">{selectedStop.title}</h2>
                    <p className="mt-2 text-sm leading-7 text-[#cbbda4]">{selectedStop.history}</p>
                  </div>
                  <div className="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-[0.68rem] uppercase tracking-[0.22em] text-[#c7b89d]">
                    {selectedStop.duration}
                  </div>
                </div>

                <div className="mt-5 grid gap-4 sm:grid-cols-2">
                  <div className="rounded-[24px] border border-white/8 bg-black/20 p-5">
                    <div className="flex items-center gap-3 text-[#eadcc8]">
                      <BookOpenText className="h-4 w-4 text-[#d8c19a]" />
                      <p className="text-sm font-medium">Interpretation</p>
                    </div>
                    <p className="mt-3 text-sm leading-7 text-[#d7cab6]">{selectedStop.whyItMatters}</p>
                  </div>

                  <div className="rounded-[24px] border border-white/8 bg-black/20 p-5">
                    <div className="flex items-center gap-3 text-[#eadcc8]">
                      <Leaf className="h-4 w-4 text-[#d8c19a]" />
                      <p className="text-sm font-medium">Visit Use</p>
                    </div>
                    <p className="mt-3 text-sm leading-7 text-[#d7cab6]">
                      Tap this stop on the route map in preview mode to rehearse the visit flow before you arrive onsite.
                    </p>
                  </div>
                </div>

                <div className="mt-5 flex flex-wrap gap-3">
                  <Button
                    type="button"
                    onClick={() => {
                      setPreviewMode(true);
                      setPreviewStopId(selectedStop.id);
                    }}
                    className="border border-[#d8c19a]/40 bg-[#d8c19a]/10 px-5 text-[#f8f1e4] hover:bg-[#d8c19a]/20"
                  >
                    <Compass className="mr-2 h-4 w-4" />
                    Preview this stop
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    onClick={() => {
                      setSelectedStopId(selectedStop.id);
                      setDetailsOpen(true);
                    }}
                    className="border border-white/10 px-5 text-[#e7dac6] hover:bg-white/[0.06] hover:text-white"
                  >
                    <Info className="mr-2 h-4 w-4" />
                    Open history panel
                  </Button>
                </div>
              </div>

              <div className="memorial-panel rounded-[30px] p-5 sm:p-6">
                <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Source Notes</p>
                <p className="mt-3 text-sm leading-7 text-[#d7cab6]">
                  Historical descriptions in this prototype are adapted from the Mizel Museum’s park page and The Cultural Landscape Foundation’s landscape record. Geofence coordinates are approximate and should be calibrated onsite before release.
                </p>
                <div className="mt-5 flex flex-wrap gap-4">
                  {BABI_YAR_PARK_SOURCES.map((source) => (
                    <ExternalLink key={source.url} href={source.url}>
                      {source.label}
                    </ExternalLink>
                  ))}
                </div>
                <div className="mt-5 rounded-[24px] border border-white/8 bg-black/20 p-4">
                  <p className="text-[0.68rem] uppercase tracking-[0.28em] text-[#9d8f76]">Release Note</p>
                  <p className="mt-3 text-sm leading-7 text-[#d7cab6]">
                    The current narration uses the device’s speech engine so the tour is immediately testable without recorded audio files. A later pass can swap in professionally voiced tracks.
                  </p>
                </div>
                {!geolocationSupported ? (
                  <p className="mt-4 text-xs uppercase tracking-[0.18em] text-[#a88a7f]">
                    Live location is unavailable on this device.
                  </p>
                ) : null}
              </div>
            </motion.div>
          </section>
        </div>
      </div>

      <StopDetailsDialog
        onReplay={handleReplayNarration}
        open={detailsOpen}
        onOpenChange={setDetailsOpen}
        stop={selectedStop}
      />
    </>
  );
}
