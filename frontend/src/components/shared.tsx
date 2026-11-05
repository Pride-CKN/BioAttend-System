import { memo } from "react"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { initials, pageWindow, primaryBtn, statusClass } from "@/features/employee/lib/format"
import type { Employee } from "@/features/employee/employee.type"

export const StatusBadge = memo(function StatusBadge({ status }: { status: string }) {
  // Keep status presentation consistent across employee, attendance, and correction tables.
  const statusText = status === "valid" ? "Valid" : status === "duplicate" ? "Duplicate" : status === "error" ? "Error" : status
  return (
    <Badge variant="outline" className={`gap-1.5 rounded-full px-2 py-0.5 font-medium ${statusClass[status] ?? ""}`}>
      <span className="size-1.5 rounded-full bg-current" />
      {statusText}
    </Badge>
  )
})

export const EmployeeAvatar = memo(function EmployeeAvatar({ emp }: { emp: Employee }) {
  return (
    <Avatar className="size-8">
      <AvatarImage src={emp.avatar} alt={emp.name} loading="lazy" />
      <AvatarFallback className={`${emp.color} text-xs font-semibold text-white`}>{initials(emp.name)}</AvatarFallback>
    </Avatar>
  )
})

export type RowActionHandlers = {
  onView: (e: Employee) => void
  onEdit: (e: Employee) => void
  onDelete: (e: Employee) => void
}

export const RowActions = memo(function RowActions({ emp, onView, onEdit, onDelete }: { emp: Employee } & RowActionHandlers) {
  return (
    <div className="flex justify-end gap-1">
      <Button size="sm" variant="ghost" onClick={() => onView(emp)}>Lihat</Button>
      <Button size="sm" variant="outline" onClick={() => onEdit(emp)}>Edit</Button>
      <Button size="sm" variant="ghost" className="text-red-700 hover:text-red-800" onClick={() => onDelete(emp)}>Hapus</Button>
    </div>
  )
})

export function Pagination({ page, pageCount, onChange }: { page: number; pageCount: number; onChange: (p: number) => void }) {
  return (
    <div className="flex items-center gap-1">
      <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => onChange(page - 1)}>‹</Button>
      {pageWindow(page, pageCount).map((n, i) =>
        n === "…" ? (
          <span key={`gap-${i}`} className="px-1">…</span>
        ) : (
          <Button
            key={n}
            size="sm"
            variant={page === n ? "default" : "outline"}
            className={page === n ? primaryBtn : ""}
            onClick={() => onChange(n)}
          >
            {n}
          </Button>
        ),
      )}
      <Button size="sm" variant="outline" disabled={page >= pageCount} onClick={() => onChange(page + 1)}>›</Button>
    </div>
  )
}
