import { useState } from "react"
import { Link, useParams } from "react-router"
import type { DepartmentInput } from "@/features/department/department.type"
import { departmentDivisions, departmentStatuses } from "@/features/department/lib/departmentConfig"
import { headOptions } from "@/features/department/lib/departmentOption"
import { validateDepartment, type DepartmentErrors } from "@/features/department/departmentValidation"
import { useDepartmentForm } from "@/features/department/hooks/useDepartmentForm"

const input = "h-9 w-full rounded-md border bg-white px-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-red-700/40 aria-[invalid=true]:border-red-600"

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <label className="flex flex-col gap-1 text-sm">
      <span className="font-medium">{label}</span>
      {children}
      {error && <span className="text-xs text-red-600">{error}</span>}
    </label>
  )
}

// Form dipisah dari loader agar state form diinisialisasi sekali dari `initial` (tanpa effect sinkronisasi).
function DepartmentFormBody({ initial, isEdit, saving, error, onSave }: { initial: DepartmentInput; isEdit: boolean; saving: boolean; error: string | null; onSave: (v: DepartmentInput) => void }) {
  const [values, setValues] = useState<DepartmentInput>(initial)
  const [errors, setErrors] = useState<DepartmentErrors>({})
  const set = <K extends keyof DepartmentInput>(k: K, v: DepartmentInput[K]) => setValues((s) => ({ ...s, [k]: v }))

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    const next = validateDepartment(values)
    setErrors(next)
    if (Object.keys(next).length === 0) onSave(values)
  }

  return (
    <form onSubmit={submit} noValidate className="flex max-w-2xl flex-col gap-4 rounded-lg border bg-white p-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Kode" error={errors.code}>
          <input className={input} value={values.code} aria-invalid={!!errors.code} onChange={(e) => set("code", e.target.value.toUpperCase())} />
        </Field>
        <Field label="Status">
          <select className={input} value={values.status} onChange={(e) => set("status", e.target.value as DepartmentInput["status"])}>
            {departmentStatuses.map((s) => <option key={s}>{s}</option>)}
          </select>
        </Field>
      </div>
      <Field label="Nama Divisi" error={errors.name}>
        <input className={input} value={values.name} aria-invalid={!!errors.name} onChange={(e) => set("name", e.target.value)} />
      </Field>
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Unit" error={errors.division}>
          <select className={input} value={values.division} aria-invalid={!!errors.division} onChange={(e) => set("division", e.target.value)}>
            <option value="">Pilih unit</option>
            {departmentDivisions.map((d) => <option key={d}>{d}</option>)}
          </select>
        </Field>
        <Field label="Kepala Unit Penanggung Jawab">
          <select className={input} value={values.headId ?? ""} onChange={(e) => set("headId", e.target.value || null)}>
            <option value="">— Belum ditentukan —</option>
            {headOptions.map((h) => <option key={h.id} value={h.id}>{h.label}</option>)}
          </select>
        </Field>
      </div>

      {error && <p role="alert" className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <div className="flex justify-end gap-2">
        <Link to="/department" className="inline-flex h-9 items-center rounded-md border px-3 text-sm hover:bg-gray-50">Batal</Link>
        <button type="submit" disabled={saving} className="h-9 rounded-md bg-red-700 px-4 text-sm font-medium text-white hover:bg-red-800 disabled:opacity-60">
          {saving ? "Menyimpan…" : isEdit ? "Simpan Perubahan" : "Tambah Divisi"}
        </button>
      </div>
    </form>
  )
}

export default function DepartmentForm() {
  const { id } = useParams()
  const f = useDepartmentForm(id)

  return (
    <div className="flex flex-col gap-4 p-4 md:p-6">
      <header>
        <h1 className="text-xl font-bold">{f.isEdit ? "Edit Divisi" : "Tambah Divisi"}</h1>
        <p className="text-xs text-muted-foreground">Total karyawan dihitung otomatis dari data karyawan.</p>
      </header>

      {f.notFound ? (
        <p className="text-sm text-muted-foreground">
          Divisi tidak ditemukan. <Link to="/department" className="text-red-700 hover:underline">Kembali ke daftar</Link>
        </p>
      ) : !f.initial ? (
        <p className="text-sm text-muted-foreground">{f.error ?? "Memuat…"}</p>
      ) : (
        // key = id → form di-reset bila pindah dari edit satu ke edit lainnya
        <DepartmentFormBody key={id ?? "new"} initial={f.initial} isEdit={f.isEdit} saving={f.saving} error={f.error} onSave={f.save} />
      )}
    </div>
  )
}
