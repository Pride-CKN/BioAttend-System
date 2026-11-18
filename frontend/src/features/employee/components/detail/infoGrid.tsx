
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"

import {
  Field,
  FieldContent,
  FieldLabel,
} from "@/components/ui/field"

import type { EmployeeDetail } from "@/features/employee/types/employee.type"

function InfoField({
  label,
  value,
}: {
  label: string
  value: string
}) {
  return (
    <Field orientation="vertical" className="gap-1">
      <FieldLabel className="text-[11px] text-muted-foreground">
        {label}
      </FieldLabel>

      <FieldContent>
        <p className="break-words text-sm font-semibold">
          {value || "—"}
        </p>
      </FieldContent>
    </Field>
  )
}

export function InfoGrid({
  detail,
}: {
  detail: EmployeeDetail
}) {
  const { employee, profile: p } = detail

  const cells: [string, string][] = [
    ["Kantor", employee.officeName ?? "—"],
    ["Divisi", employee.division],
    ["Unit", employee.unit],
    ["Position", employee.position],
    ["Atasan Langsung", p.supervisor || "—"],
    ["Status Karyawan", employee.employmentStatus ?? "—"],
    ["Tanggal Bergabung", employee.joinDate ?? "—"],
  ]

  return (
    <Card className="rounded-lg shadow-none">
      <CardHeader className="pb-3">
        <CardTitle className="text-sm font-semibold">
          Informasi Kepegawaian
        </CardTitle>
      </CardHeader>

      <CardContent className="grid grid-cols-1 gap-x-6 gap-y-5 border-t p-4 sm:grid-cols-2 @3xl:grid-cols-4">
        {cells.map(([label, value]) => (
          <InfoField
            key={label}
            label={label}
            value={value}
          />
        ))}
      </CardContent>
    </Card>
  )
}
