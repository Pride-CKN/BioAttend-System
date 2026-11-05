import { memo } from "react"
import { pct } from "@/features/employee/lib/format"
import type { EmployeeStats } from "@/features/employee/employee.type"

const nf = new Intl.NumberFormat("id-ID")

export const EmployeeStatCards = memo(function EmployeeStatCards({ stats }: { stats?: EmployeeStats }) {
  const s = stats
  const cards = [
    { label: "Total Karyawan", value: s?.total, note: s ? `+${s.newThisMonth} bulan ini` : "", border: "border-l-[#EA232A]", noteClass: "text-emerald-600" },
    { label: "Aktif", value: s?.active, note: s ? pct(s.active, s.total) : "", border: "border-l-[#16A34A]" },
    { label: "Cuti / Izin", value: s?.leave, note: s ? pct(s.leave, s.total) : "", border: "border-l-[#2563EB]" },
    { label: "Nonaktif", value: s?.inactive, note: s ? pct(s.inactive, s.total) : "", border: "border-l-[#94A3B8]" },
    { label: "Belum Absen", value: s?.notCheckedIn, note: "Perlu follow-up HRD", border: "border-l-[#F59E0B]" },
  ]
  return (
    <div className="grid grid-cols-2 gap-3 @3xl:grid-cols-5">
      {cards.map((c) => (
        <div key={c.label} className={`rounded-lg border border-l-4 bg-white p-3 ${c.border}`}>
          <p className="text-xs text-muted-foreground">{c.label}</p>
          <p className="min-h-8 text-2xl font-bold">{c.value === undefined ? "—" : nf.format(c.value)}</p>
          <p className={`min-h-4 text-xs ${c.noteClass ?? "text-muted-foreground"}`}>{c.note}</p>
        </div>
      ))}
    </div>
  )
})
