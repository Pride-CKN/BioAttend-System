export type LeaveRequest = {
  id: string
  employee: string
  type: "Annual Leave" | "Sick Leave" | "Permission"
  startDate: string
  endDate: string
  reason: string
  status: "pending" | "approved" | "rejected"
}

export type OvertimeRequest = {
  id: string
  employee: string
  date: string
  startTime: string
  endTime: string
  hours: number
  reason: string
  status: "pending" | "approved" | "rejected"
}

export const leaveRequests: LeaveRequest[] = [
  { id: "LV-2026-001", employee: "Eka Wulandari", type: "Annual Leave", startDate: "2026-11-10", endDate: "2026-11-12", reason: "Keperluan keluarga", status: "pending" },
  { id: "LV-2026-002", employee: "Dewi Lestari", type: "Sick Leave", startDate: "2026-10-28", endDate: "2026-10-28", reason: "Pemeriksaan kesehatan", status: "approved" },
  { id: "LV-2026-003", employee: "Rizky Pratama", type: "Permission", startDate: "2026-10-25", endDate: "2026-10-25", reason: "Urusan administrasi", status: "rejected" },
]

export const overtimeRequests: OvertimeRequest[] = [
  { id: "OT-2026-001", employee: "Budi Santoso", date: "2026-10-30", startTime: "17:00", endTime: "20:00", hours: 3, reason: "Closing outlet", status: "approved" },
  { id: "OT-2026-002", employee: "Andi Saputra", date: "2026-10-29", startTime: "17:00", endTime: "19:00", hours: 2, reason: "Maintenance server", status: "pending" },
]
