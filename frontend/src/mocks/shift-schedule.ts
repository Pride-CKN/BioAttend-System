import type { EmployeeSchedule, ScheduleHistory, Shift, WeeklyTemplate } from "@/features/shift-schedule/types"

export const shifts: Shift[] = [
  { id: "SHF-01", code: "SHF-01", name: "Shift Pagi", startTime: "07:00", endTime: "15:00", breakMinutes: 30, toleranceMinutes: 15, minimumWorkHours: 8, overnight: false, assignedEmployees: 142, status: "active" },
  { id: "SHF-02", code: "SHF-02", name: "Shift Siang", startTime: "15:00", endTime: "23:00", breakMinutes: 30, toleranceMinutes: 15, minimumWorkHours: 8, overnight: false, assignedEmployees: 68, status: "active" },
  { id: "SHF-03", code: "SHF-03", name: "Shift Malam", startTime: "23:00", endTime: "07:00", breakMinutes: 30, toleranceMinutes: 15, minimumWorkHours: 8, overnight: true, assignedEmployees: 38, status: "active" },
  { id: "SHF-04", code: "SHF-04", name: "Shift Kolensi", startTime: "08:00", endTime: "16:00", breakMinutes: 0, toleranceMinutes: 0, minimumWorkHours: 8, overnight: false, assignedEmployees: 12, status: "active" },
  { id: "SHF-05", code: "SHF-05", name: "Shift Fleksibel", startTime: "Fleksibel", endTime: "Fleksibel", breakMinutes: 15, toleranceMinutes: 0, minimumWorkHours: 0, overnight: false, assignedEmployees: 0, status: "inactive" },
]

export const employeeSchedules: EmployeeSchedule[] = [
  { id: "SCH-001", employee: "Andi Saputra", initials: "AS", department: "Produksi", shiftId: "SHF-01", date: "2026-10-24", validFrom: "2026-10-01", scope: "division", source: "Auto", status: "active", note: "Jadwal reguler produksi" },
  { id: "SCH-002", employee: "Budi Santoso", initials: "BS", department: "Operasional", shiftId: "SHF-01", date: "2026-10-24", validFrom: "2026-10-01", scope: "company", source: "Auto", status: "active" },
  { id: "SCH-003", employee: "Dimas Pratama", initials: "DP", department: "Produksi", shiftId: "SHF-02", date: "2026-10-24", validFrom: "2026-10-15", validTo: "2026-10-31", scope: "employee", source: "Manual", status: "active", note: "Pengganti shift sore" },
  { id: "SCH-004", employee: "Hana Putri", initials: "HP", department: "Operasional", shiftId: "SHF-03", date: "2026-10-24", validFrom: "2026-10-01", scope: "division", source: "Auto", status: "active" },
  { id: "SCH-005", employee: "Citra Lestari", initials: "CL", department: "Quality Control", shiftId: "SHF-04", date: "2026-10-24", validFrom: "2026-10-01", scope: "employee", source: "Manual", status: "active" },
  { id: "SCH-006", employee: "Fajar Nugroho", initials: "FN", department: "Maintenance", shiftId: "SHF-01", date: "2026-10-24", validFrom: "2026-10-01", scope: "division", source: "Auto", status: "active" },
]

export const weeklyTemplates: WeeklyTemplate[] = [
  { id: "TPL-001", name: "Operasional Reguler", department: "Produksi", days: "Senin - Sabtu", shiftId: "SHF-01", status: "active" },
  { id: "TPL-002", name: "Operasional Malam", department: "Operasional", days: "Senin - Jumat", shiftId: "SHF-03", status: "active" },
]

export const scheduleHistory: ScheduleHistory[] = [
  { id: "HIS-001", action: "Assign jadwal", actor: "Rio Pratama", target: "Produksi · 24 Oktober 2026", timestamp: "2026-10-23 16:30" },
  { id: "HIS-002", action: "Perbarui shift", actor: "Siti Rahmawati", target: "Shift Pagi", timestamp: "2026-10-22 10:15" },
]
