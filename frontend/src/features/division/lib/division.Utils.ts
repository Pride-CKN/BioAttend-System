import type { Division, DivisionStats } from "@/features/division/types/division.type"

export const headName = (headId: string | null) => headId ?? "—"

const shortDate = new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short", year: "numeric" })

export function formatShortDate(iso: string) {
  if (!iso) return "—"
  return shortDate.format(new Date(`${iso}T00:00:00`))
}

// Satu kali loop untuk semua angka statistik.
export function computeStats(items: Division[]): DivisionStats {
  let activeDivision = 0
  let totalUnit = 0
  let activeUnit = 0

  for (const division of items) {
    if (division.status === "Aktif") activeDivision++

    for (const unit of division.units) {
      totalUnit++
      if (unit.status === "Aktif") activeUnit++
    }
  }

  return {
    totalDivision: items.length,
    activeDivision,
    inactiveDivision: items.length - activeDivision,
    totalUnit,
    activeUnit,
    inactiveUnit: totalUnit - activeUnit,
  }
}