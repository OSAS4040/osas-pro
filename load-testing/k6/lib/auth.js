import http from 'k6/http';

/**
 * @param {string} baseUrl e.g. http://localhost/api
 * @param {string} email
 * @param {string} password
 * @returns {{ token: string, companyId: number, userId: number } | null}
 */
export function login(baseUrl, email, password) {
  const res = http.post(
    `${baseUrl}/v1/auth/login`,
    JSON.stringify({ email, password }),
    {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      tags: { name: 'AuthLogin' },
    },
  );
  if (res.status !== 200) {
    return null;
  }
  const body = res.json();
  if (!body.token || !body.user) {
    return null;
  }
  return {
    token: body.token,
    companyId: body.user.company_id,
    userId: body.user.id,
  };
}

export function bearerHeaders(token) {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
}
