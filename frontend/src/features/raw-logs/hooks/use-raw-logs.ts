import { useCallback, useEffect, useState } from "react"
import type { Device, RawLog } from "@/features/raw-logs/types"
import { getRawLogDevices, getRawLogs } from "../services/raw-logs-service"

const AUTO_REFRESH_MS = 30_000

export function useRawLogs(autoRefresh: boolean) {
  const [logs, setLogs] = useState<RawLog[]>([])
  const [devices, setDevices] = useState<Device[]>([])
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(async () => {
    setLoading(true)
    try {
      const [nextLogs, nextDevices] = await Promise.all([
        getRawLogs(),
        getRawLogDevices(),
      ])
      setLogs(nextLogs)
      setDevices(nextDevices)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    const id = setTimeout(() => void refresh(), 0)
    return () => clearTimeout(id)
  }, [refresh])

  useEffect(() => {
    if (!autoRefresh) return
    const id = setInterval(() => void refresh(), AUTO_REFRESH_MS)
    return () => clearInterval(id)
  }, [autoRefresh, refresh])

  return { devices, logs, loading }
}
