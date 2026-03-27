const GITHUB_TOKEN = process.env.GITHUB_TOKEN;
const GITHUB_USERNAME = process.env.GITHUB_USERNAME || 'KyleKrukar';
const ALLOWED_ORIGIN = process.env.ALLOWED_ORIGIN || 'https://kylekrukar.com';

const CORS_HEADERS = {
  'Access-Control-Allow-Origin': ALLOWED_ORIGIN,
  'Access-Control-Allow-Methods': 'GET, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
  'Cache-Control': 'public, max-age=3600',
};

function jsonResponse(body, status = 200) {
  return {
    statusCode: status,
    headers: { 'Content-Type': 'application/json', ...CORS_HEADERS },
    body: JSON.stringify(body),
  };
}

export async function handler(event) {
  if (event.requestContext?.http?.method === 'OPTIONS') {
    return { statusCode: 204, headers: CORS_HEADERS };
  }

  try {
    const res = await fetch('https://api.github.com/graphql', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${GITHUB_TOKEN}`,
        'Content-Type': 'application/json',
        'User-Agent': 'kylekrukar-site',
      },
      body: JSON.stringify({
        query: `query {
          user(login: "${GITHUB_USERNAME}") {
            contributionsCollection {
              contributionCalendar {
                weeks {
                  contributionDays {
                    contributionCount
                    date
                  }
                }
              }
            }
          }
        }`,
      }),
    });

    const data = await res.json();
    const weeks = data.data?.user?.contributionsCollection?.contributionCalendar?.weeks || [];

    // Flatten to daily counts, take last 30 days
    const allDays = weeks.flatMap(w => w.contributionDays);
    const recent = allDays.slice(-30).map(d => ({
      date: d.date,
      count: d.contributionCount,
    }));

    return jsonResponse({ days: recent });
  } catch (e) {
    return jsonResponse({ days: [], error: e.message }, 500);
  }
}
