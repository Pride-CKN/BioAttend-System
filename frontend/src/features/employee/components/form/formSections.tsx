import { memo, type ChangeEvent } from "react"
import { Button } from "@/components/ui/button"
import {
  COMPANY_DOMAIN, bloodTypes, departments, devices, divisionsByDept, employmentStatuses,
  genders, positionsByDept, roles, shiftHours, shifts, supervisors,
} from "@/features/employee/lib/employee.Option"
import { generatePassword } from "@/features/employee/lib/employeeFormState"
import type { EmployeeFormApi } from "@/features/employee/hooks/useEmployeeForm"
import type { EmployeeFormValues } from "@/features/employee/employee.type"
import { Field, Section, Select, Switch, inputCls } from "@/features/employee/components/form/formFields"

const text =
  (set: EmployeeFormApi["set"], key: keyof EmployeeFormValues) =>
  (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    set(key, e.target.value as never)

type Props = Pick<EmployeeFormApi, "form" | "errors" | "set"> & { employeeId: string; isEdit: boolean }

export const IdentitySection = memo(function IdentitySection({ form, errors, set, employeeId }: Props) {
  return (
    <Section no={1} title="Identitas Karyawan" id="sec-identitas">
      <Field label="ID Karyawan" required hint="Auto-generated, tidak dapat diubah">
        <input className={inputCls()} value={`EMP-${employeeId}`} disabled readOnly />
      </Field>
      <Field label="Nama Lengkap" required hint="Sesuai KTP / Akta Nikah" error={errors.nama}>
        <input className={inputCls(errors.nama)} value={form.nama} onChange={text(set, "nama")} />
      </Field>
      <Field label="NIK" required hint="16 digit" error={errors.nik}>
        <input className={inputCls(errors.nik)} inputMode="numeric" maxLength={16} value={form.nik}
          onChange={(e) => set("nik", e.target.value.replace(/\D/g, ""))} />
      </Field>
      <Field label="Tempat, Tanggal Lahir" required error={errors.tempatLahir}>
        <input className={inputCls(errors.tempatLahir)} placeholder="Jakarta, 15 Agustus 1993" value={form.tempatLahir} onChange={text(set, "tempatLahir")} />
      </Field>
      <Field label="Jenis Kelamin"><Select value={form.jenisKelamin} options={genders} onChange={(v) => set("jenisKelamin", v)} /></Field>
      <Field label="Golongan Darah"><Select value={form.golonganDarah} options={bloodTypes} onChange={(v) => set("golonganDarah", v)} /></Field>
      <Field label="Alamat Lengkap" full>
        <textarea rows={3} className={`${inputCls()} h-auto py-2`} value={form.alamat} onChange={text(set, "alamat")} />
      </Field>
    </Section>
  )
})

export const AccountSection = memo(function AccountSection({ form, errors, set, isEdit }: Props) {
  return (
    <Section no={2} title="Kontak & Akun" id="sec-kontak">
      <Field label="Nomor HP" required error={errors.hp}>
        <input className={inputCls(errors.hp)} inputMode="tel" value={form.hp} onChange={text(set, "hp")} />
      </Field>
      <Field label="Email Perusahaan" required hint={`Domain ${COMPANY_DOMAIN.slice(1)}`} error={errors.email}>
        <input type="email" className={inputCls(errors.email)} value={form.email} onChange={text(set, "email")} />
      </Field>
      <Field label="Username Login" required error={errors.username}>
        <input className={inputCls(errors.username)} value={form.username} onChange={text(set, "username")} />
      </Field>
      <Field label="Role / Akses" hint="Employee = akses data sendiri">
        <Select value={form.role} options={roles} onChange={(v) => set("role", v)} />
      </Field>
      <Field label="Password Sementara" full required={!isEdit} error={errors.password}
        hint={isEdit ? "Kosongkan bila tidak diubah" : "Generate ulang bila terlupa"}>
        <div className="flex gap-2">
          <input type="text" className={inputCls(errors.password)} placeholder={isEdit ? "••••••••" : ""} value={form.password} onChange={text(set, "password")} />
          <Button type="button" variant="outline" size="sm" className="h-9" onClick={() => set("password", generatePassword())}>Generate</Button>
        </div>
      </Field>
    </Section>
  )
})

export const PlacementSection = memo(function PlacementSection({
  form, errors, set, changeDepartment,
}: Props & Pick<EmployeeFormApi, "changeDepartment">) {
  return (
    <Section no={3} title="Penempatan Kerja" id="sec-penempatan">
      <div className="grid gap-x-4 gap-y-3 @xl:col-span-2 @xl:grid-cols-3">
        <Field label="Divisi" required><Select value={form.department} options={departments} onChange={changeDepartment} /></Field>
        <Field label="Unit" required>
          <Select value={form.division} options={divisionsByDept[form.department] ?? []} onChange={(v) => set("division", v)} />
        </Field>
        <Field label="Position" required error={errors.position}>
          <Select value={form.position} options={positionsByDept[form.department] ?? []} onChange={(v) => set("position", v)} error={errors.position} />
        </Field>
        <Field label="Status Kepegawaian">
          <Select value={form.statusKepegawaian} options={employmentStatuses} onChange={(v) => set("statusKepegawaian", v)} />
        </Field>
        <Field label="Tanggal Bergabung" required error={errors.joinDate}>
          <input type="date" className={inputCls(errors.joinDate)} value={form.joinDate} onChange={text(set, "joinDate")} />
        </Field>
        <Field label="Atasan Langsung">
          <Select value={form.supervisor} options={supervisors.map((s) => s.name)} onChange={(v) => set("supervisor", v)} />
        </Field>
      </div>
    </Section>
  )
})

export const AttendanceSection = memo(function AttendanceSection({ form, errors, set }: Props) {
  return (
    <Section no={4} title="Absensi & Fingerprint" id="sec-absensi">
      <Field label="PIN Fingerprint" required hint="Maksimal 10 digit, unik" error={errors.pin}>
        <input className={inputCls(errors.pin)} inputMode="numeric" maxLength={10} value={form.pin}
          onChange={(e) => set("pin", e.target.value.replace(/\D/g, ""))} />
      </Field>
      <Field label="Shift Utama" required>
        <select className={inputCls()} value={form.shift} onChange={(e) => set("shift", e.target.value)}>
          {shifts.map((s) => <option key={s} value={s}>{s} ({shiftHours[s]})</option>)}
        </select>
      </Field>
      <Field label="Device Fingerprint" hint="Pindahkan scan bila perlu"><Select value={form.device} options={devices} onChange={(v) => set("device", v)} /></Field>
      <Field label="Toleransi Terlambat (menit)" hint="Default perusahaan: 15 menit">
        <input type="number" min={0} className={inputCls()} value={form.toleransi} onChange={text(set, "toleransi")} />
      </Field>
      <div className="space-y-1">
        <p className="text-xs font-semibold">Wajib Fingerprint</p>
        <Switch checked={form.wajibFingerprint} onChange={(v) => set("wajibFingerprint", v)} label="Ya, absensi hanya lewat fingerprint" />
      </div>
      <div className="space-y-1">
        <p className="text-xs font-semibold">Dukungan Presensi Manual</p>
        <Switch checked={form.presensiManual} onChange={(v) => set("presensiManual", v)} label="Izinkan approval manual oleh HRD" />
      </div>
    </Section>
  )
})
