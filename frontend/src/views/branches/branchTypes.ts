/** صف فرع من API النشاط متعدد الفروع */
export interface BranchRow {
  id: number
  name: string
  name_ar?: string | null
  code?: string | null
  phone?: string | null
  address?: string | null
  city?: string | null
  latitude?: number | null
  longitude?: number | null
  is_main: boolean
  is_active: boolean
  cross_branch_access?: boolean
  status?: string
  /** جدول أسبوعي: mon … sun → [["08:00","18:00"], …] */
  opening_hours?: Record<string, [string, string][]> | null
}
