import { Card, CardContent } from "@/components/ui/card"
import type { EmployeeSchedule, Shift } from "../types"

const days = ["Sen", "Sel", "Rab", "Kam", "Jum", "Sab", "Min"]

export function WeeklyScheduleGrid({
  schedules,
  shifts,
}: {
  schedules: EmployeeSchedule[]
  shifts: Shift[]
}) {
  return (
    <Card className="hidden overflow-hidden py-0 lg:block">
      <div className="border-b px-4 py-3">
        <h2 className="font-semibold">Grid Jadwal Mingguan</h2>
        <p className="text-xs text-muted-foreground">Ringkasan penempatan shift per hari</p>
      </div>
      <CardContent className="overflow-x-auto p-0">
        <div className="grid min-w-[760px] grid-cols-8 text-xs">
          <div className="border-b bg-muted/50 p-3 font-medium">Karyawan</div>
          {days.map((day) => <div key={day} className="border-b border-l bg-muted/50 p-3 text-center font-medium">{day}</div>)}
          {schedules.map((schedule) => {
            const shift = shifts.find((item) => item.id === schedule.shiftId)
            return (
              <div key={schedule.id} className="contents">
                <div className="border-b p-3 font-medium">{schedule.employee}</div>
                {days.map((day, index) => (
                  <div key={`${schedule.id}-${day}`} className="border-b border-l p-2">
                    {index === 5 || index === 6 ? <span className="text-muted-foreground">Libur</span> : <span className="rounded bg-[var(--brand-red)]/10 px-2 py-1 text-[10px] text-[var(--brand-red)]">{shift?.name ?? "-"}</span>}
                  </div>
                ))}
              </div>
            )
          })}
        </div>
      </CardContent>
    </Card>
  )
}
