import { useCallback, useEffect, useMemo, useState } from "react"
import { createPosition, getPositionHolders, getPositions, setPositionActive, updatePosition } from "../services/position.service"
import type { Position, PositionFilters, PositionHolder, PositionInput } from "../Position.types"

const defaultFilters: PositionFilters = { search: "", level: "all", status: "all" }

export function usePositions(initialPageSize = 10) {
  const [positions, setPositions] = useState<Position[]>([])
  const [holders, setHolders] = useState<PositionHolder[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [filters, setFilters] = useState<PositionFilters>(defaultFilters)
  const [page, setPage] = useState(1)
  const [pageSize, setPageSizeState] = useState(initialPageSize)

  const refresh = useCallback(async () => {
    setLoading(true); setError(null)
    try {
      const next = await getPositions()
      setPositions(next)
      const holderLists = await Promise.all(next.map((item) => getPositionHolders(item.id)))
      setHolders(holderLists.flat())
    } catch { setError("Gagal memuat data jabatan.") } finally { setLoading(false) }
  }, [])

  useEffect(() => { void refresh() }, [refresh])

  const savePosition = useCallback(async (input: PositionInput, id?: string) => {
    const saved = id ? await updatePosition(id, input) : await createPosition(input)
    setPositions((current) =>
      id
        ? current.map((item) => item.id === id ? { ...saved, holderCount: item.holderCount } : item)
        : [...current, saved] // new position has 0 holders
    )
    return saved
  }, [])

  const filtered = useMemo(() => {
    const keyword = filters.search.trim().toLowerCase()
    return positions
      .filter((item) =>
        (!keyword || `${item.name} ${item.code}`.toLowerCase().includes(keyword)) &&
        (filters.level === "all" || String(item.level) === filters.level) &&
        (filters.status === "all" || (filters.status === "active") === item.active))
      .sort((a, b) => a.level - b.level || a.code.localeCompare(b.code))
  }, [positions, filters])

  const total = filtered.length
  const pageCount = Math.max(1, Math.ceil(total / pageSize))
  const currentPage = Math.min(page, pageCount)
  const items = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize)

  const updateFilter = useCallback(<K extends keyof PositionFilters>(key: K, value: PositionFilters[K]) => {
    setFilters((c) => ({ ...c, [key]: value })); setPage(1)
  }, [])
  const resetFilters = useCallback(() => { setFilters(defaultFilters); setPage(1) }, [])
  const setPageSize = useCallback((n: number) => { setPageSizeState(n); setPage(1) }, [])

  const toggleActive = useCallback(async (id: string, active: boolean) => {
    const updated = await setPositionActive(id, active)
    setPositions((current) =>
      current.map((item) => item.id === id ? { ...updated, holderCount: item.holderCount } : item)
    )
  }, [])

  return {
    all: positions, items, holders, total,
    page: currentPage, pageCount, pageSize,
    from: total === 0 ? 0 : (currentPage - 1) * pageSize + 1,
    to: Math.min(currentPage * pageSize, total),
    filters, loading, error,
    setPage, setPageSize, updateFilter, resetFilters, refresh, savePosition, toggleActive,
  }
}