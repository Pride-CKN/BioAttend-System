import { useState } from "react"
import { useParams } from "react-router"
import { Copy } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { useEmployeeDetail } from "@/features/employee/hooks/useEmployeeDetails"
import { useEmployeeForm } from "@/features/employee/hooks/useEmployeeForm"
import { AccountSection, IdentitySection, PlacementSection } from "@/features/employee/components/form/employeeFormSections"
import { NotFoundEmployee, PageLoading } from "@/NotFoundPage"
import type { EmployeeDetail } from "@/features/employee/types/employee.type"
import { PageBreadcrumb } from "@/components/page-breadcrumb"

const formTabs = [
  { label: "Identitas", target: "sec-identitas" },
  { label: "Kontak & Akun", target: "sec-kontak" },
  { label: "Penempatan", target: "sec-penempatan" },
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
  const section = {
    form: f.form,
    errors: f.errors,
    set: f.set,
    options: f.options,
    isEdit: f.isEdit,
    changeOffice: f.changeOffice,
    changeDivision: f.changeDivision,
    changeUnit: f.changeUnit,
    changePosition: f.changePosition,
    changeManager: f.changeManager,
  }

  return (
    <div className="@container min-h-screen min-w-0 flex-1 bg-slate-50">
      <div className="space-y-3 p-3 sm:space-y-4 sm:p-4 lg:p-6 2xl:p-8">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
              <PageBreadcrumb items={[
                { label: "Dashboard"},
                { label: "Karyawan", to: "/employee" },
                { label: f.isEdit ? "Edit" : "Tambah" }
              ]} className="mb-1" />
            <h1 className="text-xl font-bold">{f.isEdit ? "Edit Karyawan" : "Tambah Karyawan"}</h1>
            <p className="text-xs text-muted-foreground">Data identitas, kontak, dan penempatan kerja karyawan.</p>
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

        {f.activation && <Card className="border-emerald-200 bg-emerald-50">
          <CardContent className="space-y-2 p-4">
            <p className="text-sm font-semibold text-emerald-900">Akun berhasil dibuat. Salin token aktivasi sebelum meninggalkan halaman.</p>
            <code className="block break-all rounded-md border border-emerald-200 bg-white p-3 text-xs">{f.activation.token}</code>
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span className="text-xs text-emerald-800">Berlaku sampai {new Date(f.activation.expiresAt).toLocaleString("id-ID")}</span>
              <Button size="sm" variant="outline" onClick={() => void navigator.clipboard.writeText(f.activation!.token)}><Copy />Salin token</Button>
            </div>
          </CardContent>
        </Card>}

        <div className="grid items-start gap-3 @4xl:grid-cols-[1fr_340px] sm:gap-4">
          <div className="space-y-3 sm:space-y-4">
            {f.optionsError && <p role="alert" className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{f.optionsError}</p>}
            <IdentitySection {...section} />
            <AccountSection {...section} />
            <PlacementSection {...section} />
          </div>
        </div>
      </div>

      <div className="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t bg-slate-50/95 px-4 py-3 backdrop-blur sm:px-6">
        <Button size="sm" variant="outline" onClick={f.cancel}>Batal</Button>
        <Button size="sm" variant="outline" disabled={f.saving} onClick={() => f.save("draft")}>Simpan Nonaktif</Button>
        <Button size="sm" variant="outline" disabled={f.saving} onClick={() => f.save("aktif")}>✓ Simpan &amp; Aktifkan</Button>
      </div>
    </div>
  )
}
