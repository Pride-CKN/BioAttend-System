import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import type { Shift } from "../types"

const barColors = ["bg-[#c51d25]", "bg-[#df8500]", "bg-[#3478e5]", "bg-[#b56cc4]", "bg-[#4b9b75]"]

export function ShiftDistributionChart({ shifts }: { shifts: Shift[] }) {
  const maxEmployees = Math.max(...shifts.map((shift) => shift.assignedEmployees), 1)

  return (
    <Card className="overflow-hidden py-0">
      <CardHeader className="border-b px-4 py-3">
        <CardTitle className="text-sm">Distribusi Karyawan per Shift</CardTitle>
        <p className="text-xs text-muted-foreground">Penempatan karyawan</p>
      </CardHeader>
      <CardContent className="px-4 py-5">
        {shifts.length === 0 ? (
          <div className="flex h-44 items-center justify-center text-sm text-muted-foreground">
            Belum ada data distribusi shift.
          </div>
        ) : (
          <div className="mx-auto flex h-48 max-w-3xl items-end justify-center gap-5 border-b border-l px-5 pb-0 pt-3 sm:gap-10">
            {shifts.map((shift, index) => (
              <div key={shift.id} className="flex h-full min-w-12 flex-1 flex-col items-center justify-end gap-1">
                <span className="text-[10px] font-semibold text-foreground">{shift.assignedEmployees}</span>
                <div
                  className={`w-full max-w-10 rounded-t-sm ${barColors[index % barColors.length]}`}
                  style={{ height: `${Math.max((shift.assignedEmployees / maxEmployees) * 78, 4)}%` }}
                  title={`${shift.name}: ${shift.assignedEmployees} karyawan`}
                />
                <span className="max-w-20 truncate text-center text-[10px] text-muted-foreground">{shift.name}</span>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
