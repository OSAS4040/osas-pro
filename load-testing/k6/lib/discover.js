import http from 'k6/http';
import { bearerHeaders } from './auth.js';

/**
 * جلب أول عميل وأول منتج لسيناريو POS (واقعي).
 */
export function discoverPosContext(baseUrl, token) {
  const h = { headers: bearerHeaders(token) };
  const custRes = http.get(`${baseUrl}/v1/customers?per_page=5`, h);
  const prodRes = http.get(`${baseUrl}/v1/products?per_page=5`, h);

  let customerId = null;
  let product = null;

  if (custRes.status === 200) {
    const payload = custRes.json();
    const rows = payload.data && payload.data.data ? payload.data.data : [];
    if (rows.length) {
      customerId = rows[0].id;
    }
  }

  if (prodRes.status === 200) {
    const payload = prodRes.json();
    const rows = payload.data && payload.data.data ? payload.data.data : [];
    if (rows.length) {
      const p = rows[0];
      product = {
        id: p.id,
        name: p.name || 'Item',
        unit_price: Number(p.sale_price) || 50,
        cost_price: Number(p.cost_price) || 25,
        tax_rate: Number(p.tax_rate) || 15,
      };
    }
  }

  return { customerId, product };
}
