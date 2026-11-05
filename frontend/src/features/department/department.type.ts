export type DepartmentStatus = "Aktif" | "Nonaktif"

export type Department = {
  id: string
  code: string
  name: string
  division: string
  totalEmployee: number
  status: DepartmentStatus
  headId: string | null
  updatedAt: string 
}

export type DepartmentInput = Pick<Department, "code" | "name" | "division" | "status" | "headId">

export type DepartmentStats = {
  totalDepartment: number
  activeDepartment: number
  totalDivision: number
  divisionNames: string
  totalEmployee: number
}

export type DepartmentFilters = {
  search: string
  division: string // "" = semua
  status: "" | DepartmentStatus
}
