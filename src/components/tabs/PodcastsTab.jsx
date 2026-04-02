import React, { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { ExternalLink, Headphones, Mic2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { toast } from '@/components/ui/use-toast';
import { AAC_CUTTING_EDGE_PAGE_URL, AAC_CUTTING_EDGE_PODCASTS, normalizePodcastList } from '@/lib/aacPodcasts';
import { getLatestPodcasts } from '@/lib/memberApi';

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
                Listen directly in the member app with embedded Spotify players sourced from the American Alpine Club&apos;s
                {' '}
                Cutting Edge Podcast page.
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
                    <iframe
                      title={podcast.title}
                      src={podcast.embed_url}
                      width="100%"
                      height="152"
                      allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                      loading="lazy"
                      className="w-full overflow-hidden rounded-[18px] border-0"
                    />
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
