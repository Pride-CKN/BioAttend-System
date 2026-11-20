import { ApiError } from "@/api/api-error"
import { apiEndpoints } from "@/api/endpoints"
import { masterCreate, masterGet, masterList, masterSetActive, masterUpdate } from "@/api/master"
import { fetchAllPages } from "@/api/paginate"
import type { Division, DivisionInput, DivisionStatus, Unit, UnitInput } from "@/features/department/types/department.type"

type DivisionDto = {
  id: number
  code: string
  name: string
  employee_code_digit: number | null
  head_id: number | null
  is_active: boolean
  updated_at: string
}

type UnitDto = {
  id: number
  code: string
  name: string
  division_id: number
  employee_code_digit: number | null
  head_id: number | null
  is_active: boolean
  updated_at: string
}

type EmployeeHeadDto = {
  id: number
  full_name: string
  employee_code: string | null
  nip: string
  position?: { name?: string | null } | null
}

const statusFromActive = (active: boolean): DivisionStatus => active ? "Aktif" : "Nonaktif"
const activeFromStatus = (status: DivisionStatus) => status === "Aktif"
const dateOnly = (value: string) => value.slice(0, 10)

const mapUnit = (dto: UnitDto): Unit => ({
  id: String(dto.id),
  code: dto.code,
  name: dto.name,
  divisionId: String(dto.division_id),
  employeeCodeDigit: dto.employee_code_digit,
  headId: dto.head_id === null ? null : String(dto.head_id),
  status: statusFromActive(dto.is_active),
  updatedAt: dateOnly(dto.updated_at),
})

const mapDivision = (dto: DivisionDto, units: Unit[]): Division => ({
  id: String(dto.id),
  code: dto.code,
  name: dto.name,
  employeeCodeDigit: dto.employee_code_digit,
  headId: dto.head_id === null ? null : String(dto.head_id),
  status: statusFromActive(dto.is_active),
  updatedAt: dateOnly(dto.updated_at),
  units: units.filter((unit) => unit.divisionId === String(dto.id)),
})

const divisionBody = (input: DivisionInput) => ({
  code: input.code.trim(),
  name: input.name.trim(),
  head_id: input.headId ? Number(input.headId) : null,
  is_active: activeFromStatus(input.status),
})

const divisionDtoBody = (dto: DivisionDto) => ({
  code: dto.code,
  name: dto.name,
  head_id: dto.head_id,
})

const unitBody = (divisionId: string, input: UnitInput) => ({
  code: input.code.trim(),
  name: input.name.trim(),
  division_id: Number(divisionId),
  employee_code_digit: input.employeeCodeDigit,
  head_id: input.headId ? Number(input.headId) : null,
  is_active: activeFromStatus(input.status),
})

const unitDtoBody = (dto: UnitDto) => ({
  code: dto.code,
  name: dto.name,
  division_id: dto.division_id,
  employee_code_digit: dto.employee_code_digit,
  head_id: dto.head_id,
})

async function unitsFor(divisionId: string): Promise<Unit[]> {
  return (await masterList<UnitDto>("units"))
    .filter((unit) => String(unit.division_id) === divisionId)
    .map(mapUnit)
}

export const divisionService = {
  async listHeads(): Promise<Array<{ id: string; label: string }>> {
    const employees = await fetchAllPages<EmployeeHeadDto>(apiEndpoints.employees, {
      employment_status: "ACTIVE",
    }, { perPage: 100 })
    return employees.map((employee) => ({
      id: String(employee.id),
      label: `${employee.full_name} — ${employee.position?.name ?? employee.employee_code ?? employee.nip}`,
    }))
  },

  async list(): Promise<Division[]> {
    const [divisions, unitDtos] = await Promise.all([
      masterList<DivisionDto>("divisions"),
      masterList<UnitDto>("units"),
    ])
    const units = unitDtos.map(mapUnit)
    return divisions.map((division) => mapDivision(division, units))
  },

  async get(id: string): Promise<Division | null> {
    try {
      const [division, units] = await Promise.all([
        masterGet<DivisionDto>("divisions", id),
        unitsFor(id),
      ])
      return mapDivision(division, units)
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) return null
      throw error
    }
  },

  async nextCode(): Promise<string> {
    const divisions = await masterList<DivisionDto>("divisions")
    const max = divisions.reduce((currentMax, division) => {
      const match = /^PRD-(\d+)$/.exec(division.code)
      return match ? Math.max(currentMax, Number(match[1])) : currentMax
    }, 0)
    return `PRD-${String(max + 1).padStart(2, "0")}`
  },

  async create(input: DivisionInput): Promise<Division> {
    const created = await masterCreate<DivisionDto>("divisions", divisionBody(input))
    return mapDivision(created, [])
  },

  async update(id: string, input: DivisionInput): Promise<Division> {
    await masterUpdate("divisions", id, divisionBody(input))
    return (await this.get(id)) ?? Promise.reject(new Error("Division tidak ditemukan"))
  },

  async setStatus(id: string, status: DivisionStatus): Promise<Division> {
    const updated = await masterSetActive<DivisionDto>("divisions", id, activeFromStatus(status), divisionDtoBody)
    return mapDivision(updated, await unitsFor(id))
  },

  async createUnit(divisionId: string, input: UnitInput): Promise<Unit> {
    return mapUnit(await masterCreate<UnitDto>("units", unitBody(divisionId, input)))
  },

  async setUnitStatus(unitId: string, status: DivisionStatus): Promise<Unit> {
    const updated = await masterSetActive<UnitDto>("units", unitId, activeFromStatus(status), unitDtoBody)
    return mapUnit(updated)
  },
}