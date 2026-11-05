import { useCallback, useMemo } from "react"
import { useNavigate } from "react-router"
import { useAsync } from "@/hooks/useAsync"
import { useDebouncedValue } from "@/hooks/useDebouncedValue"
import { employeeService } from "@/features/employee/employee.Service"
import { useEmployeeListStore } from "@/features/employee/employeeList.store"
import { PAGE_SIZE } from "@/features/employee/lib/employee.Option"
import type { BulkAction, Employee } from "@/features/employee/employee.type"

export function useEmployeeList() {
  const navigate = useNavigate()

  const filters = useEmployeeListStore((s) => s.filters)
  const page = useEmployeeListStore((s) => s.page)
  const view = useEmployeeListStore((s) => s.view)
  const selected = useEmployeeListStore((s) => s.selected)
  const { setFilter, resetFilters, setPage, setView, toggleOne, setMany, clearSelection } =
    useEmployeeListStore.getState()

  const keyword = useDebouncedValue(filters.keyword, 250)
  const query = useMemo(
    () => ({ ...filters, keyword, page, pageSize: PAGE_SIZE }),
    [filters, keyword, page],
  )

  const list = useAsync(() => employeeService.list(query), [query])
  const stats = useAsync(() => employeeService.stats(), [])

  const items = list.data?.items ?? []
  const allSelected = items.length > 0 && items.every((e) => selected.has(e.id))

  const toggleAll = useCallback(
    () => setMany(items.map((e) => e.id), !allSelected),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [items, allSelected],
  )

  const refresh = useCallback(() => {
    list.reload()
    stats.reload()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [list.reload, stats.reload])

  const actions = useMemo(
    () => ({
      onView: (e: Employee) => navigate(`/employee/${e.id}`),
      onEdit: (e: Employee) => navigate(`/employee/${e.id}/edit`),
      onDelete: async (e: Employee) => {
        await employeeService.remove([e.id])
        refresh()
      },
    }),
    [navigate, refresh],
  )

  const runBulk = useCallback(
    async (action: BulkAction) => {
      await employeeService.bulk(action, [...useEmployeeListStore.getState().selected])
      if (action !== "export") {
        clearSelection()
        refresh()
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [refresh],
  )

  return {
    filters, setFilter, resetFilters,
    page: list.data?.page ?? page, setPage,
    pageCount: list.data?.pageCount ?? 1,
    total: list.data?.total ?? 0,
    items,
    loading: list.loading,
    error: list.error,
    stats: stats.data,
    view, setView,
    selected, allSelected, toggleAll, toggleOne, clearSelection,
    actions,
    runBulk,
    goAdd: () => navigate("/employee/new"),
    exportAll: () => employeeService.bulk("export", items.map((e) => e.id)),
  }
}
