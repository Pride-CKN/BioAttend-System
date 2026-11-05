import { employees } from "@/seeds/employee"
import type { Department, DepartmentStats } from "@/features/department/department.type"

// Map dibuat sekali di level modul → lookup O(1) per baris, bukan find() per render.
const employeeNameById = new Map(employees.map((e) => [e.id, e.name]))

export const headName = (headId: string | null) => (headId ? employeeNameById.get(headId) ?? "—" : "—")

const shortDate = new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", year: "numeric" })

export function formatShortDate(iso: string) {
  if (!iso) return "—"
  return shortDate.format(new Date(`${iso}T00:00:00`))
}

// Satu kali loop untuk semua angka statistik.
export function computeStats(items: Department[]): DepartmentStats {
  const divisions = new Set<string>()
  let active = 0
  let totalEmployee = 0
  for (const d of items) {
    divisions.add(d.division)
    if (d.status === "Aktif") active++
    totalEmployee += d.totalEmployee
  }
  return {
    totalDepartment: items.length,
    activeDepartment: active,
    totalDivision: divisions.size,
    divisionNames: [...divisions].join(", ") || "—",
    totalEmployee,
  }
}
