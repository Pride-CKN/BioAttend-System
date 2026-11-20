import { memo } from "react"
import { Switch } from "@/components/ui/switch"
import type { DivisionStatus } from "@/features/division/types/division.type"

type Props = { id: string; name: string; status: DivisionStatus; onToggle: (id: string, next: DivisionStatus) => void }

function StatusSwitchBase({ id, name, status, onToggle }: Props) {
  const on = status === "Aktif"
  return (
    <Switch checked={on} aria-label={`${on ? "Nonaktifkan" : "Aktifkan"} ${name}`} onCheckedChange={(checked) => onToggle(id, checked ? "Aktif" : "Nonaktif")} />
  )
}

export const StatusSwitch = memo(StatusSwitchBase)
