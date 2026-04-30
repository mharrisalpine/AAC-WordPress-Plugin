import React, { useEffect, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { ExternalLink, Headphones, Mic2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/use-toast';
import {
  AAC_CUTTING_EDGE_PAGE_URL,
  AAC_CUTTING_EDGE_PODCASTS,
  extractSpotifyEpisodeId,
  normalizePodcastList,
  toSpotifyEpisodeUri,
} from '@/lib/aacPodcasts';
import { getLatestPodcasts, recordPodcastListen } from '@/lib/memberApi';

const SPOTIFY_COMPLETION_THRESHOLD = 0.9;

const loadSpotifyIFrameApi = (() => {
  let promise = null;

  return () => {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
      return Promise.reject(new Error('Spotify playback tracking is only available in the browser.'));
    }

    if (window.SpotifyIframeApi) {
      return Promise.resolve(window.SpotifyIframeApi);
    }

    if (promise) {
      return promise;
    }

    promise = new Promise((resolve, reject) => {
      const settleWithExistingApi = () => {
        if (window.SpotifyIframeApi) {
          resolve(window.SpotifyIframeApi);
          return true;
        }

        return false;
      };

      if (settleWithExistingApi()) {
        return;
      }

      const previousReady = window.onSpotifyIframeApiReady;
      window.onSpotifyIframeApiReady = (api) => {
        window.SpotifyIframeApi = api;
        if (typeof previousReady === 'function') {
          previousReady(api);
        }
        resolve(api);
      };

      const existingScript = document.querySelector('script[data-aac-spotify-iframe-api="true"]');
      if (existingScript) {
        const startedAt = Date.now();
        const waitForApi = () => {
          if (settleWithExistingApi()) {
            return;
          }

          if (Date.now() - startedAt > 5000) {
            reject(new Error('Spotify iFrame API did not finish loading in time.'));
            return;
          }

          window.setTimeout(waitForApi, 100);
        };

        waitForApi();
        return;
      }

      const script = document.createElement('script');
      script.async = true;
      script.src = 'https://open.spotify.com/embed/iframe-api/v1';
      script.dataset.aacSpotifyIframeApi = 'true';
      script.onerror = () => reject(new Error('Unable to load the Spotify iFrame API.'));
      document.head.appendChild(script);
    });

    return promise;
  };
})();

const readPlaybackMetrics = (event = {}) => {
  const payload = typeof event?.data === 'object' && event.data ? event.data : event;
  const durationMs = Math.max(0, Number(
    payload?.duration
    ?? payload?.durationMs
    ?? payload?.track?.duration_ms
    ?? 0
  ));
  const positionMs = Math.max(0, Number(
    payload?.position
    ?? payload?.positionMs
    ?? payload?.position_ms
    ?? 0
  ));

  return {
    durationMs,
    positionMs,
    completionPercent: durationMs > 0 ? Math.min(100, (positionMs / durationMs) * 100) : 0,
  };
};

const TrackedSpotifyEmbed = ({ podcast }) => {
  const containerRef = useRef(null);
  const cleanupRef = useRef(() => {});
  const startedRef = useRef(false);
  const completedRef = useRef(false);
  const lastMetricsRef = useRef({
    durationMs: 0,
    positionMs: 0,
    completionPercent: 0,
  });
  const [fallbackMode, setFallbackMode] = useState(false);
  const [isReady, setIsReady] = useState(false);
  const [manualSavePending, setManualSavePending] = useState(false);
  const [manualSaveDone, setManualSaveDone] = useState(false);

  const episodeId = extractSpotifyEpisodeId(podcast.source_url || podcast.embed_url);
  const spotifyUri = toSpotifyEpisodeUri(podcast.source_url || podcast.embed_url);

  useEffect(() => {
    if (!spotifyUri || !containerRef.current) {
      setFallbackMode(true);
      return undefined;
    }

    let cancelled = false;
    setFallbackMode(false);
    setIsReady(false);
    startedRef.current = false;
    completedRef.current = false;

    const sendListenEvent = async (status, metrics) => {
      if (!episodeId) {
        return;
      }

      try {
        await recordPodcastListen({
          episode_id: episodeId,
          episode_title: podcast.title,
          source_url: podcast.source_url,
          source_page_url: podcast.source_page_url || AAC_CUTTING_EDGE_PAGE_URL,
          embed_url: podcast.embed_url,
          status,
          completion_percent: metrics.completionPercent,
          duration_ms: metrics.durationMs,
          last_position_ms: metrics.positionMs,
        });
        if (status === 'completed') {
          setManualSaveDone(true);
        }
      } catch (error) {
        console.warn('Unable to record podcast listen event:', error);
      }
    };

    loadSpotifyIFrameApi()
      .then((IFrameAPI) => {
        if (cancelled || !containerRef.current) {
          return;
        }

        IFrameAPI.createController(containerRef.current, {
          uri: spotifyUri,
          width: '100%',
          height: '152',
          theme: 'black',
        }, (controller) => {
          if (cancelled) {
            if (controller?.destroy) {
              controller.destroy();
            }
            return;
          }

          setIsReady(true);

          const playbackListener = (event) => {
            const metrics = readPlaybackMetrics(event);
            lastMetricsRef.current = metrics;

            if (!startedRef.current && metrics.positionMs > 0) {
              startedRef.current = true;
              void sendListenEvent('started', metrics);
            }

            if (
              !completedRef.current
              && metrics.durationMs > 0
              && (metrics.positionMs / metrics.durationMs) >= SPOTIFY_COMPLETION_THRESHOLD
            ) {
              completedRef.current = true;
              startedRef.current = true;
              void sendListenEvent('completed', metrics);
            }
          };

          if (controller?.addListener) {
            controller.addListener('playback_started', () => {
              if (startedRef.current) {
                return;
              }

              startedRef.current = true;
              void sendListenEvent('started', lastMetricsRef.current);
            });
            controller.addListener('playback_update', playbackListener);
          }

          cleanupRef.current = () => {
            if (controller?.removeListener) {
              controller.removeListener('playback_started');
              controller.removeListener('playback_update', playbackListener);
            }
            if (controller?.destroy) {
              controller.destroy();
            }
          };
        });
      })
      .catch((error) => {
        console.warn('Falling back to the plain Spotify iframe player:', error);
        if (!cancelled) {
          setFallbackMode(true);
        }
      });

    return () => {
      cancelled = true;
      cleanupRef.current();
      cleanupRef.current = () => {};
    };
  }, [episodeId, podcast.embed_url, podcast.source_page_url, podcast.source_url, podcast.title, spotifyUri]);

  if (fallbackMode) {
    return (
      <div className="space-y-3">
        <iframe
          title={podcast.title}
          src={podcast.embed_url}
          width="100%"
          height="152"
          allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
          loading="lazy"
          className="w-full overflow-hidden rounded-[18px] border-0"
        />
        <p className="text-xs leading-5 text-stone-600">
          This browser is using Spotify&apos;s standard embed fallback, so listen tracking may need a manual nudge.
        </p>
      </div>
    );
  }

  const handleManualCompletionSave = async () => {
    if (!episodeId || manualSavePending) {
      return;
    }

    setManualSavePending(true);

    try {
      const metrics = lastMetricsRef.current;
      await recordPodcastListen({
        episode_id: episodeId,
        episode_title: podcast.title,
        source_url: podcast.source_url,
        source_page_url: podcast.source_page_url || AAC_CUTTING_EDGE_PAGE_URL,
        embed_url: podcast.embed_url,
        status: 'completed',
        completion_percent: Math.max(metrics.completionPercent || 0, 100),
        duration_ms: metrics.durationMs,
        last_position_ms: Math.max(metrics.positionMs || 0, metrics.durationMs || 0),
      });
      completedRef.current = true;
      startedRef.current = true;
      setManualSaveDone(true);
      toast({
        title: 'Podcast listen saved',
        description: 'This completed listen is now recorded in the AAC member database.',
      });
    } catch (error) {
      toast({
        title: 'Unable to save listen',
        description: error?.message || 'The completion could not be recorded just yet.',
        variant: 'destructive',
      });
    } finally {
      setManualSavePending(false);
    }
  };

  return (
    <div className="space-y-3">
      <div className="relative min-h-[152px] w-full overflow-hidden rounded-[18px] bg-black">
        {!isReady ? (
          <div className="absolute inset-0 flex items-center justify-center text-xs font-semibold uppercase tracking-[0.18em] text-white/60">
            Loading Spotify player
          </div>
        ) : null}
        <div ref={containerRef} className="min-h-[152px] w-full" />
      </div>
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-xs leading-5 text-stone-600">
          Playback should save automatically when Spotify reports progress. If it doesn&apos;t, you can save the completion here.
        </p>
        <Button
          type="button"
          variant="outline"
          disabled={manualSavePending || manualSaveDone}
          className="rounded-none border-stone-300 text-black hover:bg-stone-100"
          onClick={handleManualCompletionSave}
        >
          {manualSaveDone ? 'Listen Saved' : manualSavePending ? 'Saving…' : 'Mark Completed'}
        </Button>
      </div>
    </div>
  );
};

const PodcastsTab = () => {
  const [podcasts, setPodcasts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPodcasts = async () => {
      setLoading(true);
      try {
        const data = await getLatestPodcasts();
        const normalizedPodcasts = normalizePodcastList(data?.podcasts);
        setPodcasts(normalizedPodcasts.length ? normalizedPodcasts : AAC_CUTTING_EDGE_PODCASTS);
      } catch (error) {
        console.error('Error fetching podcasts:', error);
        setPodcasts(AAC_CUTTING_EDGE_PODCASTS);
        toast({
          title: 'Error fetching podcasts',
          description: 'Showing the AAC podcast archive while the live feed is unavailable.',
        });
      } finally {
        setLoading(false);
      }
    };

    fetchPodcasts();
  }, []);

  return (
    <div className="py-6">
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="space-y-6"
      >
        <div className="rounded-[30px] border border-black/8 bg-[#030000] px-6 py-7 text-white shadow-[0_24px_70px_rgba(3,0,0,0.18)]">
          <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div className="max-w-3xl">
              <div className="inline-flex items-center gap-2 rounded-full border border-[#f8c235]/35 bg-[#f8c235]/10 px-4 py-2 text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-[#f8c235]">
                <Mic2 className="h-4 w-4" />
                Cutting Edge Podcast
              </div>
              <h2 className="mt-4 text-3xl font-bold text-white md:text-4xl">Spotify episodes from AAC&apos;s official podcast feed</h2>
              <p className="mt-3 text-base leading-7 text-white/75">
                Listen directly in the member app with embedded Spotify players sourced from the American Alpine Club&apos;s{' '}
                Cutting Edge Podcast page. Completed listens are now saved back to the AAC member database for logged-in members.
              </p>
            </div>

            <Button
              asChild
              className="bg-[#f8c235] text-black hover:bg-[#ddb01d]"
            >
              <a href={AAC_CUTTING_EDGE_PAGE_URL} target="_blank" rel="noreferrer">
                View Full Podcast Page
                <ExternalLink className="ml-2 h-4 w-4" />
              </a>
            </Button>
          </div>
        </div>

        {loading ? (
          <div className="text-center text-black">Loading AAC podcast episodes...</div>
        ) : podcasts.length === 0 ? (
          <div className="card-gradient rounded-[28px] border border-stone-200 p-8 text-center text-black/75">
            No podcast episodes are available right now.
          </div>
        ) : (
          <div className="grid gap-5">
            {podcasts.map((podcast, index) => (
              <motion.article
                key={podcast.source_url || podcast.embed_url || `podcast-${index}`}
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.45, delay: index * 0.05 }}
                className="card-gradient rounded-[28px] border border-stone-200 p-5 md:p-6"
              >
                <div className="grid gap-5 lg:grid-cols-[1.05fr,0.95fr] lg:items-start">
                  <div className="space-y-4">
                    <div className="flex items-center gap-3">
                      <div className="rounded-2xl bg-[#c8a43a]/18 p-3 text-[#6b5310]">
                        <Headphones className="h-5 w-5" />
                      </div>
                      <div>
                        <p className="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-[#8a6a19]">
                          Episode {index + 1}
                        </p>
                        <h3 className="mt-1 text-2xl font-bold text-stone-900">{podcast.title}</h3>
                      </div>
                    </div>

                    <p className="text-sm leading-7 text-stone-700">
                      {podcast.description || 'Listen to this Cutting Edge Podcast episode from the AAC archive.'}
                    </p>

                    <div className="flex flex-wrap gap-3">
                      {podcast.source_url ? (
                        <Button
                          asChild
                          variant="outline"
                          className="border-stone-300 text-black hover:bg-stone-100"
                        >
                          <a href={podcast.source_url} target="_blank" rel="noreferrer">
                            Open in Spotify
                            <ExternalLink className="ml-2 h-4 w-4" />
                          </a>
                        </Button>
                      ) : null}

                      <Button
                        asChild
                        variant="outline"
                        className="border-stone-300 text-black hover:bg-stone-100"
                      >
                        <a href={podcast.source_page_url || AAC_CUTTING_EDGE_PAGE_URL} target="_blank" rel="noreferrer">
                          AAC Podcast Page
                          <ExternalLink className="ml-2 h-4 w-4" />
                        </a>
                      </Button>
                    </div>
                  </div>

                  <div className="rounded-[24px] border border-stone-200 bg-white/85 p-3 shadow-[0_18px_40px_rgba(15,23,42,0.06)]">
                    <TrackedSpotifyEmbed podcast={podcast} />
                  </div>
                </div>
              </motion.article>
            ))}
          </div>
        )}
      </motion.div>
    </div>
  );
};

export default PodcastsTab;
