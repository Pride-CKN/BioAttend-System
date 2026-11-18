import type { EmployeeFormErrors, EmployeeFormValues } from "@/features/employee/types/employee.type"

export const isPlacementValid = (f: Pick<EmployeeFormValues, "officeId" | "divisionId">) =>
  Boolean(f.officeId && f.divisionId)

/** Pure, synchronous rules. PIN uniqueness is async and passed in as `pinTaken`. */
export function validateEmployeeForm(
  f: EmployeeFormValues,
  ctx: { isEdit: boolean; pinTaken: boolean },
): EmployeeFormErrors {
  const e: EmployeeFormErrors = {}
  if (!f.full_name.trim()) e.full_name = "Nama lengkap wajib diisi"
  const needsPin = ctx.isEdit || !f.autoGenerateEmployeeCode
  if (needsPin) {
    const cleanPin = f.pin.trim()
    if (!/^\d{6}$/.test(cleanPin)) e.pin = "NIP / PIN harus 6 digit angka"
    else if (ctx.pinTaken) e.pin = "PIN sudah digunakan karyawan lain"
  }
  if (!ctx.isEdit && !f.email.trim()) e.email = "Email wajib diisi untuk membuat akun login"
  if (!isPlacementValid(f)) e.divisionId = "Pilih kantor dan Division"
  return e
}
