import { getAppRuntimeConfig, getMemberApiBase, getPortalPageUrl, setRuntimeRestNonce } from '@/lib/backendConfig';

const AUTH_TOKEN_STORAGE_KEY = 'aac_wp_auth_token';

const buildUrl = (path) => {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${getMemberApiBase()}${normalizedPath}`;
};

export const getAuthToken = () => localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);

export const setAuthToken = (token) => {
  if (token) {
    localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, token);
  } else {
    localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
  }
};

export const setRestNonce = (restNonce) => {
  setRuntimeRestNonce(restNonce);
};

let nonceRefreshPromise = null;

const extractRestNonceFromHtml = (html) => {
  const match = String(html || '').match(/"restNonce":"([^"]+)"/);
  return match?.[1] || '';
};

const refreshRestNonce = async () => {
  if (nonceRefreshPromise) {
    return nonceRefreshPromise;
  }

  nonceRefreshPromise = (async () => {
    const portalPageUrl = getPortalPageUrl();
    if (!portalPageUrl) {
      throw new Error('Unable to refresh authentication. Portal URL is not configured.');
    }

    const refreshUrl = new URL(portalPageUrl, window.location.origin);
    refreshUrl.searchParams.set('aac_nonce_refresh', Date.now().toString());

    const response = await fetch(refreshUrl.toString(), {
      credentials: 'include',
      cache: 'no-store',
    });

    const html = await response.text();
    const restNonce = extractRestNonceFromHtml(html);
    if (!restNonce) {
      throw new Error('Unable to refresh authentication. Please reload the page.');
    }

    setRestNonce(restNonce);
    return restNonce;
  })();

  try {
    return await nonceRefreshPromise;
  } finally {
    nonceRefreshPromise = null;
  }
};

export async function apiRequest(path, options = {}) {
  const { retryOnNonceFailure = true, ...fetchOptions } = options;
  const token = getAuthToken();
  const runtimeConfig = getAppRuntimeConfig();
  const headers = new Headers(fetchOptions.headers || {});
  const hasBody = fetchOptions.body !== undefined;
  const isFormData = typeof FormData !== 'undefined' && fetchOptions.body instanceof FormData;

  if (token && !headers.has('Authorization')) {
    headers.set('Authorization', `Bearer ${token}`);
  }

  if (runtimeConfig.restNonce && !headers.has('X-WP-Nonce')) {
    headers.set('X-WP-Nonce', runtimeConfig.restNonce);
  }

  if (hasBody && !isFormData && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(buildUrl(path), {
    credentials: 'include',
    ...fetchOptions,
    headers,
  });

  const contentType = response.headers.get('content-type') || '';
  const isJson = contentType.includes('application/json');
  const payload = isJson ? await response.json() : await response.text();

  if (!response.ok) {
    if (
      retryOnNonceFailure &&
      response.status === 403 &&
      isJson &&
      payload?.code === 'rest_cookie_invalid_nonce'
    ) {
      await refreshRestNonce();
      return apiRequest(path, {
        ...fetchOptions,
        retryOnNonceFailure: false,
      });
    }

    const message =
      (isJson && (payload.message || payload.error)) ||
      response.statusText ||
      'Request failed';
    const error = new Error(message);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}
