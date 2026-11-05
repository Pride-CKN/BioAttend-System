import { memo } from "react"
import type { DepartmentStats } from "@/features/department/department.type"

type Card = { label: string; value: number; hint: string; accent: string }

function StatsCardsBase({ stats }: { stats: DepartmentStats }) {
  const cards: Card[] = [
    { label: "Total Divisi", value: stats.totalDepartment, hint: `${stats.activeDepartment} aktif`, accent: "border-l-red-700" },
    { label: "Total Unit", value: stats.totalDivision, hint: stats.divisionNames, accent: "border-l-blue-600" },
    { label: "Aktif", value: stats.activeDepartment, hint: `${stats.totalDepartment - stats.activeDepartment} nonaktif`, accent: "border-l-purple-600" },
    { label: "Karyawan Ter-assign", value: stats.totalEmployee, hint: "Total di semua divisi", accent: "border-l-emerald-600" },
  ]
  return (
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      {cards.map((c) => (
        <div key={c.label} className={`rounded-lg border border-l-4 bg-white p-3 ${c.accent}`}>
          <p className="text-xs text-muted-foreground">{c.label}</p>
          <p className="text-2xl font-bold">{c.value}</p>
          <p className="truncate text-xs text-muted-foreground">{c.hint}</p>
        </div>
      ))}
    </div>
  )
}

// Props = snapshot store yang referensinya stabil → tidak re-render saat filter/halaman berubah.
export const StatsCards = memo(StatsCardsBase)
