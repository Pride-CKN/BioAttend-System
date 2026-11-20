export type PositionLevel = 1 | 2 | 3
export type Position = {
  id: string
  code: string
  name: string
  level: PositionLevel
  active: boolean
  holderCount: number
}

export type PositionInput = Omit<Position, "id" | "holderCount">
export type PositionHolder = {
  id: string
  positionId: string
  employeeId: string
  employeeName: string
  employeeCode: string
  division: string
}
export type PositionFilters = { search: string; level: string; status: string }
