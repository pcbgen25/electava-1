const baseOrigin = (process.env.NEXT_PUBLIC_API_BASE_URL || process.env.API_BASE_URL || 'http://127.0.0.1:5000').replace(/\/+$/, '');

export function getApiBaseUrl() {
  return baseOrigin.endsWith('/api') ? baseOrigin : `${baseOrigin}/api`;
}

export function getApiUrl(path = '') {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${getApiBaseUrl()}${normalizedPath}`;
}
