export const AAC_CUTTING_EDGE_PAGE_URL = 'https://americanalpineclub.org/cutting-edge-podcast/';

const createSpotifyEpisode = ({ title, description, episodeId, publishedAt = '' }) => ({
  title,
  description,
  published_at: publishedAt,
  source_url: `https://open.spotify.com/episode/${episodeId}`,
  embed_url: `https://open.spotify.com/embed/episode/${episodeId}?utm_source=generator&theme=0`,
  source_page_url: AAC_CUTTING_EDGE_PAGE_URL,
});

export const AAC_CUTTING_EDGE_PODCASTS = [
  createSpotifyEpisode({
    title: 'All-Women Team Finds Six Long New Routes in Greenland',
    description: 'An AAC conversation about expedition tactics, partnership, and opening multiple new lines in East Greenland.',
    episodeId: '4ForPe7Ad0vlZBbW2H9HLE',
  }),
  createSpotifyEpisode({
    title: 'Sasha DiGiulian on El Cap’s "Direct Line"',
    description: 'Sasha DiGiulian reflects on Yosemite free climbing, risk, preparation, and the process behind Direct Line.',
    episodeId: '3hdrtuOQHJMfpl7XJEVPjb',
  }),
  createSpotifyEpisode({
    title: 'The Coveted Southeast Pillar of Ultar Sar Is Finally Climbed',
    description: 'A long-awaited Karakoram success story covering commitment, weather windows, and a much-sought alpine objective.',
    episodeId: '6q0qj9L5o2Xt7ISW1CYnwC',
  }),
  createSpotifyEpisode({
    title: 'Finding Providence in the Alaska Range',
    description: 'Anna Pfaff, Andres Marin, and Tad McCrea discuss their direct new route on Mt. Providence.',
    episodeId: '7MDmLdRoAdCSImpcheAPce',
  }),
  createSpotifyEpisode({
    title: 'Big Walls on Baffin Island',
    description: 'A wilderness big-wall conversation on planning, logistics, and climbing in one of the world’s most remote arenas.',
    episodeId: '01jTfbDZSQRczDfBghCVwd',
  }),
  createSpotifyEpisode({
    title: 'First Ascent of Yashkuk Sar, Pakistan',
    description: 'The team breaks down the route, the range, and the alpine problem-solving behind a first ascent in Pakistan.',
    episodeId: '5lIH9Vyl21ZFmkSx323QVM',
  }),
  createSpotifyEpisode({
    title: 'Babsi Zangerl and Jacopo Larcher: Free Ascent of Eternal Flame on Nameless Tower',
    description: 'A deep dive into free climbing on one of the world’s most iconic big-wall testpieces.',
    episodeId: '6umXJNDJbOp7X8ocUp6uet',
  }),
  createSpotifyEpisode({
    title: 'Mt. Dickey: A New Route and a Brilliant History',
    description: 'A look at new-route ambition on Mt. Dickey alongside the mountain’s broader climbing legacy.',
    episodeId: '1LgnDL4maXjQYQke1C22qQ',
  }),
  createSpotifyEpisode({
    title: 'Christian Black, Vitaliy Musiyenko and Hayden Wyatt: A First Ascent in India',
    description: 'An AAC episode on first-ascensionist decision making, partnership, and exploration in the Indian Himalaya.',
    episodeId: '41l2FZsr6P5TJMw7TJtEG1',
  }),
  createSpotifyEpisode({
    title: 'Matt Cornell, Jackson Marvell and Alan Rousseau: Jannu North Face',
    description: 'The team reflects on the Jannu North Face, a modern benchmark for Himalayan alpine climbing.',
    episodeId: '1oumPXgOMyqqCLmZLlY7hF',
  }),
];

const extractSpotifyEpisodeId = (value = '') => {
  const input = String(value || '');
  const match = input.match(/spotify\.com\/(?:embed\/)?episode\/([A-Za-z0-9]+)/i);
  return match?.[1] || '';
};

export const toSpotifyEmbedUrl = (value = '') => {
  const episodeId = extractSpotifyEpisodeId(value);
  return episodeId ? `https://open.spotify.com/embed/episode/${episodeId}?utm_source=generator&theme=0` : '';
};

export const toSpotifySourceUrl = (value = '') => {
  const episodeId = extractSpotifyEpisodeId(value);
  return episodeId ? `https://open.spotify.com/episode/${episodeId}` : '';
};

export const normalizePodcastEpisode = (episode = {}, index = 0) => {
  const embedUrl = String(episode.embed_url || '').trim();
  const sourceUrl = String(episode.source_url || '').trim();
  const normalizedEmbedUrl = embedUrl || toSpotifyEmbedUrl(sourceUrl);
  const normalizedSourceUrl = sourceUrl || toSpotifySourceUrl(embedUrl);

  return {
    title: String(episode.title || '').trim() || `Cutting Edge Episode ${index + 1}`,
    description: String(episode.description || '').trim(),
    published_at: String(episode.published_at || '').trim(),
    embed_url: normalizedEmbedUrl,
    source_url: normalizedSourceUrl,
    source_page_url: String(episode.source_page_url || AAC_CUTTING_EDGE_PAGE_URL).trim(),
  };
};

export const normalizePodcastList = (episodes = []) =>
  Array.isArray(episodes)
    ? episodes
        .map((episode, index) => normalizePodcastEpisode(episode, index))
        .filter((episode) => Boolean(episode.embed_url))
    : [];
