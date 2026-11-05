import { Card, CardContent } from "@/components/ui/card"
import { ShiftStatus } from "./shift-status"
import type { Shift, WeeklyTemplate } from "../types"

// Menampilkan template jadwal mingguan beserta status aktifnya.
export function TemplateTable({
  templates,
  shifts,
}: {
  templates: WeeklyTemplate[]
  shifts: Shift[]
}) {
  return (
    <Card className="overflow-hidden py-0">
      <div className="border-b px-4 py-3">
        <h2 className="font-semibold">Template Mingguan</h2>
        <p className="text-xs text-muted-foreground">
          Template jadwal berulang berdasarkan department
        </p>
      </div>
      <CardContent className="p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-left text-xs">
              <tr>
                <th className="px-4 py-3">Nama Template</th>
                <th className="px-4 py-3">Department</th>
                <th className="px-4 py-3">Hari Kerja</th>
                <th className="px-4 py-3">Shift</th>
                <th className="px-4 py-3">Status</th>
              </tr>
            </thead>
            <tbody>
              {templates.map((template) => (
                <tr key={template.id} className="border-t">
                  <td className="px-4 py-3 font-medium">{template.name}</td>
                  <td className="px-4 py-3">{template.department}</td>
                  <td className="px-4 py-3">{template.days}</td>
                  <td className="px-4 py-3">
                    {shifts.find((shift) => shift.id === template.shiftId)?.name}
                  </td>
                  <td className="px-4 py-3">
                    <ShiftStatus active={template.status === "active"} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  )
}
