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

/**
 * أول أمر عمل وأول مركبة لمسارات التفاصيل تحت الضغط (قراءة فقط).
 */
export function discoverWoVehicleContext(baseUrl, token) {
  const h = { headers: bearerHeaders(token) };
  let workOrderId = null;
  let vehicleId = null;

  const woRes = http.get(`${baseUrl}/v1/work-orders?per_page=5`, h);
  if (woRes.status === 200) {
    try {
      const payload = woRes.json();
      const rows = payload.data && payload.data.data ? payload.data.data : [];
      if (rows.length && rows[0].id != null) {
        workOrderId = rows[0].id;
      }
    } catch (_) {
      /* ignore */
    }
  }

  const vRes = http.get(`${baseUrl}/v1/vehicles?per_page=5`, h);
  if (vRes.status === 200) {
    try {
      const payload = vRes.json();
      const rows = payload.data && payload.data.data ? payload.data.data : [];
      if (rows.length && rows[0].id != null) {
        vehicleId = rows[0].id;
      }
    } catch (_) {
      /* ignore */
    }
  }

  return { workOrderId, vehicleId };
}
