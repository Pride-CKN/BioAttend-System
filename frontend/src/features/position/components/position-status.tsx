import { StatusBadge } from "@/components/shared"

export function PositionStatus({ active }: { active: boolean }) {
  return <StatusBadge status={active ? "Aktif" : "Nonaktif"} />
}
