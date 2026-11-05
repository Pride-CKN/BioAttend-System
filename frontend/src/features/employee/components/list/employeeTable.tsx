import { memo } from "react"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { EmployeeAvatar, RowActions, StatusBadge, type RowActionHandlers } from "@/components/shared"
import type { Employee } from "@/features/employee/employee.type"

const EmployeeRow = memo(function EmployeeRow({
  emp, selected, onToggle, actions,
}: { emp: Employee; selected: boolean; onToggle: (id: string) => void; actions: RowActionHandlers }) {
  return (
    <TableRow data-state={selected ? "selected" : undefined}>
      <TableCell className="pl-4">
        <input type="checkbox" checked={selected} onChange={() => onToggle(emp.id)} aria-label={`Pilih ${emp.name}`} />
      </TableCell>
      <TableCell>
        <div className="flex items-center gap-3">
          <EmployeeAvatar emp={emp} />
          <div>
            <button onClick={() => actions.onView(emp)} className="text-left text-sm font-semibold hover:text-red-700 hover:underline">
              {emp.name}
            </button>
            <div className="text-xs text-muted-foreground">ID {emp.id} · {emp.unit}</div>
          </div>
        </div>
      </TableCell>
      <TableCell><StatusBadge status={emp.status} /></TableCell>
      <TableCell>{emp.dept}</TableCell>
      <TableCell>{emp.position}</TableCell>
      <TableCell>{emp.shift}</TableCell>
      <TableCell>{emp.absensi}</TableCell>
      <TableCell>{emp.jamKerja || "—"}</TableCell>
      <TableCell>{emp.potongan}</TableCell>
      <TableCell className="pr-4"><RowActions emp={emp} {...actions} /></TableCell>
    </TableRow>
  )
})

type Props = {
  items: Employee[]
  selected: ReadonlySet<string>
  allSelected: boolean
  onToggleAll: () => void
  onToggle: (id: string) => void
  actions: RowActionHandlers
}

export function EmployeeTable({ items, selected, allSelected, onToggleAll, onToggle, actions }: Props) {
  return (
    <Table>
      <TableHeader className="bg-slate-50">
        <TableRow>
          <TableHead className="w-10 pl-4">
            <input type="checkbox" checked={allSelected} onChange={onToggleAll} aria-label="Pilih semua" />
          </TableHead>
          {["Karyawan", "Status", "Departemen", "Position", "Shift", "Absensi Terakhir", "Jam Kerja", "Potongan"].map((h) => (
            <TableHead key={h} className="text-xs uppercase">{h}</TableHead>
          ))}
          <TableHead className="pr-4 text-right text-xs uppercase">Action</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {items.length === 0 && (
          <TableRow>
            <TableCell colSpan={10} className="py-8 text-center text-muted-foreground">Tidak ada data karyawan.</TableCell>
          </TableRow>
        )}
        {items.map((emp) => (
          <EmployeeRow key={emp.id} emp={emp} selected={selected.has(emp.id)} onToggle={onToggle} actions={actions} />
        ))}
      </TableBody>
    </Table>
  )
}
