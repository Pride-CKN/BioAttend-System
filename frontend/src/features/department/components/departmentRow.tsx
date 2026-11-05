import { memo } from "react"
import { Link } from "react-router"
import type { Department, DepartmentStatus } from "@/features/department/department.type"
import { formatShortDate, headName } from "@/features/department/lib/department.Utils"
import { StatusSwitch } from "@/features/department/components/statusSwitch"

type RowProps = { dept: Department; onToggle: (id: string, next: DepartmentStatus) => void }

const badge = (status: DepartmentStatus) =>
  status === "Aktif" ? "bg-emerald-50 text-emerald-700 border-emerald-200" : "bg-gray-100 text-gray-600 border-gray-200"

const editLink = "rounded-md border px-2.5 py-1 text-xs font-medium hover:bg-gray-50"

/* Desktop: satu baris tabel. memo → hanya baris yang datanya berubah yang re-render. */
export const DepartmentRow = memo(function DepartmentRow({ dept, onToggle }: RowProps) {
  return (
    <tr className="border-t text-sm">
      <td className="px-3 py-2.5">
        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">{dept.code}</span>
      </td>
      <td className="px-3 py-2.5 font-semibold">{dept.name}</td>
      <td className="px-3 py-2.5 text-muted-foreground">{dept.division}</td>
      <td className="px-3 py-2.5">{dept.totalEmployee}</td>
      <td className="whitespace-nowrap px-3 py-2.5">
        <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs ${badge(dept.status)}`}>
          <span aria-hidden="true">●</span>
          {dept.status}
        </span>
      </td>
      <td className="px-3 py-2.5">{headName(dept.headId)}</td>
      <td className="px-3 py-2.5 text-muted-foreground">{formatShortDate(dept.updatedAt)}</td>
      <td className="w-[130px] whitespace-nowrap px-3 py-2.5">
        <div className="flex items-center justify-end gap-2">
          <Link to={`/department/${dept.id}/edit`} className={editLink}>Edit</Link>
          <StatusSwitch id={dept.id} name={dept.name} status={dept.status} onToggle={onToggle} />
        </div>
      </td>
    </tr>
  )
})

/* Mobile: kartu department. */
export const DepartmentCard = memo(function DepartmentCard({ dept, onToggle }: RowProps) {
  return (
    <li className="rounded-lg border bg-white p-3">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">{dept.code}</span>
          <p className="mt-1 truncate font-semibold">{dept.name}</p>
          <p className="text-xs text-muted-foreground">{dept.division}</p>
        </div>
        <span className={`shrink-0 rounded-full border px-2 py-0.5 text-xs ${badge(dept.status)}`}>● {dept.status}</span>
      </div>
      <dl className="mt-3 grid grid-cols-2 gap-2 text-xs">
        <div>
          <dt className="text-muted-foreground">Karyawan</dt>
          <dd className="font-medium">{dept.totalEmployee}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Kepala Unit</dt>
          <dd className="truncate font-medium">{headName(dept.headId)}</dd>
        </div>
      </dl>
      <div className="mt-3 flex items-center justify-between border-t pt-3">
        <StatusSwitch id={dept.id} name={dept.name} status={dept.status} onToggle={onToggle} />
        <Link to={`/department/${dept.id}/edit`} className={editLink}>Edit</Link>
      </div>
    </li>
  )
})
