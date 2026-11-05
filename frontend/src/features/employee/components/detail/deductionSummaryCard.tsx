import { summarizeDeduction } from "@/features/employee/lib/attendance"
import { Chip } from "@/features/employee/components/detail/profileSection"
import type { DeductionHistoryItem } from "@/features/employee/employee.type"

export function DeductionSummaryCard({
  potongan, history,
}: { potongan: string; history: DeductionHistoryItem[] | undefined }) {
  const s = summarizeDeduction(potongan)
  const rows: [string, string][] = [
    ["Total event", `${s.total} event`],
    ["Event ditoleransi", `${s.tolerated} event`],
    ["Event terhitung", `${s.counted} event`],
    ["Persentase", potongan],
  ]
  return (
    <div className="self-start overflow-hidden rounded-lg border bg-white">
      <div className="flex items-baseline gap-2 border-b px-4 py-3">
        <h3 className="text-sm font-bold">Ringkasan Deduction</h3>
        <span className="text-[11px] text-muted-foreground">Oktober 2026</span>
      </div>
      <div className="space-y-4 p-4">
        <div className="flex items-start justify-between">
          <div>
            <p className="text-xs text-muted-foreground">Persentase Potongan</p>
            <p className="text-3xl font-bold text-red-700">{potongan}</p>
          </div>
          <div className="text-right">
            <p className="text-xs text-muted-foreground">Status</p>
            <Chip className="border-slate-200 bg-slate-100 text-slate-600">Draft</Chip>
          </div>
        </div>
        <dl className="divide-y text-xs">
          {rows.map(([k, v]) => (
            <div key={k} className="flex py-2">
              <dt className="w-32 text-muted-foreground">{k}</dt>
              <dd>{v}</dd>
            </div>
          ))}
        </dl>
        <div>
          <p className="mb-1 text-xs text-muted-foreground">Riwayat deduction</p>
          <ul className="divide-y text-xs">
            {(history ?? []).map((h) => (
              <li key={h.bulan} className="flex justify-between py-2.5">
                <span>{h.bulan}</span>
                <span className={`font-bold ${h.warn ? "text-red-600" : ""}`}>{h.persen}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  )
}
