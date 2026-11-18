import { ApiError } from "@/api/api-error"
import { apiDelete, apiGet, apiGetPaginated, apiPost, apiPut } from "@/api/api-client"
import { apiEndpoints } from "@/api/endpoints"
import { masterList } from "@/api/master"
import type {
  BulkAction,
  Employee,
  EmployeeDetail,
  EmployeeFilters,
  EmployeeFormOptions,
  EmployeeFormValues,
  EmployeeMasterOption,
  EmployeeProfile,
  EmployeeQuery,
  EmployeeSaveResult,
  EmployeeStats,
  Page,
} from "@/features/employee/types/employee.type"

export interface EmployeeService {
  list(query: EmployeeQuery): Promise<Page<Employee>>
  stats(): Promise<EmployeeStats>
  getFormOptions(): Promise<EmployeeFormOptions>
  getDetail(id: string): Promise<EmployeeDetail | null>
  findPinOwner(pin: string, exceptId?: string): Promise<Employee | undefined>
  save(id: string | undefined, values: EmployeeFormValues, mode: "draft" | "aktif"): Promise<EmployeeSaveResult>
  bulk(action: BulkAction, ids: string[]): Promise<void>
  remove(ids: string[]): Promise<void>
}

type RelationDto = { id: number; code?: string | null; name?: string | null }
type EmployeeDto = {
  id: number
  nip: string
  employee_code: string | null
  full_name: string
  email: string | null
  phone: string | null
  gender: "MALE" | "FEMALE" | null
  join_date: string | null
  employment_status: "ACTIVE" | "INACTIVE" | "RESIGNED"
  office_id: number | null
  division_id: number | null
  unit_id: number | null
  position_id: number | null
  manager_id: number | null
  office?: RelationDto | null
  division?: RelationDto | null
  unit?: RelationDto | null
  position?: RelationDto | null
  manager?: { id: number; full_name: string } | null
  user?: { roles?: Array<{ name: string }> } | null
}

type MasterDto = {
  id: number
  code?: string | null
  name: string
  division_id?: number
  is_active?: boolean
}

type Paginated<T> = {
  data: T[]
  meta: { total: number; per_page: number; current_page: number; last_page: number }
}

const statusToLabel = (status: EmployeeDto["employment_status"]): Employee["status"] =>
  status === "ACTIVE" ? "Aktif" : status === "RESIGNED" ? "Resigned" : "Nonaktif"

const labelToStatus = (status: string): EmployeeDto["employment_status"] => {
  if (status === "Resigned" || status === "RESIGNED") return "RESIGNED"
  if (status === "Aktif" || status === "ACTIVE") return "ACTIVE"
  return "INACTIVE"
}

const option = (item: MasterDto): EmployeeMasterOption => ({
  id: String(item.id),
  code: item.code ?? undefined,
  name: item.name,
})

function mapEmployee(dto: EmployeeDto): Employee {
  const division = dto.division?.name ?? "—"
  const unit = dto.unit?.name ?? "—"
  return {
    id: String(dto.id),
    employeeCode: dto.employee_code ?? undefined,
    nip: dto.nip,
    full_name: dto.full_name,
    unit,
    division,
    position: dto.position?.name ?? "—",
    status: statusToLabel(dto.employment_status),
    color: "bg-slate-500",
    avatar: "",
    email: dto.email,
    phone: dto.phone,
    officeName: dto.office?.name ?? "—",
    gender: dto.gender,
    joinDate: dto.join_date,
    employmentStatus: dto.employment_status,
    officeId: dto.office_id === null ? undefined : String(dto.office_id),
    divisionId: dto.division_id === null ? undefined : String(dto.division_id),
    unitId: dto.unit_id === null ? null : String(dto.unit_id),
    positionId: dto.position_id === null ? null : String(dto.position_id),
    managerId: dto.manager_id === null ? null : String(dto.manager_id),
  }
}

function profileFrom(dto: EmployeeDto): EmployeeProfile {
  return {
    jenisKelamin: dto.gender === "FEMALE" ? "Perempuan" : dto.gender === "MALE" ? "Laki-laki" : "",
    hp: dto.phone ?? "",
    email: dto.email ?? "",
    role: dto.user?.roles?.[0]?.name ?? "",
    supervisor: dto.manager?.full_name ?? "",
  }
}

let formOptionsCache: EmployeeFormOptions | null = null
let formOptionsRequest: Promise<EmployeeFormOptions> | null = null
let allEmployeesCache: Employee[] | null = null
let allEmployeesRequest: Promise<Employee[]> | null = null

function invalidateEmployeeCache() {
  formOptionsCache = null
  allEmployeesCache = null
  formOptionsRequest = null
  allEmployeesRequest = null
}

async function getFormOptions(): Promise<EmployeeFormOptions> {
  if (formOptionsCache) return formOptionsCache
  formOptionsRequest ??= Promise.all([
    masterList<MasterDto>("offices"),
    masterList<MasterDto>("divisions"),
    masterList<MasterDto>("units"),
    masterList<MasterDto>("positions"),
    fetchAllEmployees(),
  ]).then(([offices, divisions, units, positions, employees]) => {
    formOptionsCache = {
      offices: offices.filter((item) => item.is_active !== false).map(option),
      divisions: divisions.filter((item) => item.is_active !== false).map(option),
      units: units.filter((item) => item.is_active !== false).map((item) => ({ ...option(item), divisionId: String(item.division_id) })),
      positions: positions.filter((item) => item.is_active !== false).map(option),
      managers: employees.map((employee) => ({ id: employee.id, name: employee.full_name, employeeCode: employee.employeeCode })),
    }
    return formOptionsCache
  }).finally(() => {
    formOptionsRequest = null
  })
  return formOptionsRequest
}

async function fetchAllEmployees(): Promise<Employee[]> {
  if (allEmployeesCache) return allEmployeesCache
  allEmployeesRequest ??= (async () => {
    const first = await apiGetPaginated<EmployeeDto>(apiEndpoints.employees, {
      query: { per_page: 100, page: 1 },
    })
    const pages: Paginated<EmployeeDto>[] = [first]
    for (let page = 2; page <= first.meta.last_page; page++) {
      pages.push(await apiGetPaginated<EmployeeDto>(apiEndpoints.employees, { query: { per_page: 100, page } }))
    }
    return pages.flatMap((page) => page.data.map(mapEmployee))
  })().then((employees) => {
    allEmployeesCache = employees
    return employees
  }).finally(() => {
    allEmployeesRequest = null
  })
  return allEmployeesRequest
}

function basePayload(values: EmployeeFormValues) {
  return {
    nip: values.pin.trim(),
    full_name: values.full_name.trim(),
    email: values.email.trim() || null,
    phone: values.hp.trim() || null,
    gender: values.jenisKelamin === "Perempuan" || values.jenisKelamin === "FEMALE" ? "FEMALE" : values.jenisKelamin ? "MALE" : null,
    join_date: values.joinDate || null,
    position_id: values.positionId ? Number(values.positionId) : null,
    manager_id: values.managerId ? Number(values.managerId) : null,
  }
}

function placementPayload(values: EmployeeFormValues) {
  return {
    office_id: Number(values.officeId),
    division_id: Number(values.divisionId),
    unit_id: values.unitId ? Number(values.unitId) : null,
  }
}

function downloadEmployeeCsv(employees: Employee[]) {
  const rows = [
    ["NIP", "Nama", "Email", "Telepon", "Divisi", "Unit", "Jabatan", "Status"],
    ...employees.map((employee) => [
      employee.nip ?? "", employee.full_name, employee.email ?? "",
      employee.phone ?? "", employee.division, employee.unit, employee.position, employee.employmentStatus ?? "",
    ]),
  ]
  const csv = rows.map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(",")).join("\n")
  const url = URL.createObjectURL(new Blob(["\uFEFF", csv], { type: "text/csv;charset=utf-8" }))
  const link = document.createElement("a")
  link.href = url
  link.download = "employees.csv"
  link.click()
  URL.revokeObjectURL(url)
}

export const employeeService: EmployeeService = {
  async list(query) {
    const status = query.employmentStatus ? labelToStatus(query.employmentStatus) : undefined
    const result = await apiGetPaginated<EmployeeDto>(apiEndpoints.employees, {
      query: {
        q: query.keyword.trim() || undefined,
        office_id: query.officeId || undefined,
        division_id: query.divisionId || undefined,
        unit_id: query.unitId || undefined,
        employment_status: status,
        per_page: query.pageSize,
        page: query.page,
      },
    })
    return {
      items: result.data.map(mapEmployee),
      total: result.meta.total,
      page: result.meta.current_page,
      pageSize: result.meta.per_page,
      pageCount: result.meta.last_page,
    }
  },

  async stats() {
    const employees = await fetchAllEmployees()
    const month = new Date().toISOString().slice(0, 7)
    return employees.reduce<EmployeeStats>((stats, employee) => {
      stats.total++
      if (employee.employmentStatus === "ACTIVE") stats.active++
      else if (employee.employmentStatus === "RESIGNED") stats.resigned++
      else stats.inactive++
      if (employee.joinDate?.startsWith(month)) stats.newThisMonth++
      return stats
    }, { total: 0, active: 0, inactive: 0, resigned: 0, newThisMonth: 0 })
  },

  getFormOptions,

  async getDetail(id) {
    try {
      const dto = await apiGet<EmployeeDto>(apiEndpoints.employee(id))
      return { employee: mapEmployee(dto), profile: profileFrom(dto) }
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) return null
      throw error
    }
  },

  async findPinOwner(pin, exceptId) {
    const result = await apiGetPaginated<EmployeeDto>(apiEndpoints.employees, { query: { q: pin, per_page: 100 } })
    const match = result.data.find((employee) => employee.nip === pin && String(employee.id) !== exceptId)
    return match ? mapEmployee(match) : undefined
  },

  async save(id, values, mode) {
    const status = mode === "aktif" ? "ACTIVE" : "INACTIVE"
    const payload = {
      ...basePayload(values),
      employment_status: status,
      ...placementPayload(values),
      effective_from: values.joinDate || new Date().toISOString().slice(0, 10),
      reason: id ? "Perubahan data karyawan" : "Pembuatan data karyawan",
    }

    if (!id) {
      if (mode === "aktif") {
        const registered = await apiPost<{ employee: EmployeeDto; activation: { token: string; expires_at: string } }>(
          apiEndpoints.employeesRegister,
          { ...payload, role: values.role === "KADIV" ? "KADIV" : "EMPLOYEE" },
        )
        invalidateEmployeeCache()
        return { id: String(registered.employee.id), activation: { token: registered.activation.token, expiresAt: registered.activation.expires_at } }
      }
      const created = await apiPost<EmployeeDto>(apiEndpoints.employees, payload)
      invalidateEmployeeCache()
      return { id: String(created.id) }
    }

    const current = await apiGet<EmployeeDto>(apiEndpoints.employee(id))
    const changedPlacement =
      String(current.office_id ?? "") !== values.officeId ||
      String(current.division_id ?? "") !== values.divisionId ||
      String(current.unit_id ?? "") !== values.unitId
    const updatePayload: Record<string, unknown> = {
      ...basePayload(values),
      employment_status: status,
    }
    if (values.positionId !== String(current.position_id ?? "")) updatePayload.position_id = values.positionId ? Number(values.positionId) : null
    if (changedPlacement) {
      Object.assign(updatePayload, placementPayload(values), {
        effective_from: new Date().toISOString().slice(0, 10),
        reason: "Perubahan penempatan karyawan",
      })
    }
    await apiPut<EmployeeDto>(apiEndpoints.employee(id), updatePayload)
    invalidateEmployeeCache()
    return { id }
  },

  async bulk(action, ids) {
    if (action === "export") {
      const employees = await fetchAllEmployees()
      downloadEmployeeCsv(employees.filter((employee) => ids.includes(employee.id)))
      return
    }
    if (action === "hapus") return employeeService.remove(ids)
    await Promise.all(ids.map((id) => apiPut<EmployeeDto>(apiEndpoints.employee(id), {
      employment_status: action === "aktifkan" ? "ACTIVE" : "INACTIVE",
    })))
    invalidateEmployeeCache()
  },

  async remove(ids) {
    await Promise.all(ids.map((id) => apiDelete<unknown>(apiEndpoints.employee(id))))
    invalidateEmployeeCache()
  },
}
