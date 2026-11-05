export type SystemUser = {
  id: string
  name: string
  email: string
  role: "HR Manager" | "HR Staff" | "Administrator" | "Viewer"
  status: "active" | "inactive"
  lastLogin: string
}

export type NotificationItem = {
  id: string
  title: string
  message: string
  type: "info" | "warning" | "success"
  read: boolean
  createdAt: string
}

export type AuditLog = {
  id: string
  actor: string
  action: string
  resource: string
  createdAt: string
  ipAddress: string
}

export const systemUsers: SystemUser[] = [
  { id: "USR-001", name: "Rio Pratama", email: "rio@pridechicken.co.id", role: "HR Manager", status: "active", lastLogin: "2026-11-04 08:12" },
  { id: "USR-002", name: "Sari Dewi", email: "sari@pridechicken.co.id", role: "HR Staff", status: "active", lastLogin: "2026-11-04 07:55" },
  { id: "USR-003", name: "Admin System", email: "admin@pridechicken.co.id", role: "Administrator", status: "active", lastLogin: "2026-11-03 22:10" },
]

export const notifications: NotificationItem[] = [
  { id: "NOTIF-001", title: "Pengajuan cuti baru", message: "Eka Wulandari mengajukan cuti tahunan.", type: "info", read: false, createdAt: "2026-11-04 08:05" },
  { id: "NOTIF-002", title: "Perangkat offline", message: "ZKTeco Office tidak mengirim data selama 15 menit.", type: "warning", read: false, createdAt: "2026-11-04 07:40" },
  { id: "NOTIF-003", title: "Payroll selesai", message: "Payroll Oktober 2026 berhasil diproses.", type: "success", read: true, createdAt: "2026-11-01 17:00" },
]

export const auditLogs: AuditLog[] = [
  { id: "AUD-001", actor: "Rio Pratama", action: "Approved leave request", resource: "LV-2026-002", createdAt: "2026-10-29 10:20", ipAddress: "192.168.1.20" },
  { id: "AUD-002", actor: "Admin System", action: "Updated device settings", resource: "ZKTECO-003", createdAt: "2026-10-28 15:42", ipAddress: "192.168.1.10" },
  { id: "AUD-003", actor: "Sari Dewi", action: "Exported attendance logs", resource: "Raw Attendance Logs", createdAt: "2026-10-28 09:15", ipAddress: "192.168.1.21" },
]
