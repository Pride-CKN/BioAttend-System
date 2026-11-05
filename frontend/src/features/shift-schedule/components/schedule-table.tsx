import { Card, CardContent } from "@/components/ui/card"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { ShiftStatus } from "./shift-status"
import type { EmployeeSchedule, Shift } from "../types"

function formatDate(value: string) {
  return new Date(value).toLocaleDateString("id-ID", { weekday: "short", day: "2-digit", month: "short", year: "numeric" })
}

export function ScheduleTable({
  schedules,
  shifts,
  loading,
}: {
  schedules: EmployeeSchedule[]
  shifts: Shift[]
  loading: boolean
}) {
  return (
    <Card className="overflow-hidden py-0">
      <div className="border-b px-4 py-3">
        <h2 className="font-semibold">Jadwal Kerja Karyawan <span className="ml-1 text-xs font-normal text-muted-foreground">{schedules.length} karyawan terjadwal</span></h2>
      </div>
      <CardContent className="p-0">
        {loading ? <div className="p-5 text-sm text-muted-foreground">Memuat jadwal...</div> : schedules.length === 0 ? <div className="p-10 text-center text-sm text-muted-foreground">Belum ada jadwal pada tanggal ini.</div> : <div className="overflow-x-auto"><Table><TableHeader><TableRow className="bg-muted/50"><TableHead>Karyawan</TableHead><TableHead>Shift</TableHead><TableHead>Tanggal</TableHead><TableHead>Jam Kerja</TableHead><TableHead>Department</TableHead><TableHead>Status</TableHead><TableHead>Catatan</TableHead></TableRow></TableHeader><TableBody>{schedules.map((item) => { const shift = shifts.find((value) => value.id === item.shiftId); return <TableRow key={item.id}><TableCell><div className="flex items-center gap-2"><span className="flex size-7 items-center justify-center rounded-full bg-[var(--brand-red)]/10 text-[10px] font-semibold text-[var(--brand-red)]">{item.initials}</span><div><p className="font-medium">{item.employee}</p><p className="text-[10px] text-muted-foreground">{item.department} · {item.scope}</p></div></div></TableCell><TableCell>{shift?.name ?? "-"}</TableCell><TableCell><p>{formatDate(item.date)}</p><p className="text-[10px] text-muted-foreground">{item.validFrom}{item.validTo ? ` - ${item.validTo}` : " - tanpa batas"}</p></TableCell><TableCell>{shift ? `${shift.startTime} – ${shift.endTime}` : "-"}</TableCell><TableCell>{item.department}</TableCell><TableCell><ShiftStatus active={item.status === "active"} /></TableCell><TableCell className="max-w-40 truncate text-xs text-muted-foreground">{item.note ?? "-"}</TableCell></TableRow> })}</TableBody></Table></div>}
      </CardContent>
      <div className="border-t px-4 py-2 text-xs text-muted-foreground">Menampilkan {schedules.length} jadwal</div>
    </Card>
  )
}
