
import { memo } from "react"
import {
  Card,
  CardContent,
} from "@/components/ui/card"
import { pct } from "@/lib/format"
import type { EmployeeStats } from "@/features/employee/types/employee.type"

const nf = new Intl.NumberFormat("id-ID")

export const EmployeeStatCards = memo(function EmployeeStatCards({
  stats,
}: {
  stats?: EmployeeStats
}) {
  const s = stats

  const cards = [
    {
      label: "Total Karyawan",
      value: s?.total,
      note: s ? `+${s.newThisMonth} bulan ini` : "",
      border: "border-l-[#EA232A]",
      noteClass: "text-emerald-600",
    },
    {
      label: "Aktif",
      value: s?.active,
      note: s ? pct(s.active, s.total) : "",
      border: "border-l-[#16A34A]",
    },
    {
      label: "Nonaktif",
      value: s?.inactive,
      note: s ? pct(s.inactive, s.total) : "",
      border: "border-l-[#94A3B8]",
    },
    {
      label: "Resigned",
      value: s?.resigned,
      note: s ? pct(s.resigned, s.total) : "",
      border: "border-l-[#64748B]",
    },
    {
      label: "Karyawan Baru",
      value: s?.newThisMonth,
      note: "Bulan ini",
      border: "border-l-[#F59E0B]",
    },
  ]

  return (
    <div className="grid grid-cols-2 gap-3 @3xl:grid-cols-5">
      {cards.map((card) => (
        <Card
          key={card.label}
          className={`rounded-lg border border-l-4 bg-white shadow-none ${card.border}`}
        >
          <CardContent className="p-3">
            <p className="text-xs text-muted-foreground">
              {card.label}
            </p>

            <p className="min-h-8 text-2xl font-bold tabular-nums">
              {card.value === undefined
                ? "—"
                : nf.format(card.value)}
            </p>

            <p
              className={`min-h-4 text-xs ${
                card.noteClass ?? "text-muted-foreground"
              }`}
            >
              {card.note}
            </p>
          </CardContent>
        </Card>
      ))}
    </div>
  )
})
