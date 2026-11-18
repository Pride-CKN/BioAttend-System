import { memo, type ChangeEvent } from "react"
import type { EmployeeFormApi } from "@/features/employee/hooks/useEmployeeForm"
import type { EmployeeFormValues } from "@/features/employee/types/employee.type"
import { FormField, FormSelect, Section, inputCls } from "@/features/employee/components/form/formFields"

type SectionProps = Pick<
  EmployeeFormApi,
  | "form"
  | "errors"
  | "set"
  | "options"
  | "changeOffice"
  | "changeDivision"
  | "changeUnit"
  | "changePosition"
  | "changeManager"
  | "isEdit"
>
const text =
  (set: EmployeeFormApi["set"], key: keyof EmployeeFormValues) =>
  (event: ChangeEvent<HTMLInputElement>) =>
    set(key, event.target.value as never)

export const IdentitySection = memo(function IdentitySection({ form, errors, set, isEdit }: SectionProps) {
  return (
    <Section no={1} title="Identitas Karyawan" id="sec-identitas">
      <FormField label="Nama Lengkap" required error={errors.full_name}>
        <input className={inputCls(errors.full_name)} value={form.full_name} onChange={text(set, "full_name")} />
      </FormField>
      <FormField full label="NIP / PIN Fingerprint" required={!form.autoGenerateEmployeeCode} hint={form.autoGenerateEmployeeCode ? "NIP akan dibuat oleh sistem saat data disimpan" : "6 digit; PIN perangkat mengikuti NIP"} error={errors.pin}>
        <div className="space-y-2">
          {!isEdit && <label className="flex items-center gap-2 text-xs text-muted-foreground">
            <input type="checkbox" checked={form.autoGenerateEmployeeCode} onChange={(event) => {
              const checked = event.target.checked
              set("autoGenerateEmployeeCode", checked)
              if (checked) set("pin", "")
            }} />
            Buat NIP otomatis
          </label>}
          <input className={inputCls(errors.pin)} inputMode="numeric" maxLength={6} placeholder={form.autoGenerateEmployeeCode ? "Akan dibuat otomatis" : "Contoh: 123456"} disabled={form.autoGenerateEmployeeCode} value={form.autoGenerateEmployeeCode ? "" : form.pin} onChange={(event) => set("pin", event.target.value.replace(/\D/g, ""))} />
        </div>
      </FormField>
      <FormField label="Jenis Kelamin">
        <FormSelect value={form.jenisKelamin} options={["Laki-laki", "Perempuan"]} onChange={(value) => set("jenisKelamin", value)} />
      </FormField>
      <FormField label="Tanggal Bergabung">
        <input type="date" className={inputCls()} value={form.joinDate} onChange={text(set, "joinDate")} />
      </FormField>
    </Section>
  )
})

export const AccountSection = memo(function AccountSection({ form, errors, set, isEdit }: SectionProps) {
  return (
    <Section no={2} title="Kontak & Akun" id="sec-kontak">
      <FormField label="Email" required={!isEdit} error={errors.email}>
        <input type="email" className={inputCls(errors.email)} value={form.email} onChange={text(set, "email")} />
      </FormField>
      <FormField label="Nomor HP" error={errors.hp}>
        <input inputMode="tel" className={inputCls(errors.hp)} value={form.hp} onChange={text(set, "hp")} />
      </FormField>
      {!isEdit && <FormField label="Role Akun Login" required>
        <FormSelect value={form.role} options={["EMPLOYEE", "KADIV"]} onChange={(value) => set("role", value)} />
      </FormField>}
    </Section>
  )
})

export const PlacementSection = memo(function PlacementSection({
  form,
  errors,
  options,
  changeOffice,
  changeDivision,
  changeUnit,
  changePosition,
  changeManager,
}: SectionProps) {
  const units = options.units.filter((unit) => unit.divisionId === form.divisionId)
  const managers = options.managers.filter((manager) =>
    manager.divisionId === form.divisionId && manager.unitId === (form.unitId || null),
  )
  return (
    <Section no={3} title="Penempatan Kerja" id="sec-penempatan">
      <div className="grid gap-x-4 gap-y-3 @xl:col-span-2 @xl:grid-cols-3">
        <FormField label="Kantor" required error={errors.officeId}>
          <FormSelect value={form.office} options={options.offices.map((office) => office.name)} onChange={changeOffice} error={errors.officeId} />
        </FormField>
        <FormField label="Division" required error={errors.divisionId}>
          <FormSelect value={form.division} options={options.divisions.map((division) => division.name)} onChange={changeDivision} error={errors.divisionId} />
        </FormField>
        <FormField label="Unit">
          <FormSelect value={form.unit} options={units.map((unit) => unit.name)} onChange={changeUnit} />
        </FormField>
        <FormField label="Jabatan">
          <FormSelect value={form.position} options={options.positions.map((position) => position.name)} onChange={changePosition} />
        </FormField>
        <FormField label="Atasan Langsung">
          <FormSelect value={form.supervisor} options={managers.map((manager) => manager.name)} onChange={changeManager} />
        </FormField>
      </div>
    </Section>
  )
})
