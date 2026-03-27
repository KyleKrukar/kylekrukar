const CLIENT_ID = process.env.SPOTIFY_CLIENT_ID;
const CLIENT_SECRET = process.env.SPOTIFY_CLIENT_SECRET;
const REFRESH_TOKEN = process.env.SPOTIFY_REFRESH_TOKEN;
const ALLOWED_ORIGINS = (process.env.ALLOWED_ORIGINS || process.env.ALLOWED_ORIGIN || 'https://kylekrukar.com')
  .split(',').map(s => s.trim());

let cachedToken = null;
let tokenExpiry = 0;

async function getAccessToken() {
  if (cachedToken && Date.now() < tokenExpiry) return cachedToken;

  const creds = btoa(`${CLIENT_ID}:${CLIENT_SECRET}`);
  const res = await fetch('https://accounts.spotify.com/api/token', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Authorization': `Basic ${creds}`,
    },
    body: new URLSearchParams({
      grant_type: 'refresh_token',
      refresh_token: REFRESH_TOKEN,
    }),
  });

  const data = await res.json();
  if (!data.access_token) throw new Error('Token refresh failed');

  cachedToken = data.access_token;
  tokenExpiry = Date.now() + (data.expires_in - 60) * 1000;
  return cachedToken;
}

function getCorsHeaders(event) {
  const origin = event.headers?.origin || '';
  return {
    'Access-Control-Allow-Origin': ALLOWED_ORIGINS.includes(origin) ? origin : ALLOWED_ORIGINS[0],
    'Access-Control-Allow-Methods': 'GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Cache-Control': 'no-cache, no-store',
  };
}

function jsonResponse(body, event, status = 200) {
  return {
    statusCode: status,
    headers: { 'Content-Type': 'application/json', ...getCorsHeaders(event) },
    body: JSON.stringify(body),
  };
}

export async function handler(event) {
  if (event.requestContext?.http?.method === 'OPTIONS') {
    return { statusCode: 204, headers: getCorsHeaders(event) };
  }

  try {
    const token = await getAccessToken();
    const res = await fetch('https://api.spotify.com/v1/me/player/currently-playing', {
      headers: { 'Authorization': `Bearer ${token}` },
    });

    if (res.status === 204 || res.status === 202) {
      return jsonResponse({ is_playing: false }, event);
    }

    const data = await res.json();
    const item = data.item || {};
    const album = item.album || {};
    const images = album.images || [];

    return jsonResponse({
      is_playing: data.is_playing || false,
      track: item.name || '',
      artist: (item.artists || []).map(a => a.name).join(', '),
      album: album.name || '',
      album_art: images[0]?.url || '',
      album_art_small: images[images.length - 1]?.url || '',
      progress_ms: data.progress_ms || 0,
      duration_ms: item.duration_ms || 0,
    }, event);
  } catch (e) {
    return jsonResponse({ is_playing: false, error: e.message }, event, 500);
  }
}
