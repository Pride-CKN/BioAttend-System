import { useCallback, useEffect, useMemo, useState } from "react"
import type { DivisionFilters, DivisionStatus } from "@/features/division/types/division.type"
import { changeDivisionStatus, loadDivisions } from "@/features/division/services/divisionAction.Service"
import { DIVISION_PAGE_SIZE, SEARCH_DEBOUNCE_MS } from "@/features/division/lib/divisionConfig"
import { useDivisionStore } from "@/features/division/division.store"
import { headName } from "@/features/division/lib/division.Utils"
import { useDebouncedValue } from "@/hooks/useDebouncedValue"

const errMsg = (e: unknown) => (e instanceof Error ? e.message : "Terjadi kesalahan")

export function useDivisionList() {
  const items = useDivisionStore((s) => s.items)
  const stats = useDivisionStore((s) => s.stats)
  const loaded = useDivisionStore((s) => s.loaded)

  const [filters, setFilters] = useState<DivisionFilters>({ search: "", unit: "", status: "" })
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)
  const search = useDebouncedValue(filters.search, SEARCH_DEBOUNCE_MS)
  const units = useMemo(() => [...new Set(items.flatMap((division) => division.units.map((unit) => unit.name)))].sort(), [items])

  const reload = useCallback(() => {
    setError(null)
    loadDivisions(true).catch((e) => setError(errMsg(e)))
  }, [])

  useEffect(() => {
    loadDivisions().catch((e) => setError(errMsg(e)))
  }, [])

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase()
    return items.filter(
      (d) =>
        (!filters.unit || d.units.some((unit) => unit.name === filters.unit)) &&
        (!filters.status || d.status === filters.status) &&
        (!q || `${d.code} ${d.name} ${d.units.map((unit) => unit.name).join(" ")} ${headName(d.headId)}`.toLowerCase().includes(q)),
    )
  }, [items, search, filters.unit, filters.status])

  const totalPages = Math.max(1, Math.ceil(filtered.length / DIVISION_PAGE_SIZE))
  const safePage = Math.min(page, totalPages)
  const start = (safePage - 1) * DIVISION_PAGE_SIZE
  const pageItems = useMemo(() => filtered.slice(start, start + DIVISION_PAGE_SIZE), [filtered, start])

  const updateFilters = useCallback((patch: Partial<DivisionFilters>) => {
    setFilters((f) => ({ ...f, ...patch }))
    setPage(1)
  }, [])

  // Referensi stabil → baris ber-memo tidak re-render saat state lain berubah.
  const toggleStatus = useCallback((id: string, status: DivisionStatus) => {
    setError(null)
    changeDivisionStatus(id, status).catch((e) => setError(errMsg(e)))
  }, [])

  return {
    stats,
    units,
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

