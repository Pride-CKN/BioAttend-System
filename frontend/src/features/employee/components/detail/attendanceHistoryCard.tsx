import { memo, useState } from "react"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { describeAttendance } from "@/features/employee/lib/attendance"
import { Pagination } from "@/components/shared"
import type { AttendanceRow } from "@/features/employee/employee.type"

const PAGE_SIZE = 8

const Row = memo(function Row({ r }: { r: AttendanceRow }) {
  const d = describeAttendance(r)
  return (
    <TableRow>
      <TableCell className="text-xs">{r.tanggal}</TableCell>
      <TableCell className={`text-xs ${d.late > 30 ? "font-semibold text-red-600" : ""}`}>{r.status}</TableCell>
      <TableCell className="text-xs">{r.masuk ?? "—"}</TableCell>
      <TableCell className="text-xs">{r.keluar ?? "—"}</TableCell>
      <TableCell className="text-xs">{d.durasi}</TableCell>
      <TableCell className="text-xs">{d.note}</TableCell>
    </TableRow>
  )
})

export function AttendanceHistoryCard({
  rows, loading, onDetail,
}: { rows: AttendanceRow[] | undefined; loading: boolean; onDetail: () => void }) {
  const [page, setPage] = useState(1)
  const all = rows ?? []
  const pageCount = Math.max(1, Math.ceil(all.length / PAGE_SIZE))
  const visible = all.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE)

  return (
    <div className="self-start overflow-hidden rounded-lg border bg-white">
      <div className="flex items-baseline gap-2 border-b px-4 py-3">
        <h3 className="text-sm font-bold">Riwayat Absensi — 30 Hari Terakhir</h3>
        <button onClick={onDetail} className="text-[11px] text-muted-foreground hover:underline">Detail</button>
      </div>
      <Table>
        <TableHeader className="bg-slate-50">
          <TableRow>
            {["Tanggal", "Status", "Masuk", "Keluar", "Durasi", "Catatan"].map((h) => (
              <TableHead key={h} className="text-[11px] uppercase">{h}</TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {loading && !rows && (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-xs text-muted-foreground">Memuat…</TableCell></TableRow>
          )}
          {visible.map((r) => <Row key={r.tanggal} r={r} />)}
        </TableBody>
      </Table>
      <div className="flex items-center justify-between border-t px-4 py-3 text-xs text-muted-foreground">
        <span>{visible.length} dari {all.length} hari</span>
        <Pagination page={page} pageCount={pageCount} onChange={setPage} />
      </div>
    </div>
  )
}
