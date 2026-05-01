/**
 * افتراضيات ملف النشاط — يجب أن تبقى مطابقة لـ
 * `App\Support\BusinessFeatureProfileDefaults::featureMatrixForType`.
 */
export type BusinessType = 'service_center' | 'retail' | 'fleet_operator'

/** قيم اختيارية؛ الغياب = الافتراضي من النوع. `platform_execution_partner` يُفعّل واجهة شريك التنفيذ للمنصة فقط عند true صريحاً. */
export type FeatureMatrix = Record<string, boolean | undefined> & {
  platform_execution_partner?: boolean
}

export function featureMatrixForBusinessType(businessType: BusinessType): FeatureMatrix {
  switch (businessType) {
    case 'service_center':
      return {
        operations: true,
        hr: true,
        finance: true,
        accounting: true,
        fixed_assets: false,
        inventory: true,
        reports: true,
        intelligence: true,
        crm: true,
        fleet: true,
        org_structure: true,
        supplier_contract_mgmt: true,
        platform_execution_partner: false,
      }
    case 'retail':
      return {
        operations: true,
        hr: true,
        finance: true,
        accounting: true,
        fixed_assets: false,
        inventory: true,
        reports: true,
        intelligence: false,
        crm: true,
        fleet: false,
        org_structure: false,
        supplier_contract_mgmt: false,
        platform_execution_partner: false,
      }
    case 'fleet_operator':
      return {
        operations: true,
        hr: true,
        finance: true,
        accounting: true,
        fixed_assets: false,
        inventory: false,
        reports: true,
        intelligence: true,
        crm: true,
        fleet: true,
        org_structure: true,
        supplier_contract_mgmt: true,
        platform_execution_partner: false,
      }
    default:
      return featureMatrixForBusinessType('service_center')
  }
}

export function normalizeBusinessType(value: unknown): BusinessType {
  if (value === 'retail' || value === 'fleet_operator' || value === 'service_center') {
    return value
  }
  return 'service_center'
}

/** عناوين قصيرة للواجهة العربية */
export function businessTypeLabelAr(type: BusinessType): string {
  switch (type) {
    case 'retail':
      return 'تجاري / تجزئة'
    case 'fleet_operator':
      return 'مشغّل أسطول'
    default:
      return 'مركز خدمات وصيانة'
  }
}
