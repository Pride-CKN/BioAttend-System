import { useSyncExternalStore } from "react"
import type { Department, DepartmentStats } from "@/features/department/department.type"
import { computeStats } from "@/features/department/lib/department.Utils"

type State = { items: Department[]; stats: DepartmentStats; loaded: boolean }

let state: State = { items: [], stats: computeStats([]), loaded: false }
const listeners = new Set<() => void>()

const commit = (items: Department[], loaded = true) => {
  state = { items, stats: computeStats(items), loaded }
  listeners.forEach((l) => l())
}

export const departmentStore = {
  getState: () => state,
  subscribe: (l: () => void) => {
    listeners.add(l)
    return () => listeners.delete(l)
  },
  setAll: (items: Department[]) => commit(items),
  upsert: (item: Department) => {
    const exists = state.items.some((d) => d.id === item.id)
    commit(exists ? state.items.map((d) => (d.id === item.id ? item : d)) : [...state.items, item])
  },
}

export function useDepartmentStore<T>(selector: (s: State) => T): T {
  return useSyncExternalStore(departmentStore.subscribe, () => selector(state), () => selector(state))
}
