import { Badge } from "@/components/ui/badge"
import type { CorrectionStatus } from "../types"

const labels: Record<CorrectionStatus, string> = {
  pending: "Pending",
  approved: "Approved",
  rejected: "Rejected",
}

const colors: Record<CorrectionStatus, string> = {
  pending:
    "border-[#faa419]/40 bg-[#faa419]/15 text-[#9a5d00] dark:text-[#f0d00b]",
  approved:
    "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300",
  rejected:
    "border-[#ca373d]/30 bg-[#ca373d]/10 text-[#ca373d] dark:text-[#ff8c90]",
}

export function CorrectionStatusBadge({
  status,
}: {
  status: CorrectionStatus
}) {
  return (
    <Badge variant="outline" className={colors[status]}>
      {labels[status]}
    </Badge>
  )
}
