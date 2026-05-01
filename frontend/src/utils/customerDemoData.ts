export const demoCustomerInvoices = [
  {
    id: 99001,
    invoice_number: 'INV-DEMO-001',
    issue_date: '2026-04-20',
    total: 1280,
    status: 'paid',
    customer_name: 'العميل التجريبي',
  },
  {
    id: 99002,
    invoice_number: 'INV-DEMO-002',
    issue_date: '2026-04-24',
    total: 745,
    status: 'unpaid',
    customer_name: 'العميل التجريبي',
  },
  {
    id: 99003,
    invoice_number: 'INV-DEMO-003',
    issue_date: '2026-04-27',
    total: 2190,
    status: 'overdue',
    customer_name: 'العميل التجريبي',
  },
]

export const demoCustomerVehicles = [
  {
    id: 88001,
    plate_number: 'ABC 1234',
    make: 'Toyota',
    model: 'Camry',
    year: 2023,
    color: 'أبيض',
    fuel_type: 'gasoline',
    vin: 'DEMO-VEHICLE-0001',
    is_active: true,
  },
  {
    id: 88002,
    plate_number: 'KSA 4521',
    make: 'Hyundai',
    model: 'Elantra',
    year: 2022,
    color: 'فضي',
    fuel_type: 'hybrid',
    vin: 'DEMO-VEHICLE-0002',
    is_active: true,
  },
]

export const demoCustomerWorkOrders = [
  {
    id: 77001,
    order_number: 'WO-DEMO-001',
    status: 'pending_manager_approval',
    priority: 'high',
    created_at: '2026-04-26T09:30:00Z',
    description: 'تغيير زيت + فلتر',
    notes: 'تنفيذ صباحًا',
    vehicle: { id: 88001, plate_number: 'ABC 1234' },
  },
  {
    id: 77002,
    order_number: 'WO-DEMO-002',
    status: 'in_progress',
    priority: 'medium',
    created_at: '2026-04-27T12:10:00Z',
    description: 'فحص فرامل وإطارات',
    notes: '',
    vehicle: { id: 88002, plate_number: 'KSA 4521' },
  },
]

export const demoCustomerServices = [
  { id: 66001, name: 'تغيير زيت' },
  { id: 66002, name: 'تغيير فلاتر' },
  { id: 66003, name: 'غسيل مركبة' },
  { id: 66004, name: 'فحص شامل' },
]

export const demoCustomerPricingVersions = [
  {
    uuid: 'demo-pricing-v1',
    version_no: 1,
    is_reference: true,
    activated_at: '2026-04-25T10:00:00Z',
    contract_id: 4501,
    root_contract_id: 4501,
    sell_snapshot: [
      { service_code: 'OIL_CHANGE', unit_price: 180, currency: 'SAR' },
      { service_code: 'FILTER_CHANGE', unit_price: 95, currency: 'SAR' },
      { service_code: 'CAR_WASH', unit_price: 45, currency: 'SAR' },
      { service_code: 'GENERAL_INSPECTION', unit_price: 120, currency: 'SAR' },
    ],
  },
]

