import { useState } from "react"
import { Link, useParams } from "react-router"
import { Button } from "@/components/ui/button"
import { useEmployeeDetail } from "@/features/employee/hooks/useEmployeeDetails"
import { useEmployeeForm } from "@/features/employee/hooks/useEmployeeForm"
import { AccountSection, AttendanceSection, IdentitySection, PlacementSection } from "@/features/employee/components/form/formSections"
import { DocumentsCard, PhotoCard, ValidationCard } from "@/features/employee/components/form/formSidebar"
import { NotFoundEmployee, PageLoading } from "@/features/employee/components/notFoundEmployee"
import { primaryBtn } from "@/features/employee/lib/format"
import type { EmployeeDetail } from "@/features/employee/employee.type"

const formTabs = [
  { label: "Data Pribadi", target: "sec-identitas" },
  { label: "Data Kepegawaian", target: "sec-penempatan" },
  { label: "Absensi & PIN", target: "sec-absensi" },
  { label: "Dokumen", target: "sec-dokumen" },
  { label: "Akun & Akses", target: "sec-kontak" },
]

export default function EmployeeForm() {
  const { id } = useParams()
  const { detail, loading } = useEmployeeDetail(id)
  if (id && loading) return <PageLoading />
  if (id && !detail) return <NotFoundEmployee id={id} />
  // key resets form state when switching between employees
  return <EmployeeFormView key={id ?? "new"} detail={detail} />
}

function EmployeeFormView({ detail }: { detail: EmployeeDetail | null }) {
  const f = useEmployeeForm(detail)
  const [activeTab, setActiveTab] = useState(formTabs[0].label)

  const scrollTo = (label: string, target: string) => {
    setActiveTab(label)
    document.getElementById(target)?.scrollIntoView({ behavior: "smooth", block: "start" })
  }
  const section = { form: f.form, errors: f.errors, set: f.set, isEdit: f.isEdit, employeeId: f.employeeId }

  return (
    <div className="@container min-h-screen min-w-0 flex-1 bg-slate-50">
      <div className="space-y-3 p-3 sm:space-y-4 sm:p-4 lg:p-6 2xl:p-8">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-xs text-muted-foreground">
              Dashboard / Master Data / <Link to="/employee" className="hover:underline">Employee</Link> /{" "}
              <span className="font-semibold text-foreground">{f.isEdit ? "Edit" : "Tambah"}</span>
            </p>
            <h1 className="text-xl font-bold">{f.isEdit ? "Edit Karyawan" : "Tambah Karyawan"}</h1>
            <p className="text-xs text-muted-foreground">Form master karyawan: identitas, penempatan kerja, dan enrollment fingerprint</p>
          </div>
          <Button size="sm" variant="outline" onClick={f.cancel}>Batal</Button>
        </div>

        <div className="flex gap-5 overflow-x-auto border-b">
          {formTabs.map((t) => (
            <button
              key={t.label}
              type="button"
              onClick={() => scrollTo(t.label, t.target)}
              className={`-mb-px whitespace-nowrap border-b-2 px-1 pb-2 text-xs font-semibold ${
                activeTab === t.label ? "border-[#EA232A] text-red-700" : "border-transparent text-muted-foreground hover:text-foreground"
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        {f.errorCount > 0 && (
          <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
            Ada {f.errorCount} isian yang perlu diperbaiki sebelum menyimpan &amp; mengaktifkan.
          </div>
        )}

        <div className="grid items-start gap-3 @4xl:grid-cols-[1fr_340px] sm:gap-4">
          <div className="space-y-3 sm:space-y-4">
            <IdentitySection {...section} />
            <AccountSection {...section} />
            <PlacementSection {...section} changeDepartment={f.changeDepartment} />
            <AttendanceSection {...section} />
          </div>
          <aside className="space-y-3 sm:space-y-4">
            <PhotoCard name={f.form.nama} />
            <DocumentsCard isEdit={f.isEdit} />
            <ValidationCard pin={f.form.pin} email={f.form.email} pinOwner={f.pinOwner} placementValid={f.placementValid} />
          </aside>
        </div>
      </div>

      <div className="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t bg-slate-50/95 px-4 py-3 backdrop-blur sm:px-6">
        <Button size="sm" variant="outline" onClick={f.cancel}>Batal</Button>
        <Button size="sm" variant="outline" disabled={f.saving} onClick={() => f.save("draft")}>Simpan sebagai Draft</Button>
        <Button size="sm" className={primaryBtn} disabled={f.saving} onClick={() => f.save("aktif")}>✓ Simpan &amp; Aktifkan</Button>
      </div>
    </div>
  )
}
