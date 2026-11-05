import { employeeSchedules, scheduleHistory, shifts, weeklyTemplates } from "@/mocks/shift-schedule"
import type { EmployeeSchedule, ScheduleHistory, Shift, ShiftStatus, WeeklyTemplate } from "@/features/shift-schedule/types"

export type ShiftInput = Omit<Shift, "id" | "code" | "assignedEmployees" | "status">
export type ScheduleFilters = {
  date?: string
  shiftId?: string
  department?: string
}

// Mengambil daftar master shift dari sumber data modul.
export async function getShifts(): Promise<Shift[]> {
  return shifts.map((shift) => ({ ...shift }))
}

// Mengambil jadwal kerja karyawan berdasarkan periode yang dipilih.
export async function getEmployeeSchedules(date?: string): Promise<EmployeeSchedule[]> {
  return employeeSchedules
    .filter((schedule) => !date || schedule.date === date)
    .map((schedule) => ({ ...schedule }))
}

export async function getWeeklyTemplates(): Promise<WeeklyTemplate[]> {
  return weeklyTemplates.map((template) => ({ ...template }))
}

export async function getScheduleHistory(): Promise<ScheduleHistory[]> {
  return scheduleHistory.map((item) => ({ ...item }))
}

export async function createShift(input: ShiftInput): Promise<Shift> {
  const normalizedName = input.name.trim().toLowerCase()
  if (!normalizedName) throw new Error("Nama shift wajib diisi.")
  if (shifts.some((shift) => shift.name.toLowerCase() === normalizedName)) {
    throw new Error("Nama shift harus unik.")
  }
  if (input.startTime !== "Fleksibel" && input.endTime !== "Fleksibel") {
    const sameDay = input.overnight
      ? input.endTime < input.startTime
      : input.endTime > input.startTime
    if (!sameDay) throw new Error("Jam pulang harus setelah jam masuk.")
  }
  if (input.toleranceMinutes < 0 || input.toleranceMinutes > 120) {
    throw new Error("Toleransi harus berada di antara 0 sampai 120 menit.")
  }
  if (input.breakMinutes < 0 || input.minimumWorkHours < 0) {
    throw new Error("Durasi istirahat dan minimal jam kerja tidak boleh negatif.")
  }
  const sequence = shifts.length + 1
  const created: Shift = {
    ...input,
    id: `SHF-${String(sequence).padStart(2, "0")}`,
    code: `SHF-${String(sequence).padStart(2, "0")}`,
    assignedEmployees: 0,
    status: "active",
  }
  shifts.push(created)
  return { ...created }
}

export async function updateShift(id: string, input: ShiftInput): Promise<Shift> {
  const index = shifts.findIndex((shift) => shift.id === id)
  if (index < 0) throw new Error(`Shift ${id} tidak ditemukan.`)
  const duplicate = shifts.some((shift) => shift.id !== id && shift.name.toLowerCase() === input.name.trim().toLowerCase())
  if (duplicate) throw new Error("Nama shift harus unik.")
  const updated = { ...shifts[index], ...input }
  shifts[index] = updated
  return { ...updated }
}

// Mengubah status shift tanpa menghapus konfigurasi shift.
export async function updateShiftStatus(id: string, status: ShiftStatus): Promise<Shift> {
  const shift = shifts.find((item) => item.id === id)
  if (!shift) throw new Error(`Shift ${id} tidak ditemukan`)
  shift.status = status
  return { ...shift }
}

export async function duplicateShift(id: string): Promise<Shift> {
  const source = shifts.find((item) => item.id === id)
  if (!source) throw new Error(`Shift ${id} tidak ditemukan`)
  const copy = { ...source, id: `SHF-${String(shifts.length + 1).padStart(2, "0")}`, code: `SHF-${String(shifts.length + 1).padStart(2, "0")}`, name: `${source.name} Copy`, status: "inactive" as const, assignedEmployees: 0 }
  shifts.push(copy)
  return { ...copy }
}

export async function assignEmployeeSchedule(input: Omit<EmployeeSchedule, "id">): Promise<EmployeeSchedule> {
  if (input.validTo && input.validTo < input.validFrom) {
    throw new Error("Tanggal selesai tidak boleh sebelum tanggal mulai.")
  }
  const existing = employeeSchedules.find((item) => item.employee === input.employee && item.date === input.date)
  if (existing) {
    Object.assign(existing, input)
    return { ...existing }
  }
  const created = { ...input, id: `SCH-${String(employeeSchedules.length + 1).padStart(3, "0")}` }
  employeeSchedules.push(created)
  return { ...created }
}

export async function bulkAssignSchedules(
  employeeNames: string[],
  shiftId: string,
  date: string,
): Promise<EmployeeSchedule[]> {
  const shift = shifts.find((item) => item.id === shiftId)
  if (!shift) throw new Error("Shift yang dipilih tidak ditemukan.")
  const updated = await Promise.all(employeeNames.map((employee) => assignEmployeeSchedule({
    employee,
    initials: employee.split(" ").map((part) => part[0]).join("").slice(0, 2).toUpperCase(),
    department: "Produksi",
    shiftId,
    date,
    validFrom: date,
    scope: "employee",
    source: "Manual",
    status: "active",
  })))
  return updated
}
