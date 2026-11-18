import { useNavigate, useParams } from "react-router"
import { Button } from "@/components/ui/button"
import { useEmployeeDetail } from "@/features/employee/hooks/useEmployeeDetails"
import { ProfileCard } from "@/features/employee/components/detail/profileCard"
import { InfoGrid } from "@/features/employee/components/detail/infoGrid"
import { NotFoundEmployee, PageLoading } from "@/NotFoundPage"
import type { EmployeeDetail as Detail } from "@/features/employee/types/employee.type"
import { PageBreadcrumb } from "@/components/page-breadcrumb"

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
  const edit = () => navigate(`/employee/${employee.id}/edit`)
  return (
    <div className="@container min-h-screen min-w-0 flex-1 space-y-3 bg-slate-50 p-3 sm:space-y-4 sm:p-4 lg:p-6 2xl:p-8">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <PageBreadcrumb items={[
            { label: "Dashboard"},
            { label: "Karyawan", to: "/employee" },
            { label: "Detail" }
          ]} className="mb-1" />
          <h1 className="text-xl font-bold">Detail Karyawan</h1>
          <p className="text-xs text-muted-foreground">Informasi identitas, kontak, dan penempatan kerja.</p>
        </div>
        <div className="flex w-full flex-wrap gap-2 sm:w-auto">
          <Button className="flex-1 sm:flex-none" size="sm" variant="outline" onClick={() => navigate("/employee")}>← Kembali</Button>
          <Button className="flex-1 sm:flex-none" size="sm" variant="outline" onClick={() => window.print()}>Cetak</Button>
        </div>
      </div>

      <ProfileCard detail={detail} onEdit={edit} />
      <InfoGrid detail={detail} />
    </div>
  )
}
