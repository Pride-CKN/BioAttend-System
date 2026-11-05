import { memo } from "react"
import { EmployeeAvatar, RowActions, StatusBadge, type RowActionHandlers } from "@/components/shared"
import type { Employee } from "@/features/employee/employee.type"

const EmployeeCard = memo(function EmployeeCard({ emp, actions }: { emp: Employee; actions: RowActionHandlers }) {
  return (
    <div className="space-y-3 rounded-lg border p-3">
      <div className="flex items-center gap-3">
        <EmployeeAvatar emp={emp} />
        <div className="min-w-0 flex-1">
          <div className="truncate text-sm font-semibold">{emp.name}</div>
          <div className="text-xs text-muted-foreground">ID {emp.id} · {emp.unit}</div>
        </div>
      </div>
      <div className="flex items-center justify-between text-xs">
        <StatusBadge status={emp.status} />
        <span className="text-muted-foreground">{emp.shift}</span>
      </div>
      <div className="text-xs text-muted-foreground">{emp.position} · {emp.dept}</div>
      <RowActions emp={emp} {...actions} />
    </div>
  )
})

export function EmployeeCardGrid({ items, actions }: { items: Employee[]; actions: RowActionHandlers }) {
  if (items.length === 0) return <p className="py-8 text-center text-sm text-muted-foreground">Tidak ada data karyawan.</p>
  return (
    <div className="grid gap-3 p-4 @xl:grid-cols-2 @4xl:grid-cols-4">
      {items.map((emp) => <EmployeeCard key={emp.id} emp={emp} actions={actions} />)}
    </div>
  )
}
