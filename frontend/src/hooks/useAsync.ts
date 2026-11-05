import { useCallback, useEffect, useRef, useState } from "react"

type State<T> = { data: T | undefined; error: unknown; loading: boolean }

export function useAsync<T>(fn: () => Promise<T>, deps: readonly unknown[], enabled = true) {
  const [state, setState] = useState<State<T>>({ data: undefined, error: undefined, loading: enabled })
  const [tick, setTick] = useState(0)
  const fnRef = useRef(fn)
  useEffect(() => {
    fnRef.current = fn
  }, [fn])

  useEffect(() => {
    if (!enabled) return
    let alive = true
    // The loading transition belongs to the async operation started by this effect.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setState((s) => (s.loading ? s : { ...s, loading: true, error: undefined }))
    fnRef.current().then(
      (data) => alive && setState({ data, error: undefined, loading: false }),
      (error) => alive && setState((s) => ({ ...s, error, loading: false })),
    )
    return () => {
      alive = false
    }
  // The caller controls re-fetching through deps; fn is kept current in fnRef.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, tick, enabled])

  const reload = useCallback(() => setTick((t) => t + 1), [])
  return { ...state, reload }
}
