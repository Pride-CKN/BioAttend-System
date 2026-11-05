import { memo } from "react"
import { Link } from "react-router"
import type { DepartmentFilters } from "@/features/department/department.type"
import { departmentDivisions, departmentStatuses } from "@/features/department/lib/departmentConfig"

type Props = { filters: DepartmentFilters; onChange: (patch: Partial<DepartmentFilters>) => void }

const field = "h-9 rounded-md border bg-white px-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-red-700/40"

function DepartmentFiltersBarBase({ filters, onChange }: Props) {
  return (
    <div className="flex flex-col gap-2 rounded-lg border bg-white p-3 sm:flex-row sm:items-center">
      <input
        value={filters.search}
        onChange={(e) => onChange({ search: e.target.value })}
        placeholder="Cari divisi / unit"
        aria-label="Cari divisi"
        className={`${field} sm:w-64`}
      />
      <select value={filters.division} onChange={(e) => onChange({ division: e.target.value })} aria-label="Filter unit" className={field}>
        <option value="">Semua Unit</option>
        {departmentDivisions.map((d) => (
          <option key={d}>{d}</option>
        ))}
      </select>
      <select
        value={filters.status}
        onChange={(e) => onChange({ status: e.target.value as DepartmentFilters["status"] })}
        aria-label="Filter status"
        className={field}
      >
        <option value="">Semua Status</option>
        {departmentStatuses.map((s) => (
          <option key={s}>{s}</option>
        ))}
      </select>
      <Link to="/department/new" className="inline-flex h-9 items-center justify-center rounded-md bg-red-700 px-3 text-sm font-medium text-white hover:bg-red-800 sm:ml-auto">
        + Tambah Divisi
      </Link>
    </div>
  )
}

export const DepartmentFiltersBar = memo(DepartmentFiltersBarBase)
