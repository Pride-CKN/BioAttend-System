import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import type { Shift } from "../types"

export function ScheduleFilters({
  query,
  date,
  shiftId,
  department,
  shifts,
  onQueryChange,
  onDateChange,
  onShiftChange,
  onDepartmentChange,
}: {
  query: string
  date: string
  shiftId: string
  department: string
  shifts: Shift[]
  onQueryChange: (value: string) => void
  onDateChange: (value: string) => void
  onShiftChange: (value: string) => void
  onDepartmentChange: (value: string) => void
}) {
  return (
    <div className="flex flex-wrap items-center gap-2 border-b px-3 py-3">
      <Input value={query} onChange={(event) => onQueryChange(event.target.value)} placeholder="Cari karyawan" className="w-full sm:max-w-[220px]" />
      <label className="flex items-center gap-2 text-xs text-muted-foreground">
        Tanggal
        <Input type="date" value={date} onChange={(event) => onDateChange(event.target.value)} className="w-36" />
      </label>
      <Select value={shiftId} onValueChange={(value) => onShiftChange(value ?? "all")}>
        <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
        <SelectContent><SelectItem value="all">Semua Shift</SelectItem>{shifts.map((shift) => <SelectItem key={shift.id} value={shift.id}>{shift.name}</SelectItem>)}</SelectContent>
      </Select>
      <Select value={department} onValueChange={(value) => onDepartmentChange(value ?? "all")}>
        <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
        <SelectContent><SelectItem value="all">Semua Departemen</SelectItem><SelectItem value="Produksi">Produksi</SelectItem><SelectItem value="Operasional">Operasional</SelectItem><SelectItem value="Quality Control">Quality Control</SelectItem><SelectItem value="Maintenance">Maintenance</SelectItem></SelectContent>
      </Select>
    </div>
  )
}
