
import { memo } from "react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Card,
  CardContent,
} from "@/components/ui/card"

import {
  initials,
  primaryBtn,
  statusClass,
} from "@/lib/format"
import type { EmployeeDetail } from "@/features/employee/types/employee.type"

export function Chip({
  className,
  children,
}: {
  className: string
  children: React.ReactNode
}) {
  return (
    <Badge
      variant="outline"
      className={`gap-1 rounded-full px-2 py-0 text-[10px] font-medium ${className}`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {children}
    </Badge>
  )
}

export function ProfileCard({
  detail,
  onEdit,
}: {
  detail: EmployeeDetail
  onEdit: () => void
}) {
  const { employee, profile: p } = detail

  return (
    <Card className="rounded-lg shadow-none">
      <CardContent className="flex flex-wrap items-start gap-4 p-4 sm:p-5">
        {/* Avatar */}
        <div className="flex size-16 shrink-0 items-center justify-center rounded-xl bg-red-700 text-xl font-bold text-white">
          {initials(employee.full_name)}
        </div>

        {/* Employee information */}
        <div className="min-w-0 flex-1 space-y-2">
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold">
              {employee.full_name}
            </h2>

            <Chip className={statusClass[employee.status] ?? ""}>
              {employee.status}
            </Chip>

            <Chip className="border-blue-200 bg-blue-50 text-blue-700">{employee.division}</Chip>
          </div>

          <p className="text-xs text-muted-foreground">Employee ID: {employee.employeeCode ?? "—"} · NIP / PIN: {employee.nip ?? "—"} · Bergabung sejak {employee.joinDate ?? "—"}</p>

          <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <span>✉️ {p.email}</span>
            <span>📞 {p.hp || "—"}</span>
          </div>
        </div>

        {/* Actions */}
        <div className="flex flex-wrap items-center gap-2">
          <Button size="sm" variant="outline" asChild>
            <a href={`mailto:${p.email}`}>
              Kirim Email
            </a>
          </Button>

          <Button
            size="sm"
            variant="outline"
            onClick={onEdit}
          >
            Edit
          </Button>

          <Button
            size="sm"
            variant="outline"
            aria-label="Menu lainnya"
          >
            ⋮
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
