import { useSyncExternalStore } from "react"

type Listener = () => void
type Partialish<S> = Partial<S> | ((s: S) => Partial<S>)

export function createStore<S extends object>(
  init: (set: (p: Partialish<S>) => void, get: () => S) => S,
) {
  const listeners = new Set<Listener>()
  let state: S

  const get = () => state
  const set = (p: Partialish<S>) => {
    const next = typeof p === "function" ? p(state) : p
    state = { ...state, ...next }
    listeners.forEach((l) => l())
  }
  const subscribe = (l: Listener) => {
    listeners.add(l)
    return () => listeners.delete(l)
  }

  state = init(set, get)

  function useStore<T>(selector: (s: S) => T): T {
    return useSyncExternalStore(
      subscribe,
      () => selector(state),
      () => selector(state),
    )
  }

  return Object.assign(useStore, { getState: get, setState: set, subscribe })
}
