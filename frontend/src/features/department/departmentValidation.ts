import type { DepartmentInput } from "@/features/department/department.type"

export type DepartmentErrors = Partial<Record<keyof DepartmentInput, string>>

export function validateDepartment(v: DepartmentInput): DepartmentErrors {
  const errors: DepartmentErrors = {}
  if (!/^[A-Z]{2,5}-\d{2,}$/.test(v.code)) errors.code = "Format kode: PRD-01"
  if (v.name.trim().length < 3) errors.name = "Nama minimal 3 karakter"
  if (!v.division) errors.division = "Pilih division"
  return errors
}
