import { Link } from "react-router";
import { Plus } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DivisionCard,
  DivisionRow,
} from "@/features/division/components/divisionRow";
import { DivisionFiltersBar } from "@/features/division/components/divisionFiltersBar";
import { Pagination } from "@/components/pagination";
import { Card, CardContent } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { StatsCards } from "@/features/division/components/statsCard";
import { useDivisionList } from "@/features/division/hooks/useDivisionList";
import { PageBreadcrumb } from "@/components/page-breadcrumb";

const headers = [
  "Kode",
  "Nama Division",
  "Unit",
  "Status",
  "KADIV ID",
  "Terakhir Diperbarui",
  "Aksi",
];

export default function DivisionList() {
  const l = useDivisionList();

  return (
    <div className="mx-auto flex w-full max-w-280 flex-col gap-4 pb-10">
      <header className="flex items-start justify-between gap-3">
        <div>
          <PageBreadcrumb items={[
            { label: "Dashboard" }, 
            { label: "Divisi & Unit" }
            ]} className="mb-1" />
          <h1 className="text-xl font-bold">Divisi & Unit</h1>
          <p className="text-xs text-muted-foreground">
            Kelola divisi dan penanggung jawab organisasi PT Pride Chicken
          </p>
        </div>
      </header>

      <StatsCards stats={l.stats} />
      <DivisionFiltersBar
        filters={l.filters}
        units={l.units}
        onChange={l.updateFilters}
      />

      {l.error && (
        <div
          role="alert"
          className="flex items-center justify-between rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          <span>{l.error}</span>
          <Button
            variant="link"
            size="sm"
            className="h-auto p-0 text-red-700"
            onClick={l.reload}
          >
            Coba lagi
          </Button>
        </div>
      )}

      <Card className="overflow-hidden py-0">
        <div className="flex items-center gap-2 border-b px-4 py-3">
          <div>
            <h2 className="text-sm font-semibold">Daftar Divisi</h2>
            <span className="text-xs text-muted-foreground">
              {l.total} divisi
            </span>
          </div>
          <div className="flex w-full gap-2 sm:ml-auto sm:w-auto">
            <Button size="sm" variant="outline" className="rounded-full border-[#080808] bg-transparent px-4 text-[#080808] transition duration-200 hover:-translate-y-0.5 hover:border-[#EA232A] hover:bg-white hover:text-[#EA232A] active:translate-y-0" render={<Link to="/division/new" />}>
              <Plus />
              Tambah Divisi
            </Button>
          </div>
        </div>

        <CardContent className="p-0">
          {l.loading ? (
            <p className="p-8 text-center text-sm text-muted-foreground">
              Memuat…
            </p>
          ) : l.total === 0 ? (
            <p className="p-8 text-center text-sm text-muted-foreground">
              Tidak ada divisi yang cocok.
            </p>
          ) : (
            <>
              <div className="hidden md:block">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-muted/50 hover:bg-muted/50">
                      {headers.map((header) => (
                        <TableHead
                          key={header}
                          className={`text-xs font-semibold uppercase tracking-wide text-muted-foreground ${header === "Aksi" ? "text-center" : ""}`}
                        >
                          {header}
                        </TableHead>
                      ))}
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {l.pageItems.map((division) => (
                      <DivisionRow
                        key={division.id}
                        div={division}
                        onToggle={l.toggleStatus}
                      />
                    ))}
                  </TableBody>
                </Table>
              </div>
              <ul className="flex flex-col gap-3 p-3 md:hidden">
                {l.pageItems.map((division) => (
                  <DivisionCard
                    key={division.id}
                    div={division}
                    onToggle={l.toggleStatus}
                  />
                ))}
              </ul>
              <Pagination
                page={l.page}
                totalPages={l.totalPages}
                from={l.from}
                to={l.to}
                total={l.total}
                onPage={l.setPage}
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
