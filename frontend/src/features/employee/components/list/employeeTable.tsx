
import { memo } from "react"

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Card,
  CardContent,
} from "@/components/ui/card"
import {
  EmployeeAvatar,
  RowActions,
  StatusBadge,
  type RowActionHandlers,
} from "@/components/shared"
import type { Employee } from "@/features/employee/types/employee.type"

const EmployeeRow = memo(function EmployeeRow({
  emp,
  selected,
  onToggle,
  actions,
}: {
  emp: Employee
  selected: boolean
  onToggle: (id: string) => void
  actions: RowActionHandlers
}) {
  return (
    <TableRow data-state={selected ? "selected" : undefined}>
      <TableCell className="w-10 pl-4">
        <input
         
          type="checkbox"
         
          checked={selected}
         
          onChange={() => onToggle(emp.id)}
          aria-label={`Pilih ${emp.full_name}`}
        />
      </TableCell>

      <TableCell className="w-[19%] max-w-0 px-2 whitespace-normal">
        <div className="flex min-w-0 items-center gap-3">
          <EmployeeAvatar emp={emp} />
          <div className="min-w-0">
            <button
             
              onClick={() => actions.onView(emp)}
              className="block max-w-full whitespace-normal wrap-break-word text-left text-sm font-semibold hover:text-red-700 hover:underline"
            >
              {emp.full_name}
            </button>
            <div className="text-xs text-muted-foreground">
              {emp.employeeCode ? `ID ${emp.employeeCode} · ` : "000"}
            </div>
          </div>
        </div>
      </TableCell>

      <TableCell className="w-[9%] px-2">
        <StatusBadge status={emp.status} />
      </TableCell>

      <TableCell className="w-[11%] px-2 text-muted-foreground">
        {emp.division}
      </TableCell>

      <TableCell className="w-[10%] px-2 text-muted-foreground">
        {emp.position}
      </TableCell>

      <TableCell className="w-[16%] px-2 text-muted-foreground">
        {emp.officeName ?? "—"}
      </TableCell>

      <TableCell className="w-[13%] px-2 text-muted-foreground">
        {emp.unit}
      </TableCell>

      <TableCell className="w-[13%] px-1 text-center">
        <div className="flex items-center justify-center">
          <RowActions emp={emp} {...actions} />
        </div>
      </TableCell>
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

export function EmployeeTable({
  items,
  selected,
  allSelected,
  onToggleAll,
  onToggle,
  actions,
}: Props) {
  return (
    <Card className="overflow-hidden rounded-lg shadow-none">
      <CardContent className="p-0">
        <div className="w-full">
          <Table
            className="w-full table-fixed"
            containerClassName="relative w-full overflow-hidden"
          >
            <TableHeader className="bg-muted/50">
              <TableRow>
                <TableHead className="w-10 pl-4">
                  <input
                    type="checkbox"
                    checked={allSelected}
                    onChange={onToggleAll}
                    aria-label="Pilih semua"
                  />
                </TableHead>

                {[
                  ["Karyawan", "w-[19%]"],
                  ["Status", "w-[9%]"],
                  ["Divisi", "w-[11%]"],
                  ["Position", "w-[10%]"],
                  ["Kantor", "w-[16%]"],
                  ["Unit", "w-[13%]"],
                ].map(([h, width]) => (
                  <TableHead
                    key={h}
                    className={`${width} text-xs uppercase`}
                  >
                    {h}
                  </TableHead>
                ))}

                <TableHead className="w-[13%] text-center text-xs uppercase">
                  Action
                </TableHead>
              </TableRow>
            </TableHeader>

            <TableBody>
              {items.length === 0 && (
                <TableRow>
                  <TableCell
                    colSpan={8}
                    className="py-8 text-center text-muted-foreground"
                  >
                    Tidak ada data karyawan.
                  </TableCell>
                </TableRow>
              )}

              {items.map((emp) => (
                <EmployeeRow
                  key={emp.id}
                  emp={emp}
                  selected={selected.has(emp.id)}
                  onToggle={onToggle}
                  actions={actions}
                />
              ))}
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>
  )
}
