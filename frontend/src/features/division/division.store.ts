import { useSyncExternalStore } from "react"
import type { Division, DivisionStats } from "@/features/division/types/division.type"
import { computeStats } from "@/features/division/lib/division.Utils"

type State = { items: Division[]; stats: DivisionStats; loaded: boolean }

let state: State = { items: [], stats: computeStats([]), loaded: false }
const listeners = new Set<() => void>()

const commit = (items: Division[], loaded = true) => {
  state = { items, stats: computeStats(items), loaded }
  listeners.forEach((l) => l())
}

export const divisionStore = {
  getState: () => state,
  subscribe: (l: () => void) => {
    listeners.add(l)
    return () => listeners.delete(l)
  },
  setAll: (items: Division[]) => commit(items),
  upsert: (item: Division) => {
    const exists = state.items.some((d) => d.id === item.id)
    commit(exists ? state.items.map((d) => (d.id === item.id ? item : d)) : [...state.items, item])
  },
}

export function useDivisionStore<T>(selector: (s: State) => T): T {
  return useSyncExternalStore(divisionStore.subscribe, () => selector(state), () => selector(state))
}
