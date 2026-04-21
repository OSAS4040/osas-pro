# Database Schema

## Table List (by Migration Order)

### 1. companies
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| name | varchar | NOT NULL |
| name_ar | varchar | nullable |
| tax_number | varchar | nullable |
| cr_number | varchar | nullable |
| email | varchar | nullable |
| phone | varchar | nullable |
| address | text | nullable |
| city | varchar | nullable |
| country | varchar | default SAU |
| currency | varchar | default SAR |
| timezone | varchar | default Asia/Riyadh |
| logo_url | varchar | nullable |
| is_active | boolean | default true |
| settings | json | nullable |
| created_at, updated_at | timestamp | UTC |
| deleted_at | timestamp | nullable (soft delete) |

---

### 2. branches
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| company_id | FK → companies | NOT NULL |
| name | varchar | NOT NULL |
| name_ar | varchar | nullable |
| phone | varchar | nullable |
| address | text | nullable |
| city | varchar | nullable |
| is_main | boolean | default false |
| is_active | boolean | default true |
| created_at, updated_at | timestamp | UTC |
| deleted_at | timestamp | nullable |
| **Indexes** | company_id | — |

---

### 3. users
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| company_id | FK → companies | NOT NULL |
| branch_id | FK → branches | nullable |
| name | varchar | NOT NULL |
| email | varchar | UNIQUE |
| password | varchar | hashed |
| phone | varchar | nullable |
| role | varchar | owner\|manager\|cashier\|technician\|viewer |
| is_active | boolean | default true |
| email_verified_at | timestamp | nullable |
| remember_token | varchar | nullable |
| created_at, updated_at | timestamp | UTC |
| deleted_at | timestamp | nullable |
| **Indexes** | (company_id, email), (company_id, role) | — |

---

### 4. subscriptions
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| company_id | FK → companies | NOT NULL |
| plan | varchar | NOT NULL |
| status | enum | active\|grace_period\|suspended |
| starts_at | timestamp | NOT NULL |
| ends_at | timestamp | NOT NULL |
| grace_ends_at | timestamp | nullable |
| amount | decimal(10,2) | nullable |
| currency | varchar | default SAR |
| features | json | nullable |
| max_branches | int | nullable |
| max_users | int | nullable |
| created_at, updated_at | timestamp | UTC |
| **Indexes** | (company_id, status) | — |

---

### 5. idempotency_keys
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| company_id | FK → companies | NOT NULL |
| key | varchar | NOT NULL |
| endpoint | varchar | NOT NULL |
| trace_id | varchar | nullable |
| request_hash | varchar | NOT NULL |
| response_snapshot | text | nullable |
| expires_at | timestamp | NOT NULL |
| created_at | timestamp | UTC |
| **Constraints** | UNIQUE(company_id, key) | — |

---

### 7. customers
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| company_id | FK → companies | NOT NULL |
| branch_id | FK → branches | nullable |
| name | varchar | NOT NULL |
| name_ar | varchar | nullable |
| email | varchar | nullable |
| phone | varchar | nullable |
| type | enum | b2c\|b2b |
| company_name | varchar | nullable (B2B) |
| tax_number | varchar | nullable (B2B) |
| credit_limit | decimal(14,4) | default 0 |
| is_active | boolean | default true |
| created_at, updated_at | timestamp | UTC |
| deleted_at | timestamp | nullable |
| **Indexes** | (company_id, type), (company_id, phone) | — |

---

### 8. wallets
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| company_id | FK → companies | NOT NULL |
| customer_id | FK → customers | NOT NULL |
| balance | decimal(14,4) | default 0 |
| currency | varchar | default SAR |
| version | int | default 0 (optimistic lock) |
| created_at, updated_at | timestamp | UTC |
| **Constraints** | UNIQUE(company_id, customer_id) | — |

---

### 9. wallet_transactions (APPEND-ONLY)
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| uuid | uuid | UNIQUE |
| company_id | FK → companies | NOT NULL |
| wallet_id | FK → wallets | NOT NULL |
| created_by_user_id | FK → users | NOT NULL |
| type | enum | TOP_UP\|INVOICE_DEBIT\|REFUND\|ADJUSTMENT_ADD\|ADJUSTMENT_SUB\|REVERSAL |
| amount | decimal(14,4) | NOT NULL |
| balance_before | decimal(14,4) | NOT NULL |
| balance_after | decimal(14,4) | NOT NULL |
| reference_type | varchar | nullable |
| reference_id | bigint | nullable |
| original_transaction_id | bigint | nullable (FK self) |
| reversal_transaction_id | bigint | nullable (FK self) |
| trace_id | varchar | nullable |
| note | text | nullable |
| created_at | timestamp | UTC (no updated_at) |

**Rules**: NO UPDATE, NO DELETE. Corrections via REVERSAL type only.

---

### 10. products + product_categories
**product_categories**: id, company_id, name, name_ar, parent_id (self-ref), is_active

**products**:
| Column | Type | Notes |
|--------|------|-------|
| uuid | uuid | UNIQUE |
| company_id | FK | tenant |
| category_id | FK → product_categories | nullable |
| name, name_ar | varchar | — |
| barcode | varchar | UNIQUE(company_id, barcode) |
| sku | varchar | UNIQUE(company_id, sku) |
| unit | varchar | pcs\|kg\|ltr\|m |
| price | decimal(14,4) | selling price |
| cost | decimal(14,4) | purchase cost |
| tax_rate | decimal(5,2) | default 15 |
| is_taxable | boolean | — |
| track_inventory | boolean | — |
| version | int | optimistic lock |

---

### 11. inventory + inventory_reservations + stock_movements

**inventory**: company_id, branch_id, product_id, quantity, reserved_quantity, reorder_point, version
- UNIQUE(company_id, branch_id, product_id)
- `available_quantity` = quantity - reserved_quantity

**inventory_reservations**: uuid, company_id, branch_id, product_id, inventory_id, reference_type, reference_id, quantity, status (pending\|consumed\|released\|canceled\|expired), expires_at

**stock_movements (APPEND-ONLY)**: uuid, company_id, branch_id, product_id, created_by_user_id, type, quantity, quantity_before, quantity_after, reference_type, reference_id, original_movement_id, reversal_movement_id, trace_id, note

---

### 12. invoices + invoice_items + payments

**invoices**:
- UNIQUE(company_id, invoice_number)
- UNIQUE(company_id, idempotency_key)
- Cryptographic hash chain: invoice_hash, previous_invoice_hash, invoice_counter
- ZATCA fields: zatca_status

**invoice_items**: invoice_id, company_id, product_id, name, sku, quantity, unit_price, discount_amount, tax_rate, tax_amount, subtotal, total

**payments (APPEND-ONLY)**: uuid, company_id, branch_id, invoice_id, created_by_user_id, method, amount, currency, reference, status, original_payment_id, reversal_payment_id, trace_id

---

### 14. vehicles
| Column | Type |
|--------|------|
| uuid | uuid UNIQUE |
| company_id | FK |
| customer_id | FK → customers |
| make, model, year | varchar |
| color | varchar nullable |
| vin | UNIQUE(company_id, vin) |
| plate_number | UNIQUE(company_id, plate_number) |
| mileage | int nullable |
| fuel_type | varchar nullable |
| transmission | varchar nullable |
| notes | text nullable |

---

### 15. work_orders + work_order_items + work_order_technicians

**work_orders**:
- UNIQUE(company_id, order_number)
- status state machine: draft→pending→in_progress→on_hold→completed→delivered / cancelled
- version column (optimistic lock)
- ZATCA: source_type, source_id

**work_order_items**: item_type (part\|labor\|service\|other), product_id nullable, quantity, unit_price, tax fields

**work_order_technicians**: work_order_id, user_id, role (lead\|assistant), started_at, ended_at, notes

---

### 16. suppliers + purchase_orders + purchase_order_items

**suppliers**: company_id, name, contact_name, email, phone, tax_number, credit_terms

**purchase_orders**: UNIQUE(company_id, po_number), supplier_id, status (draft\|sent\|partial\|received\|cancelled), expected_at, received_at, totals

**purchase_order_items**: po_id, product_id, ordered_qty, received_qty, unit_cost

---

### 17. activity_logs + api_usage_logs + zatca_logs

**activity_logs**: company_id, branch_id, user_id, action, model_type, model_id, old_values, new_values, trace_id

**api_usage_logs**: api_key_id, company_id, endpoint, method, status_code, duration_ms, trace_id

**zatca_logs**: company_id, invoice_id, attempt, request_payload, response_payload, status, zatca_uuid

---

## Index Strategy

All indexes start with `company_id` per architecture rules:
- `(company_id, branch_id, status)` — filtered list views
- `(company_id, customer_id)` — customer-scoped lookups
- `(company_id, issued_at)` — date-range reports
- `(company_id, wallet_id, type)` — wallet transaction history
- `(company_id, branch_id, product_id)` — inventory lookups
