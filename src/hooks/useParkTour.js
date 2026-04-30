import { useEffect, useRef, useState } from 'react';

const EARTH_RADIUS_METERS = 6371000;

const toRadians = (value) => (value * Math.PI) / 180;

const getDistanceMeters = (from, to) => {
  const latitudeDelta = toRadians(to.lat - from.lat);
  const longitudeDelta = toRadians(to.lng - from.lng);
  const fromLatitude = toRadians(from.lat);
  const toLatitude = toRadians(to.lat);

  const haversine =
    Math.sin(latitudeDelta / 2) * Math.sin(latitudeDelta / 2) +
    Math.cos(fromLatitude) *
      Math.cos(toLatitude) *
      Math.sin(longitudeDelta / 2) *
      Math.sin(longitudeDelta / 2);

  const arc = 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
  return EARTH_RADIUS_METERS * arc;
};

const getNearestStop = (position, stops) => {
  let bestMatch = null;

  stops.forEach((stop) => {
    if (!stop.coordinates) {
      return;
    }

    const distanceMeters = getDistanceMeters(position, {
      lat: stop.coordinates.lat,
      lng: stop.coordinates.lng,
    });

    if (distanceMeters > stop.coordinates.radiusMeters) {
      return;
    }

    if (!bestMatch || distanceMeters < bestMatch.distanceMeters) {
      bestMatch = {
        id: stop.id,
        distanceMeters,
      };
    }
  });

  return bestMatch;
};

const getDistanceToRoute = (position, stops) => {
  let closest = null;

  stops.forEach((stop) => {
    if (!stop.coordinates) {
      return;
    }

    const distanceMeters = getDistanceMeters(position, {
      lat: stop.coordinates.lat,
      lng: stop.coordinates.lng,
    });

    if (closest === null || distanceMeters < closest) {
      closest = distanceMeters;
    }
  });

  return closest;
};

const findStopById = (stops, stopId) => stops.find((stop) => stop.id === stopId) || null;

const getPreferredVoice = () => {
  if (typeof window === 'undefined' || !window.speechSynthesis) {
    return null;
  }

  const voices = window.speechSynthesis.getVoices();

  return (
    voices.find((voice) => /en-US/i.test(voice.lang) && /female|samantha|ava|allison|zoe/i.test(voice.name)) ||
    voices.find((voice) => /en-US/i.test(voice.lang)) ||
    voices.find((voice) => /^en/i.test(voice.lang)) ||
    null
  );
};

export const formatDistance = (distanceMeters) => {
  if (distanceMeters === null || Number.isNaN(distanceMeters)) {
    return 'Not available';
  }

  if (distanceMeters < 1000) {
    return `${Math.round(distanceMeters)} m`;
  }

  return `${(distanceMeters / 1000).toFixed(1)} km`;
};

export function useParkTour(stops) {
  const [permissionState, setPermissionState] = useState('idle');
  const [position, setPosition] = useState(null);
  const [previewMode, setPreviewMode] = useState(false);
  const [previewStopId, setPreviewStopId] = useState(stops[0]?.id ?? null);
  const [liveStopId, setLiveStopId] = useState(null);
  const [liveStopDistance, setLiveStopDistance] = useState(null);
  const [visitedStopIds, setVisitedStopIds] = useState([]);
  const [entryEvent, setEntryEvent] = useState(null);
  const [narrationEnabled, setNarrationEnabled] = useState(true);
  const [speechState, setSpeechState] = useState('idle');
  const [locationError, setLocationError] = useState('');
  const watchIdRef = useRef(null);
  const lastSpokenStopIdRef = useRef(null);

  const autoNarrationStopId = previewMode ? previewStopId : liveStopId;
  const activeStop = findStopById(stops, previewMode ? previewStopId : liveStopId) || stops[0] || null;
  const distanceToRoute =
    position && Array.isArray(stops) && stops.length > 0 ? getDistanceToRoute(position, stops) : null;
  const speechSupported =
    typeof window !== 'undefined' && 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
  const geolocationSupported =
    typeof navigator !== 'undefined' && 'geolocation' in navigator;

  const stopNarration = () => {
    if (typeof window !== 'undefined' && window.speechSynthesis) {
      window.speechSynthesis.cancel();
    }

    setSpeechState('idle');
  };

  const speakStop = (stop) => {
    if (!stop || !speechSupported || typeof window === 'undefined') {
      return false;
    }

    window.speechSynthesis.cancel();

    const utterance = new window.SpeechSynthesisUtterance(stop.narration);
    const preferredVoice = getPreferredVoice();

    utterance.lang = 'en-US';
    utterance.rate = 0.9;
    utterance.pitch = 0.92;
    utterance.volume = 1;

    if (preferredVoice) {
      utterance.voice = preferredVoice;
    }

    utterance.onstart = () => setSpeechState('playing');
    utterance.onend = () => setSpeechState('idle');
    utterance.onerror = () => setSpeechState('error');

    setSpeechState('loading');
    window.speechSynthesis.speak(utterance);
    return true;
  };

  const updateLivePosition = (coords) => {
    const nextPosition = {
      lat: coords.latitude,
      lng: coords.longitude,
      accuracy: coords.accuracy,
    };

    setPosition(nextPosition);
    setPermissionState('granted');
    setLocationError('');

    const nearestStop = getNearestStop(nextPosition, stops);

    if (!nearestStop) {
      setLiveStopDistance(null);
      setLiveStopId(null);
      return;
    }

    setLiveStopDistance(nearestStop.distanceMeters);

    setVisitedStopIds((currentStops) => {
      if (currentStops.includes(nearestStop.id)) {
        return currentStops;
      }

      return [...currentStops, nearestStop.id];
    });

    setLiveStopId((currentStopId) => {
      if (currentStopId === nearestStop.id) {
        return currentStopId;
      }

      setEntryEvent({
        stopId: nearestStop.id,
        enteredAt: Date.now(),
      });

      return nearestStop.id;
    });
  };

  const requestLocationAccess = () => {
    if (!geolocationSupported) {
      setPermissionState('unsupported');
      setLocationError('This device does not expose browser geolocation.');
      return;
    }

    setPermissionState('locating');
    setLocationError('');

    navigator.geolocation.getCurrentPosition(
      (nextPosition) => {
        updateLivePosition(nextPosition.coords);

        if (watchIdRef.current !== null) {
          navigator.geolocation.clearWatch(watchIdRef.current);
        }

        watchIdRef.current = navigator.geolocation.watchPosition(
          (watchedPosition) => updateLivePosition(watchedPosition.coords),
          (error) => {
            setPermissionState(error.code === error.PERMISSION_DENIED ? 'denied' : 'error');
            setLocationError(error.message || 'Unable to continue tracking your position.');
          },
          {
            enableHighAccuracy: true,
            maximumAge: 15000,
            timeout: 15000,
          }
        );
      },
      (error) => {
        setPermissionState(error.code === error.PERMISSION_DENIED ? 'denied' : 'error');
        setLocationError(error.message || 'Unable to access your current location.');
      },
      {
        enableHighAccuracy: true,
        maximumAge: 0,
        timeout: 15000,
      }
    );
  };

  useEffect(() => {
    if (typeof navigator === 'undefined' || !navigator.permissions?.query) {
      return undefined;
    }

    let mounted = true;
    let permissionStatus = null;

    navigator.permissions
      .query({ name: 'geolocation' })
      .then((result) => {
        if (!mounted) {
          return;
        }

        permissionStatus = result;
        setPermissionState((currentState) => (currentState === 'idle' ? result.state : currentState));

        permissionStatus.onchange = () => {
          setPermissionState(permissionStatus.state);
        };
      })
      .catch(() => {});

    return () => {
      mounted = false;

      if (permissionStatus) {
        permissionStatus.onchange = null;
      }
    };
  }, []);

  useEffect(() => {
    if (!narrationEnabled) {
      stopNarration();
    }
  }, [narrationEnabled]);

  useEffect(() => {
    if (!autoNarrationStopId || !activeStop || !narrationEnabled) {
      return;
    }

    if (lastSpokenStopIdRef.current === activeStop.id) {
      return;
    }

    lastSpokenStopIdRef.current = activeStop.id;
    speakStop(activeStop);
  }, [activeStop, autoNarrationStopId, narrationEnabled]);

  useEffect(() => {
    return () => {
      if (watchIdRef.current !== null && typeof navigator !== 'undefined' && navigator.geolocation) {
        navigator.geolocation.clearWatch(watchIdRef.current);
      }

      if (typeof window !== 'undefined' && window.speechSynthesis) {
        window.speechSynthesis.cancel();
      }
    };
  }, []);

  return {
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
  };
}
