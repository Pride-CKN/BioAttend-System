import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { formatDateID, supervisorLabel } from "@/seeds/employee"
import { shiftHours } from "@/features/employee/lib/employee.Option"
import { initials, primaryBtn, statusClass } from "@/features/employee/lib/format"
import type { EmployeeDetail } from "@/features/employee/employee.type"

export function Chip({ className, children }: { className: string; children: React.ReactNode }) {
  return (
    <Badge variant="outline" className={`gap-1 rounded-full px-2 py-0 text-[10px] font-medium ${className}`}>
      <span className="size-1.5 rounded-full bg-current" />
      {children}
    </Badge>
  )
}

function InfoCell({ label, value }: { label: string; value: string }) {
  return (
    <div className="p-3 sm:px-4">
      <p className="text-[11px] text-muted-foreground">{label}</p>
      <p className="text-sm font-semibold">{value}</p>
    </div>
  )
}

export function ProfileCard({ detail, onEdit }: { detail: EmployeeDetail; onEdit: () => void }) {
  const { employee, profile: p } = detail
  return (
    <div className="flex flex-wrap items-start gap-4 rounded-lg border bg-white p-4 sm:p-5">
      <div className="flex size-16 shrink-0 items-center justify-center rounded-xl bg-red-700 text-xl font-bold text-white">
        {initials(employee.name)}
      </div>
      <div className="min-w-0 flex-1 space-y-1.5">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="text-lg font-bold">{employee.name}</h2>
          <Chip className={statusClass[employee.status] ?? ""}>{employee.status}</Chip>
          <Chip className="border-blue-200 bg-blue-50 text-blue-700">{employee.unit}</Chip>
          <Chip className="border-amber-200 bg-amber-50 text-amber-700">Deduction {employee.potongan}</Chip>
        </div>
        <p className="text-xs text-muted-foreground">
          ID Karyawan: EMP-{employee.id} · PIN Fingerprint: {employee.id} · Bergabung sejak {formatDateID(p.joinDate)}
        </p>
        <p className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
          <span>📍 {p.alamat || "—"}</span>
          <span>✉️ {p.email}</span>
          <span>📞 {p.hp || "—"}</span>
        </p>
      </div>
      <div className="flex items-center gap-2">
        <Button size="sm" variant="outline" asChild><a href={`mailto:${p.email}`}>Kirim Email</a></Button>
        <Button size="sm" variant="outline" onClick={onEdit}>Edit</Button>
        <Button size="sm" className={primaryBtn} aria-label="Menu lainnya">⋮</Button>
      </div>
    </div>
  )
}

export function InfoGrid({ detail }: { detail: EmployeeDetail }) {
  const { employee, profile: p } = detail
  const cells: [string, string][] = [
    ["Divisi", employee.dept],
    ["Unit", p.division],
    ["Position", employee.position],
    ["Atasan Langsung", supervisorLabel(p.supervisor)],
    ["Status Kepegawaian", p.statusKepegawaian ?? "Permanen"],
    ["Join Date", formatDateID(p.joinDate)],
    ["Shift Utama", `${employee.shift} · ${shiftHours[employee.shift] ?? ""}`],
    ["Sisa Cuti Tahunan", p.cuti],
  ]
  return (
    <div className="grid grid-cols-2 divide-x divide-y overflow-hidden rounded-lg border bg-white @3xl:grid-cols-4">
      {cells.map(([l, v]) => <InfoCell key={l} label={l} value={v} />)}
    </div>
  )
}
