import { Link } from "react-router"
import { DepartmentCard, DepartmentRow } from "@/features/department/components/departmentRow"
import { DepartmentFiltersBar } from "@/features/department/components/departmentFiltersBar"
import { Pagination } from "@/components/pagination"
import { StatsCards } from "@/features/department/components/statsCard"
import { useDepartmentList } from "@/features/department/hooks/useDepartmentList"

const headers = ["Kode", "Nama Divisi", "Unit", "Jumlah Karyawan", "Status", "Kepala Unit Penanggung Jawab", "Terakhir Diperbarui", "Aksi"]

export default function DepartmentList() {
  const l = useDepartmentList()

  return (
    <div className="flex flex-col gap-4 p-4 md:p-6">
      <header className="flex items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold">Divisi</h1>
          <p className="text-xs text-muted-foreground">Kelola divisi dan penanggung jawab organisasi PT Pride Chicken</p>
        </div>
        <Link to="/department/new" className="hidden h-9 items-center rounded-md bg-red-700 px-3 text-sm font-medium text-white hover:bg-red-800 sm:inline-flex md:hidden">
          + Tambah
        </Link>
      </header>

      <StatsCards stats={l.stats} />
      <DepartmentFiltersBar filters={l.filters} onChange={l.updateFilters} />

      {l.error && (
        <div role="alert" className="flex items-center justify-between rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
          <span>{l.error}</span>
          <button onClick={l.reload} className="font-medium underline">Coba lagi</button>
        </div>
      )}

      <section className="rounded-lg border bg-white">
        <div className="flex items-center gap-2 border-b px-3 py-3">
          <h2 className="text-sm font-semibold">Daftar Divisi</h2>
          <span className="text-xs text-muted-foreground">{l.total} divisi</span>
        </div>

        {l.loading ? (
          <p className="p-6 text-center text-sm text-muted-foreground">Memuat…</p>
        ) : l.total === 0 ? (
          <p className="p-6 text-center text-sm text-muted-foreground">Tidak ada divisi yang cocok.</p>
        ) : (
          <>
            {/* Desktop: tabel */}
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full min-w-[900px] text-left">
                <thead className="bg-gray-50 text-xs uppercase text-muted-foreground">
                  <tr>
                    {headers.map((h, i) => (
                      <th key={h} scope="col" className={`px-3 py-2 font-medium ${i === headers.length - 1 ? "text-right" : ""}`}>{h}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {l.pageItems.map((d) => (
                    <DepartmentRow key={d.id} dept={d} onToggle={l.toggleStatus} />
                  ))}
                </tbody>
              </table>
            </div>

            {/* Mobile: kartu */}
            <ul className="flex flex-col gap-3 p-3 md:hidden">
              {l.pageItems.map((d) => (
                <DepartmentCard key={d.id} dept={d} onToggle={l.toggleStatus} />
              ))}
            </ul>

            <Pagination page={l.page} totalPages={l.totalPages} from={l.from} to={l.to} total={l.total} onPage={l.setPage} />
          </>
        )}
      </section>
    </div>
  )
}
