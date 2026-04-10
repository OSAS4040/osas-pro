/**
 * Shared helpers for k6 load scripts (local/staging only).
 * Auth: POST /api/v1/auth/login → root-level `token` (not data.token).
 */
import http from 'k6/http';

export function login(baseUrl, email, password) {
  const res = http.post(
    `${baseUrl}/api/v1/auth/login`,
    JSON.stringify({ email, password }),
    {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      tags: { name: 'auth_login' },
    },
  );
  if (res.status !== 200) {
    return { ok: false, status: res.status, body: String(res.body).slice(0, 500) };
  }
  let token;
  try {
    token = JSON.parse(res.body).token;
  } catch (e) {
    return { ok: false, status: res.status, body: 'parse error' };
  }
  if (!token) {
    return { ok: false, status: res.status, body: 'no token' };
  }
  return { ok: true, token };
}

export function authHeaders(token) {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
}

/** First customer, then a vehicle that belongs to that customer (avoids mismatched pairs). */
export function fetchFirstCustomerVehicle(baseUrl, headers) {
  const custRes = http.get(`${baseUrl}/api/v1/customers?per_page=1`, {
    headers,
    tags: { name: 'setup_customers' },
  });
  let customerId = null;
  try {
    const cj = JSON.parse(custRes.body);
    const d = cj.data;
    const list = Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [];
    if (list[0]) customerId = list[0].id;
  } catch (e) { /* ignore */ }

  let vehicleId = null;
  let vehiclePlate = null;
  if (customerId != null) {
    const vehRes = http.get(
      `${baseUrl}/api/v1/vehicles?per_page=1&customer_id=${customerId}`,
      { headers, tags: { name: 'setup_vehicles' } },
    );
    try {
      const vj = JSON.parse(vehRes.body);
      const d = vj.data;
      const list = Array.isArray(d?.data) ? d.data : Array.isArray(d) ? d : [];
      if (list[0]) {
        vehicleId = list[0].id;
        vehiclePlate = list[0].plate_number || null;
      }
    } catch (e) { /* ignore */ }
  }

  return { customerId, vehicleId, vehiclePlate, custStatus: custRes.status };
}
