import { useState } from "react"
import { Link, useNavigate, useParams } from "react-router"
import { Button } from "@/components/ui/button"
import { useAttendanceHistory, useDeductionHistory, useEmployeeDetail } from "@/features/employee/hooks/useEmployeeDetails"
import { ProfileCard, InfoGrid } from "@/features/employee/components/detail/profileSection"
import { AttendanceHistoryCard } from "@/features/employee/components/detail/attendanceHistoryCard"
import { DeductionSummaryCard } from "@/features/employee/components/detail/deductionSummaryCard"
import { NotFoundEmployee, PageLoading } from "@/features/employee/components/notFoundEmployee"
import { primaryBtn } from "@/features/employee/lib/format"
import type { EmployeeDetail as Detail } from "@/features/employee/employee.type"

const TABS = ["Ringkasan", "Riwayat Absensi", "Pengajuan", "Deduction", "Dokumen", "Audit Trail"]

export default function EmployeeDetail() {
  const { id } = useParams()
  const { detail, loading } = useEmployeeDetail(id)
  if (loading) return <PageLoading />
  if (!detail) return <NotFoundEmployee id={id} />
  return <EmployeeDetailView detail={detail} />
}

function EmployeeDetailView({ detail }: { detail: Detail }) {
  const navigate = useNavigate()
  const { employee } = detail
  const [tab, setTab] = useState("Ringkasan")
  const onSummary = tab === "Ringkasan"

  const attendance = useAttendanceHistory(employee.id, onSummary)
  const deductions = useDeductionHistory(employee.id, onSummary)
  const edit = () => navigate(`/employee/${employee.id}/edit`)

  return (
    <div className="@container min-h-screen min-w-0 flex-1 space-y-3 bg-slate-50 p-3 sm:space-y-4 sm:p-4 lg:p-6 2xl:p-8">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs text-muted-foreground">
            Dashboard / Master Data / <Link to="/employee" className="hover:underline">Employee</Link> /{" "}
            <span className="font-semibold text-foreground">Detail</span>
          </p>
          <h1 className="text-xl font-bold">Detail Karyawan</h1>
          <p className="text-xs text-muted-foreground">Profil lengkap, riwayat absensi, dan deduction history karyawan</p>
        </div>
        <div className="flex gap-2">
          <Button size="sm" variant="outline" onClick={() => navigate("/employee")}>← Kembali</Button>
          <Button size="sm" variant="outline" onClick={() => window.print()}>Cetak</Button>
          <Button size="sm" className={primaryBtn} onClick={edit}>Edit Data</Button>
        </div>
      </div>

      <ProfileCard detail={detail} onEdit={edit} />
      <InfoGrid detail={detail} />

      <div className="flex gap-5 overflow-x-auto border-b">
        {TABS.map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`-mb-px whitespace-nowrap border-b-2 px-1 pb-2 text-xs font-semibold ${
              tab === t ? "border-[#EA232A] text-red-700" : "border-transparent text-muted-foreground hover:text-foreground"
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      {!onSummary ? (
        <div className="rounded-lg border bg-white py-16 text-center text-sm text-muted-foreground">
          Konten tab “{tab}” belum tersedia.
        </div>
      ) : (
        <div className="grid gap-3 @4xl:grid-cols-[1fr_320px] sm:gap-4">
          <AttendanceHistoryCard rows={attendance.data} loading={attendance.loading} onDetail={() => setTab("Riwayat Absensi")} />
          <DeductionSummaryCard potongan={employee.potongan} history={deductions.data} />
        </div>
      )}
    </div>
  )
}
