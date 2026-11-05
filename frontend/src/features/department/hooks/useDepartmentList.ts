import { useCallback, useEffect, useMemo, useState } from "react"
import type { DepartmentFilters, DepartmentStatus } from "@/features/department/department.type"
import { changeDepartmentStatus, loadDepartments } from "@/features/department/departmentsAction.Service"
import { DEPARTMENT_PAGE_SIZE, SEARCH_DEBOUNCE_MS } from "@/features/department/lib/departmentConfig"
import { useDepartmentStore } from "@/features/department/department.store"
import { headName } from "@/features/department/lib/department.Utils"
import { useDebouncedValue } from "@/hooks/useDebouncedValue"

const errMsg = (e: unknown) => (e instanceof Error ? e.message : "Terjadi kesalahan")

export function useDepartmentList() {
  const items = useDepartmentStore((s) => s.items)
  const stats = useDepartmentStore((s) => s.stats)
  const loaded = useDepartmentStore((s) => s.loaded)

  const [filters, setFilters] = useState<DepartmentFilters>({ search: "", division: "", status: "" })
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)
  const search = useDebouncedValue(filters.search, SEARCH_DEBOUNCE_MS)

  const reload = useCallback(() => {
    setError(null)
    loadDepartments(true).catch((e) => setError(errMsg(e)))
  }, [])

  useEffect(() => {
    loadDepartments().catch((e) => setError(errMsg(e)))
  }, [])

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    return items.filter(
      (d) =>
        (!filters.division || d.division === filters.division) &&
        (!filters.status || d.status === filters.status) &&
        (!q || `${d.code} ${d.name} ${d.division} ${headName(d.headId)}`.toLowerCase().includes(q)),
    )
  }, [items, search, filters.division, filters.status])

  const totalPages = Math.max(1, Math.ceil(filtered.length / DEPARTMENT_PAGE_SIZE))
  const safePage = Math.min(page, totalPages)
  const start = (safePage - 1) * DEPARTMENT_PAGE_SIZE
  const pageItems = useMemo(() => filtered.slice(start, start + DEPARTMENT_PAGE_SIZE), [filtered, start])

  const updateFilters = useCallback((patch: Partial<DepartmentFilters>) => {
    setFilters((f) => ({ ...f, ...patch }))
    setPage(1)
  }, [])

  // Referensi stabil → baris ber-memo tidak re-render saat state lain berubah.
  const toggleStatus = useCallback((id: string, status: DepartmentStatus) => {
    setError(null)
    changeDepartmentStatus(id, status).catch((e) => setError(errMsg(e)))
  }, [])

  return {
    stats,
    loading: !loaded && !error,
    error,
    reload,
    filters,
    updateFilters,
    pageItems,
    total: filtered.length,
    page: safePage,
    totalPages,
    from: filtered.length ? start + 1 : 0,
    to: start + pageItems.length,
    setPage,
    toggleStatus,
  }
}
