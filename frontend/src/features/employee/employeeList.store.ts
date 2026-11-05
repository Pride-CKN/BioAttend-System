import { createStore } from "@/lib/createStore"
import type { EmployeeFilters } from "@/features/employee/employee.type"

export const DEFAULT_FILTERS: EmployeeFilters = { keyword: "", dept: "", unit: "", status: "", shift: "" }

type State = {
  filters: EmployeeFilters
  page: number
  view: "table" | "card"
  selected: ReadonlySet<string>
  setFilter: <K extends keyof EmployeeFilters>(key: K, value: EmployeeFilters[K]) => void
  resetFilters: () => void
  setPage: (page: number) => void
  setView: (view: "table" | "card") => void
  toggleOne: (id: string) => void
  setMany: (ids: string[], on: boolean) => void
  clearSelection: () => void
}

export const useEmployeeListStore = createStore<State>((set) => ({
  filters: DEFAULT_FILTERS,
  page: 1,
  view: "table",
  selected: new Set<string>(),

  setFilter: (key, value) => set((s) => ({ filters: { ...s.filters, [key]: value }, page: 1 })),
  resetFilters: () => set({ filters: DEFAULT_FILTERS, page: 1 }),
  setPage: (page) => set({ page }),
  setView: (view) => set({ view }),

  toggleOne: (id) =>
    set((s) => {
      const next = new Set(s.selected)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return { selected: next }
    }),
  setMany: (ids, on) =>
    set((s) => {
      const next = new Set(s.selected)
      ids.forEach((id) => (on ? next.add(id) : next.delete(id)))
      return { selected: next }
    }),
  clearSelection: () => set({ selected: new Set<string>() }),
}))
