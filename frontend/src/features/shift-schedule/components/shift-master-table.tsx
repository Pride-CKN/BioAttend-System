import { Copy, Edit, MoreHorizontal } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import type { Shift } from "../types"
import { ShiftStatus } from "./shift-status"

function formatTime(start: string, end: string) {
  return `${start} – ${end}`
}

export function ShiftMasterTable({
  shifts,
  loading,
  query,
  statusFilter,
  onQueryChange,
  onStatusChange,
  onAdd,
  onEdit,
  onDuplicate,
  onToggle,
}: {
  shifts: Shift[]
  loading: boolean
  query: string
  statusFilter: string
  onQueryChange: (value: string) => void
  onStatusChange: (value: string) => void
  onAdd: () => void
  onEdit: (shift: Shift) => void
  onDuplicate: (id: string) => void
  onToggle: (id: string, active: boolean) => void
}) {
  return (
    <Card className="overflow-hidden py-0">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
        <h2 className="font-semibold">Daftar Semua Shift <span className="ml-1 text-xs font-normal text-muted-foreground">{shifts.length} shift kerja</span></h2>
        <Button variant="outline" size="sm" onClick={onAdd}>Assign ke Department</Button>
      </div>
      <CardContent className="flex flex-wrap items-center gap-2 border-b px-3 py-3">
        <Input value={query} onChange={(event) => onQueryChange(event.target.value)} placeholder="Cari nama shift" className="w-full sm:max-w-[260px]" />
        <Select value={statusFilter} onValueChange={(value) => onStatusChange(value ?? "all")}>
          <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
          <SelectContent><SelectItem value="all">Semua Status</SelectItem><SelectItem value="active">Aktif</SelectItem><SelectItem value="inactive">Nonaktif</SelectItem></SelectContent>
        </Select>
      </CardContent>
      <CardContent className="p-0">
        {loading ? (
          <div className="space-y-3 p-5">{Array.from({ length: 5 }).map((_, index) => <Skeleton key={index} className="h-10 w-full" />)}</div>
        ) : shifts.length === 0 ? (
          <div className="p-10 text-center text-sm text-muted-foreground">Tidak ada shift yang sesuai filter.</div>
        ) : (
          <div className="overflow-x-auto">
            <Table>
              <TableHeader><TableRow className="bg-muted/50"><TableHead>Kode</TableHead><TableHead>Nama Shift</TableHead><TableHead>Jam Kerja</TableHead><TableHead>Durasi</TableHead><TableHead>Istirahat</TableHead><TableHead>Toleransi</TableHead><TableHead>Karyawan</TableHead><TableHead>Status</TableHead><TableHead className="w-10" /></TableRow></TableHeader>
              <TableBody>{shifts.map((shift) => (
                <TableRow key={shift.id}>
                  <TableCell><span className="rounded bg-muted px-2 py-1 text-[10px] font-medium">{shift.code}</span></TableCell>
                  <TableCell className="font-medium">{shift.name}{shift.overnight && <span className="ml-1 text-[10px] text-muted-foreground">(malam)</span>}</TableCell>
                  <TableCell>{formatTime(shift.startTime, shift.endTime)}</TableCell>
                  <TableCell>{shift.minimumWorkHours ? `${shift.minimumWorkHours} jam` : "—"}</TableCell>
                  <TableCell>{shift.breakMinutes} menit</TableCell>
                  <TableCell>{shift.toleranceMinutes} menit</TableCell>
                  <TableCell>{shift.assignedEmployees}</TableCell>
                  <TableCell><ShiftStatus active={shift.status === "active"} /></TableCell>
                  <TableCell><DropdownMenu><DropdownMenuTrigger render={<Button variant="ghost" size="icon-sm" aria-label={`Aksi ${shift.name}`} />}><MoreHorizontal /></DropdownMenuTrigger><DropdownMenuContent align="end"><DropdownMenuItem onClick={() => onEdit(shift)}><Edit />Edit shift</DropdownMenuItem><DropdownMenuItem onClick={() => onDuplicate(shift.id)}><Copy />Duplikasi shift</DropdownMenuItem><DropdownMenuItem onClick={() => onToggle(shift.id, shift.status !== "active")}>{shift.status === "active" ? "Nonaktifkan" : "Aktifkan"}</DropdownMenuItem></DropdownMenuContent></DropdownMenu></TableCell>
                </TableRow>
              ))}</TableBody>
            </Table>
          </div>
        )}
      </CardContent>
      <div className="border-t px-4 py-2 text-xs text-muted-foreground">Menampilkan {shifts.length} shift</div>
    </Card>
  )
}
