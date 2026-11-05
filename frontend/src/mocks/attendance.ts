export type AttendanceRecap = {
  employeePin: string
  employee: string
  department: string
  period: string
  present: number
  late: number
  absent: number
  leave: number
  attendanceRate: number
}

export type ShiftSchedule = {
  id: string
  name: string
  startTime: string
  endTime: string
  breakMinutes: number
  assignedEmployees: number
  active: boolean
}

export const attendanceRecaps: AttendanceRecap[] = [
  { employeePin: "1182", employee: "Andi Saputra", department: "Information Technology", period: "October 2026", present: 21, late: 2, absent: 0, leave: 1, attendanceRate: 95.5 },
  { employeePin: "1067", employee: "Eka Wulandari", department: "Human Resources", period: "October 2026", present: 20, late: 1, absent: 1, leave: 2, attendanceRate: 90.9 },
  { employeePin: "1204", employee: "Budi Santoso", department: "Operations", period: "October 2026", present: 22, late: 4, absent: 0, leave: 0, attendanceRate: 100 },
  { employeePin: "1311", employee: "Dewi Lestari", department: "Finance", period: "October 2026", present: 19, late: 0, absent: 2, leave: 3, attendanceRate: 86.4 },
]

export const attendanceCorrections: AttendanceCorrection[] = [
  { id: "COR-2026-001", employee: "Andi Saputra", date: "2026-10-30", requestedType: "Check-Out", originalValue: "Tidak ada scan", correctedValue: "17:30", reason: "Lupa scan saat pulang", requester: "Andi Saputra", status: "pending", submittedAt: "2026-10-31 08:10" },
  { id: "COR-2026-002", employee: "Eka Wulandari", date: "2026-10-29", requestedType: "Check-In", originalValue: "Tidak ada scan", correctedValue: "08:05", reason: "Perangkat sedang offline", requester: "Eka Wulandari", status: "approved", submittedAt: "2026-10-30 09:00" },
  { id: "COR-2026-003", employee: "Budi Santoso", date: "2026-10-28", requestedType: "Check-Out", originalValue: "17:00", correctedValue: "19:00", reason: "Dinas luar kantor", requester: "Budi Santoso", status: "rejected", submittedAt: "2026-10-29 13:20" },
  { id: "COR-2026-004", employee: "Citra Lestari", date: "2026-10-27", requestedType: "Status Tidak Masuk", originalValue: "Absent", correctedValue: "Cuti", reason: "Cuti telah disetujui", requester: "HRD", status: "approved", submittedAt: "2026-10-28 10:15" },
  { id: "COR-2026-005", employee: "Dimas Pratama", date: "2026-10-26", requestedType: "Check-In", originalValue: "09:12", correctedValue: "08:00", reason: "Jam masuk tidak tercatat karena kendala mesin", requester: "Dimas Pratama", status: "pending", submittedAt: "2026-10-27 08:25" },
  { id: "COR-2026-006", employee: "Sari Melati", date: "2026-10-25", requestedType: "Check-Out", originalValue: "Tidak ada scan", correctedValue: "16:45", reason: "Lupa scan sebelum mengikuti briefing", requester: "Sari Melati", status: "approved", submittedAt: "2026-10-26 09:40" },
  { id: "COR-2026-007", employee: "Fajar Hidayat", date: "2026-10-24", requestedType: "Status Tidak Masuk", originalValue: "Absent", correctedValue: "Dinas", reason: "Penugasan ke outlet cabang", requester: "Supervisor Operasional", status: "rejected", submittedAt: "2026-10-25 14:05" },
  { id: "COR-2026-008", employee: "Nina Kurnia", date: "2026-10-23", requestedType: "Check-In", originalValue: "Tidak ada scan", correctedValue: "07:55", reason: "Sidik jari gagal terbaca", requester: "Nina Kurnia", status: "pending", submittedAt: "2026-10-24 08:00" },
  { id: "COR-2026-009", employee: "Rizky Maulana", date: "2026-10-22", requestedType: "Check-Out", originalValue: "18:10", correctedValue: "17:10", reason: "Data scan tercatat pada mesin berbeda", requester: "Rizky Maulana", status: "approved", submittedAt: "2026-10-23 11:30" },
  { id: "COR-2026-010", employee: "Lia Anggraini", date: "2026-10-21", requestedType: "Status Tidak Masuk", originalValue: "Absent", correctedValue: "Sakit", reason: "Surat keterangan sakit telah dilampirkan", requester: "Lia Anggraini", status: "approved", submittedAt: "2026-10-22 10:20" },
  { id: "COR-2026-011", employee: "Yoga Permana", date: "2026-10-20", requestedType: "Check-In", originalValue: "08:45", correctedValue: "08:15", reason: "Koneksi mesin absensi terputus", requester: "Yoga Permana", status: "pending", submittedAt: "2026-10-21 09:10" },
  { id: "COR-2026-012", employee: "Maya Salsabila", date: "2026-10-19", requestedType: "Check-Out", originalValue: "Tidak ada scan", correctedValue: "17:00", reason: "Aplikasi absensi mengalami gangguan", requester: "Maya Salsabila", status: "rejected", submittedAt: "2026-10-20 12:45" },
  { id: "COR-2026-013", employee: "Arif Nugroho", date: "2026-10-18", requestedType: "Check-In", originalValue: "10:05", correctedValue: "08:10", reason: "Menghadiri meeting di lokasi klien", requester: "Arif Nugroho", status: "approved", submittedAt: "2026-10-19 15:00" },
  { id: "COR-2026-014", employee: "Putri Ayuningtyas", date: "2026-10-17", requestedType: "Status Tidak Masuk", originalValue: "Absent", correctedValue: "Cuti", reason: "Pengajuan cuti sudah disetujui atasan", requester: "Putri Ayuningtyas", status: "pending", submittedAt: "2026-10-18 08:35" },
  { id: "COR-2026-015", employee: "Gilang Ramadhan", date: "2026-10-16", requestedType: "Check-Out", originalValue: "16:20", correctedValue: "17:00", reason: "Scan pulang menggunakan mesin lantai dua", requester: "Gilang Ramadhan", status: "approved", submittedAt: "2026-10-17 09:50" },
]

export const shiftSchedules: ShiftSchedule[] = [
  { id: "SHIFT-REG", name: "Regular Office", startTime: "08:00", endTime: "17:00", breakMinutes: 60, assignedEmployees: 18, active: true },
  { id: "SHIFT-MORNING", name: "Morning Outlet", startTime: "07:00", endTime: "16:00", breakMinutes: 60, assignedEmployees: 14, active: true },
  { id: "SHIFT-EVENING", name: "Evening Outlet", startTime: "14:00", endTime: "23:00", breakMinutes: 60, assignedEmployees: 12, active: true },
]
import type { AttendanceCorrection } from "@/features/attendance-correction/types"
