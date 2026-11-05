import type { AttendanceRow } from "@/features/employee/employee.type"
import { toMinutes } from "@/features/employee/lib/format"

export function describeAttendance(row: AttendanceRow) {
  if (!row.masuk || !row.keluar) return { durasi: "—", late: 0, note: row.catatan ?? "—" }
  const total = toMinutes(row.keluar) - toMinutes(row.masuk)
  const late = Math.max(0, toMinutes(row.masuk) - 8 * 60)
  return {
    durasi: `${Math.floor(total / 60)}j ${String(total % 60).padStart(2, "0")}m`,
    late,
    note: late > 0 ? `Terlambat ${late}m` : row.catatan ?? "—",
  }
}

export function summarizeDeduction(potongan: string) {
  const percent = parseInt(potongan, 10) || 0
  const counted = Math.round(percent / 2)
  const tolerated = 2
  return { percent, counted, tolerated, total: counted + tolerated }
}
