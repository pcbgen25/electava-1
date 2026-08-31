const baseOrigin = (process.env.NEXT_PUBLIC_API_BASE_URL || process.env.API_BASE_URL || 'http://127.0.0.1:5000').replace(/\/+$/, '');

export function getApiBaseUrl() {
  return baseOrigin.endsWith('/api') ? baseOrigin : `${baseOrigin}/api`;
}

export function getApiUrl(path = '') {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${getApiBaseUrl()}${normalizedPath}`;
}

/**
 * Authenticated fetch — automatically attaches JWT Bearer token.
 * Calls onUnauthorized() (or clears storage + redirects) if 401/403 received.
 */
export async function authFetch(url, options = {}, onUnauthorized) {
  const token = typeof window !== 'undefined'
    ? window.localStorage.getItem('electava-marketplace-token')
    : null;

  const headers = {
    'Content-Type': 'application/json',
    ...options.headers,
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };

  const response = await fetch(url, { ...options, headers });

  if (response.status === 401 || response.status === 403) {
    if (typeof onUnauthorized === 'function') {
      onUnauthorized();
    } else if (typeof window !== 'undefined') {
      window.localStorage.removeItem('electava-marketplace-token');
      window.localStorage.removeItem('electava-marketplace-user');
      window.location.href = '/login?expired=1';
    }
  }

  return response;
}
