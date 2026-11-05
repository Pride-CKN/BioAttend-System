import { useEmployeeList } from "@/features/employee/hooks/useEmployeeList"
import { EmployeeStatCards } from "@/features/employee/components/list/employeeStatCards"
import { EmployeeFilterBar } from "@/features/employee/components/list/employeeFilterBar"
import { EmployeeBulkBar } from "@/features/employee/components/list/employeeBulkBar"
import { EmployeeTable } from "@/features/employee/components/list/employeeTable"
import { EmployeeCardGrid } from "@/features/employee/components/list/employeeCardGrid"
import { Pagination } from "@/components/shared"
import { PAGE_SIZE } from "@/features/employee/lib/employee.Option"

/** Composition only: all behaviour lives in useEmployeeList, all markup in components/list. */
export default function EmployeeList() {
  const vm = useEmployeeList()
  const from = vm.items.length === 0 ? 0 : (vm.page - 1) * PAGE_SIZE + 1
  const to = vm.items.length === 0 ? 0 : from + vm.items.length - 1

  return (
    <div className="@container min-h-screen min-w-0 flex-1 space-y-3 bg-white p-3 sm:space-y-4 sm:p-4 lg:p-6 2xl:p-8">
      <div>
        <p className="text-xs text-muted-foreground">
          Dashboard / Master Data / <span className="font-semibold text-foreground">Employee</span>
        </p>
        <h1 className="text-xl font-bold">Daftar Karyawan</h1>
        <p className="text-xs text-muted-foreground">Kelola data karyawan, PIN fingerprint, dan status kepegawaian</p>
      </div>

      <EmployeeStatCards stats={vm.stats} />
      <EmployeeFilterBar filters={vm.filters} onChange={vm.setFilter} onExport={vm.exportAll} onAdd={vm.goAdd} />
      <EmployeeBulkBar count={vm.selected.size} onAction={vm.runBulk} onClear={vm.clearSelection} />

      <div className="overflow-hidden rounded-lg border bg-white" aria-busy={vm.loading}>
        <div className="flex items-center justify-between px-4 py-3">
          <div className="flex items-baseline gap-2">
            <h2 className="text-sm font-bold">Daftar Karyawan</h2>
            <span className="text-xs text-muted-foreground">{vm.total} karyawan</span>
          </div>
          <div className="flex rounded-md border p-0.5 text-xs">
            {(["table", "card"] as const).map((v) => (
              <button
                key={v}
                onClick={() => vm.setView(v)}
                className={`rounded px-3 py-1 font-semibold capitalize ${vm.view === v ? "bg-white text-red-700 shadow-sm" : "text-muted-foreground"}`}
              >
                {v}
              </button>
            ))}
          </div>
        </div>

        <div className={vm.loading ? "opacity-60 transition-opacity" : ""}>
          {vm.view === "table" ? (
            <EmployeeTable
              items={vm.items}
              selected={vm.selected}
              allSelected={vm.allSelected}
              onToggleAll={vm.toggleAll}
              onToggle={vm.toggleOne}
              actions={vm.actions}
            />
          ) : (
            <EmployeeCardGrid items={vm.items} actions={vm.actions} />
          )}
        </div>

        <div className="flex items-center justify-between border-t px-4 py-3 text-xs text-muted-foreground">
          <span>Menampilkan {from}–{to} dari {vm.total} karyawan</span>
          <Pagination page={vm.page} pageCount={vm.pageCount} onChange={vm.setPage} />
        </div>
      </div>
    </div>
  )
}
