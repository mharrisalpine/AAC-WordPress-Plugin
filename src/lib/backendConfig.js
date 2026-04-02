const getRuntimeConfig = () => {
  if (typeof window === 'undefined') {
    return {};
  }

  return window.AAC_MEMBER_PORTAL_CONFIG || {};
};

const trimTrailingSlash = (value) => String(value || '').replace(/\/$/, '');

export const getAppRuntimeConfig = () => getRuntimeConfig();

export const setRuntimeRestNonce = (restNonce) => {
  if (typeof window === 'undefined') {
    return;
  }

  const nextConfig = {
    ...getRuntimeConfig(),
  };

  if (restNonce) {
    nextConfig.restNonce = restNonce;
  } else {
    delete nextConfig.restNonce;
  }

  window.AAC_MEMBER_PORTAL_CONFIG = nextConfig;
};

export const getMemberApiBase = () => {
  const runtimeBase = getRuntimeConfig().apiBase;
  if (runtimeBase) {
    return trimTrailingSlash(runtimeBase);
  }

  const configuredBase =
    import.meta.env.VITE_MEMBER_API_BASE ||
    import.meta.env.VITE_WORDPRESS_API_BASE;

  if (configuredBase) {
    return trimTrailingSlash(configuredBase);
  }

  return '/wp-json/aac/v1';
};

export const getPortalPageUrl = () => {
  const runtimePortalUrl = getRuntimeConfig().portalPageUrl;
  if (runtimePortalUrl) {
    return trimTrailingSlash(runtimePortalUrl);
  }

  if (typeof window === 'undefined') {
    return '';
  }

  return trimTrailingSlash(`${window.location.origin}${window.location.pathname}`);
};

export const getCommerceProvider = () =>
  getRuntimeConfig().commerceProvider ||
  import.meta.env.VITE_COMMERCE_PROVIDER ||
  'embedded';

export const isStandaloneBackend = () =>
  getRuntimeConfig().backendMode === 'standalone' ||
  import.meta.env.VITE_BACKEND_MODE === 'standalone';
