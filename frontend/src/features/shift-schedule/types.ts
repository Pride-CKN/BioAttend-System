export type ShiftStatus = "active" | "inactive"

export type Shift = {
  id: string
  code: string
  name: string
  startTime: string
  endTime: string
  breakMinutes: number
  toleranceMinutes: number
  minimumWorkHours: number
  overnight: boolean
  assignedEmployees: number
  status: ShiftStatus
}

export type EmployeeSchedule = {
  id: string
  employee: string
  initials: string
  department: string
  shiftId: string
  date: string
  validFrom: string
  validTo?: string
  scope: "company" | "division" | "employee"
  source: "Auto" | "Manual"
  status: "active" | "leave" | "off"
  note?: string
}

export type WeeklyTemplate = {
  id: string
  name: string
  department: string
  days: string
  shiftId: string
  status: "active" | "inactive"
}

export type ScheduleHistory = {
  id: string
  action: string
  actor: string
  target: string
  timestamp: string
}

export type ViewTab = "master" | "schedule" | "template" | "history"
