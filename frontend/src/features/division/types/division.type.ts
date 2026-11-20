export type DivisionStatus = "Aktif" | "Nonaktif"

export type Unit = {
  id: string
  code: string
  name: string
  divisionId: string
  employeeCodeDigit: number | null
  status: DivisionStatus
  headId: string | null
  updatedAt: string
}

export type Division = {
  id: string
  code: string
  name: string
  employeeCodeDigit: number | null
  units: Unit[]
  status: DivisionStatus
  headId: string | null
  updatedAt: string
}

export type DivisionInput = Pick<Division, "code" | "name" | "status" | "headId">

export type UnitInput = Pick<Unit, "code" | "name" | "employeeCodeDigit" | "headId" | "status">

export type DivisionStats = {
  totalDivision: number
  activeDivision: number
  inactiveDivision: number
  totalUnit: number
  activeUnit: number
  inactiveUnit: number
}

export type DivisionFilters = {
  search: string
  unit: string // "" = semua
  status: "" | DivisionStatus
}
