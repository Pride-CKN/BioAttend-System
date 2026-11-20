import { memo } from "react"
import type { DivisionStats } from "@/features/division/types/division.type"

type Card = { label: string; value: number; hint: string; accent: string }

function StatsCardsBase({ stats }: { stats: DivisionStats }) {
  const cards: Card[] = [
    { label: "Total Division", value: stats.totalDivision, hint: "", accent: "border-l-red-700" },
    { label: "Division Aktif", value: stats.activeDivision, hint: `${stats.inactiveDivision} nonaktif`, accent: "border-l-purple-600" },
    { label: "Total Unit", value: stats.totalUnit, hint: "", accent: "border-l-blue-600" },
    { label: "Unit Aktif", value: stats.activeUnit, hint: `${stats.inactiveUnit} nonaktif`, accent: "border-l-emerald-600" },
  ]
  return (
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      {cards.map((c) => (
        <div key={c.label} className={`rounded-xl border border-l-4 bg-white p-3 shadow-sm ${c.accent}`}>
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
