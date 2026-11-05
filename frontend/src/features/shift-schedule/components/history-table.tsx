import { Card, CardContent } from "@/components/ui/card"
import type { ScheduleHistory } from "../types"

export function HistoryTable({ history }: { history: ScheduleHistory[] }) {
  return <Card className="overflow-hidden py-0"><div className="border-b px-4 py-3"><h2 className="font-semibold">Riwayat Perubahan</h2><p className="text-xs text-muted-foreground">Aktivitas perubahan shift dan jadwal</p></div><CardContent className="p-0"><div className="overflow-x-auto"><table className="w-full text-sm"><thead className="bg-muted/50 text-left text-xs"><tr><th className="px-4 py-3">Aktivitas</th><th className="px-4 py-3">Target</th><th className="px-4 py-3">Oleh</th><th className="px-4 py-3">Waktu</th></tr></thead><tbody>{history.map((item) => <tr key={item.id} className="border-t"><td className="px-4 py-3 font-medium">{item.action}</td><td className="px-4 py-3">{item.target}</td><td className="px-4 py-3">{item.actor}</td><td className="px-4 py-3 text-muted-foreground">{item.timestamp}</td></tr>)}</tbody></table></div></CardContent></Card>
}
