import { memo } from "react"
import type { DepartmentStatus } from "@/features/department/department.type"

type Props = { id: string; name: string; status: DepartmentStatus; onToggle: (id: string, next: DepartmentStatus) => void }

function StatusSwitchBase({ id, name, status, onToggle }: Props) {
  const on = status === "Aktif"
  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      aria-label={`${on ? "Nonaktifkan" : "Aktifkan"} ${name}`}
      onClick={() => onToggle(id, on ? "Nonaktif" : "Aktif")}
      className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 ${on ? "bg-emerald-500" : "bg-gray-300"}`}
    >
      <span className={`inline-block size-5 rounded-full bg-white shadow transition-transform ${on ? "translate-x-5" : "translate-x-0.5"}`} />
    </button>
  )
}

export const StatusSwitch = memo(StatusSwitchBase)
