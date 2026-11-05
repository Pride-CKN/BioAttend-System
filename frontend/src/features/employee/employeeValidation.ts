import type { EmployeeFormErrors, EmployeeFormValues } from "@/features/employee/employee.type"
import { COMPANY_DOMAIN, divisionsByDept, positionsByDept } from "@/features/employee/lib/employee.Option"

export const isPlacementValid = (f: Pick<EmployeeFormValues, "department" | "division" | "position">) =>
  !!divisionsByDept[f.department]?.includes(f.division) && !!positionsByDept[f.department]?.includes(f.position)

/** Pure, synchronous rules. PIN uniqueness is async and passed in as `pinTaken`. */
export function validateEmployeeForm(
  f: EmployeeFormValues,
  ctx: { isEdit: boolean; pinTaken: boolean },
): EmployeeFormErrors {
  const e: EmployeeFormErrors = {}
  if (!f.nama.trim()) e.nama = "Nama lengkap wajib diisi"
  if (!/^\d{16}$/.test(f.nik)) e.nik = "NIK harus 16 digit angka"
  if (!f.tempatLahir.trim()) e.tempatLahir = "Tempat, tanggal lahir wajib diisi"
  if (!/^[\d\s+-]{9,16}$/.test(f.hp)) e.hp = "Nomor HP tidak valid"
  if (!f.email.toLowerCase().endsWith(COMPANY_DOMAIN)) e.email = `Gunakan email ${COMPANY_DOMAIN}`
  if (!f.username.trim()) e.username = "Username wajib diisi"
  if (!ctx.isEdit && f.password.length < 8) e.password = "Minimal 8 karakter"
  if (!f.joinDate) e.joinDate = "Tanggal bergabung wajib diisi"
  if (!/^\d{1,10}$/.test(f.pin)) e.pin = "PIN 1–10 digit angka"
  else if (ctx.pinTaken) e.pin = "PIN sudah digunakan karyawan lain"
  if (!isPlacementValid(f)) e.position = "Department, division, dan position tidak konsisten"
  return e
}
