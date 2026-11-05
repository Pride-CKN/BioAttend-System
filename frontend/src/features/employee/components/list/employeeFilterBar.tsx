import { memo } from "react"
import { Button } from "@/components/ui/button"
import { FILTER_DEPARTMENTS, FILTER_DIVISIONS, FILTER_SHIFTS, FILTER_STATUSES } from "@/features/employee/lib/employee.Option"
import { primaryBtn } from "@/features/employee/lib/format"
import type { EmployeeFilters } from "@/features/employee/employee.type"

const selectClass =
  "h-9 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"

function FilterSelect({
  label, all, value, options, onChange,
}: { label: string; all: string; value: string; options: string[]; onChange: (v: string) => void }) {
  return (
    <label className="flex items-center gap-2 text-xs text-muted-foreground">
      {label}
      <select className={selectClass} value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="">{all}</option>
        {options.map((o) => <option key={o}>{o}</option>)}
      </select>
    </label>
  )
}

type Props = {
  filters: EmployeeFilters
  onChange: <K extends keyof EmployeeFilters>(key: K, value: EmployeeFilters[K]) => void
  onExport: () => void
  onAdd: () => void
}

export const EmployeeFilterBar = memo(function EmployeeFilterBar({ filters, onChange, onExport, onAdd }: Props) {
  return (
    <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-white p-3">
      <input
        type="text"
        placeholder="Cari nama, ID, atau PIN"
        value={filters.keyword}
        onChange={(e) => onChange("keyword", e.target.value)}
        className={`${selectClass} w-52`}
      />
      <FilterSelect label="Departemen" all="Semua Departemen" value={filters.dept} options={FILTER_DEPARTMENTS} onChange={(v) => onChange("dept", v)} />
      <FilterSelect label="Divisi" all="Semua Divisi" value={filters.unit} options={FILTER_DIVISIONS} onChange={(v) => onChange("unit", v)} />
      <FilterSelect label="Status" all="Semua Status" value={filters.status} options={FILTER_STATUSES} onChange={(v) => onChange("status", v)} />
      <FilterSelect label="Shift" all="Semua Shift" value={filters.shift} options={FILTER_SHIFTS} onChange={(v) => onChange("shift", v)} />
      <div className="ml-auto flex gap-2">
        <Button size="sm" variant="outline" onClick={onExport}>Export</Button>
        <Button size="sm" className={primaryBtn} onClick={onAdd}>+ Tambah Employee</Button>
      </div>
    </div>
  )
})
