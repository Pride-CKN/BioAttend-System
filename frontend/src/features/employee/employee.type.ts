import type { Employee, getProfile } from "@/seeds/employee"

export type { Employee }
export type EmployeeProfile = ReturnType<typeof getProfile>

export type EmployeeFilters = {
  keyword: string
  dept: string
  unit: string
  status: string
  shift: string
}

export type EmployeeQuery = EmployeeFilters & { page: number; pageSize: number }

export type Page<T> = {
  items: T[]
  total: number
  page: number
  pageSize: number
  pageCount: number
}

export type EmployeeStats = {
  total: number
  active: number
  leave: number
  inactive: number
  notCheckedIn: number
  newThisMonth: number
}

export type AttendanceRow = {
  tanggal: string
  status: "Hadir" | "Cuti" | "Izin" | "Tidak Masuk"
  masuk?: string
  keluar?: string
  catatan?: string
}

export type DeductionHistoryItem = { bulan: string; persen: string; warn: boolean }

export type EmployeeDetail = { employee: Employee; profile: EmployeeProfile }

export type EmployeeFormValues = {
  nama: string
  nik: string
  tempatLahir: string
  jenisKelamin: string
  golonganDarah: string
  alamat: string
  hp: string
  email: string
  username: string
  role: string
  password: string
  department: string
  division: string
  position: string
  statusKepegawaian: string
  joinDate: string
  supervisor: string
  pin: string
  shift: string
  device: string
  toleransi: string
  wajibFingerprint: boolean
  presensiManual: boolean
}

export type EmployeeFormErrors = Partial<Record<keyof EmployeeFormValues, string>>

export type BulkAction = "aktifkan" | "nonaktifkan" | "export" | "hapus"
