
import { memo } from "react"

import {
  Card,
  CardContent,
  CardHeader,
} from "@/components/ui/card"

import {
  EmployeeAvatar,
  RowActions,
  StatusBadge,
  type RowActionHandlers,
} from "@/components/shared"
import type { Employee } from "@/features/employee/types/employee.type"

const EmployeeCard = memo(function EmployeeCard({
  emp,
  actions,
}: {
  emp: Employee
  actions: RowActionHandlers
}) {
  return (
    <Card className="rounded-lg shadow-none">
      <CardHeader className="flex flex-row items-center gap-3 space-y-0 p-3 pb-2">
        <EmployeeAvatar emp={emp} />

        <div className="min-w-0 flex-1">
          <div className="truncate text-sm font-semibold">
            {emp.full_name}
          </div>
          <div className="text-xs text-muted-foreground">
            {emp.employeeCode ? ` ` : ""}NIP {emp.nip ?? "—"}
          </div>
        </div>
      </CardHeader>

      <CardContent className="space-y-3 p-3 pt-1">
        <div className="flex items-center justify-between text-xs">
          <StatusBadge status={emp.status} />
          <span className="text-muted-foreground">{emp.officeName ?? "—"}</span>
        </div>

        <div className="text-xs text-muted-foreground">
          {emp.position} · {emp.division}
        </div>

        <div className="border-t pt-3">
          <RowActions emp={emp} {...actions} />
        </div>
      </CardContent>
    </Card>
  )
})

export function EmployeeCardGrid({
  items,
  actions,
}: {
  items: Employee[]
  actions: RowActionHandlers
}) {
  if (items.length === 0) {
    return (
      <Card className="shadow-none">
        <CardContent className="py-8 text-center text-sm text-muted-foreground">
          Tidak ada data karyawan.
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="grid grid-cols-1 gap-3 @xl:grid-cols-2 @4xl:grid-cols-4">
      {items.map((emp) => (
        <EmployeeCard
          key={emp.id}
          emp={emp}
          actions={actions}
        />
      ))}
    </div>
  )
}
