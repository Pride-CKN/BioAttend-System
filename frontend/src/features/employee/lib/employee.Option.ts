export {
  bloodTypes,
  departments,
  devices,
  divisionsByDept,
  employmentStatuses,
  genders,
  positionsByDept,
  roles,
  shiftHours,
  shifts,
  supervisors,
} from "@/seeds/employee"

export const FILTER_DIVISIONS = ["Produksi", "Operasional", "QC", "Logistik", "Maintenance"]
export const FILTER_DEPARTMENTS = ["Produksi", "Operasional", "Quality Control", "Logistik", "Maintenance"]
export const FILTER_STATUSES = ["Aktif", "Izin", "Tidak Masuk", "Nonaktif"]
export const FILTER_SHIFTS = ["Shift Pagi", "Shift Siang", "Shift Malam"]
export const COMPANY_DOMAIN = "@pridechicken.co.id"
export const PAGE_SIZE = 20