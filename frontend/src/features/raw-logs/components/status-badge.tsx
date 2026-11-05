import { Badge } from "@/components/ui/badge"
import { statusLabel, type LogStatus } from "../types"

export function StatusBadge({ status }: { status: LogStatus }) {
  const className = {
    valid:
      "gap-1.5 rounded-full border-emerald-700 bg-[#a7b3b0] px-2 py-0.5 text-[11px] font-medium text-[#66f2c0] dark:border-emerald-700 dark:bg-[#52635f] dark:text-[#66f2c0]",
    duplicate:
      "gap-1.5 rounded-full border-[#52617a] bg-[#25344d] px-2 py-0.5 text-[11px] font-medium text-[#d3dce8] dark:border-[#52617a] dark:bg-[#25344d] dark:text-[#d3dce8]",
    error:
      "gap-1.5 rounded-full border-[#b40000] bg-[#b7a4a6] px-2 py-0.5 text-[11px] font-medium text-[#ff9696] dark:border-[#b40000] dark:bg-[#63494c] dark:text-[#ff9696]",
  }[status]

  return (
    <Badge variant="outline" className={className}>
      <span className="size-1.5 rounded-full bg-current" />
      {statusLabel[status]}
    </Badge>
  )
}
