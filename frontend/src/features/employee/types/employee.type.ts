export type Employee = {
  id: string
  full_name: string
  unit: string
  division: string
  position: string
  positionCode?: string
  status: "Aktif" | "Nonaktif" | "Resigned"
  avatar?: string
  color?: string
  employeeCode?: string
  nip?: string
  email?: string | null
  phone?: string | null
  officeName?: string
  gender?: "MALE" | "FEMALE" | null
  joinDate?: string | null
  employmentStatus?: "ACTIVE" | "INACTIVE" | "RESIGNED"
  officeId?: string
  divisionId?: string
  unitId?: string | null
  positionId?: string | null
  managerId?: string | null
}
export type EmployeeProfile = {
  jenisKelamin: string
  email: string
  hp: string
  role: string
  supervisor: string
}

export type EmployeeFilters = {
  keyword: string
  officeId: string
  divisionId: string
  unitId: string
  positionId: string
  employmentStatus: string
}

export type EmployeeMasterOption = { id: string; code?: string; name: string }
export type EmployeeFormOptions = {
  offices: EmployeeMasterOption[]
  divisions: EmployeeMasterOption[]
  units: Array<EmployeeMasterOption & { divisionId: string }>
  positions: EmployeeMasterOption[]
  managers: Array<EmployeeMasterOption & {
    employeeCode?: string
    divisionId?: string
    unitId?: string | null
    positionCode?: string
  }>
}

export type EmployeeQuery = EmployeeFilters & { page: number; pageSize: number }

export type Page<T> = {
  items: T[]
  total: number
  page: number
  pageSize: number
  pageCount: number
}

export type EmployeeStats = {
  total: number
  active: number
  inactive: number
  resigned: number
  newThisMonth: number
}

export type EmployeeDetail = { employee: Employee; profile: EmployeeProfile }
export type EmployeeSaveResult = { id: string; activation?: { token: string; expiresAt: string } }

export type EmployeeFormValues = {
  full_name: string
  jenisKelamin: string
  hp: string
  email: string
  role: string
  officeId: string
  office: string
  division: string
  divisionId: string
  unit: string
  unitId: string
  position: string
  positionId: string
  joinDate: string
  supervisor: string
  managerId: string
  pin: string
  autoGenerateEmployeeCode: boolean
}

export type EmployeeFormErrors = Partial<Record<keyof EmployeeFormValues, string>>

export type BulkAction = "aktifkan" | "nonaktifkan" | "export" | "hapus"
