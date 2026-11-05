import { useCallback, useEffect, useState } from "react"
import type { AttendanceCorrection, CorrectionStatus } from "@/features/attendance-correction/types"
import {
  createAttendanceCorrection,
  getAttendanceCorrections,
  updateCorrectionsStatus,
  updateCorrectionStatus,
} from "../services/attendance-correction-service"
import type { CreateAttendanceCorrectionInput } from "../services/attendance-correction-service"

// Mengelola pemuatan data koreksi dan pembaruan state tampilan.
export function useAttendanceCorrections() {
  const [corrections, setCorrections] = useState<AttendanceCorrection[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const refresh = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      setCorrections(await getAttendanceCorrections())
    } catch {
      setError("Gagal memuat pengajuan koreksi absensi.")
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    const timer = window.setTimeout(() => void refresh(), 0)
    return () => window.clearTimeout(timer)
  }, [refresh])

  const updateStatus = useCallback(
    async (id: string, status: CorrectionStatus, note?: string) => {
      const updated = await updateCorrectionStatus(id, status, note)
      setCorrections((current) =>
        current.map((correction) =>
          correction.id === id ? updated : correction,
        ),
      )
    },
    [],
  )

  const bulkApprove = useCallback(async (ids: string[]) => {
    const updated = await updateCorrectionsStatus(ids, "approved")
    setCorrections((current) =>
      current.map((correction) =>
        updated.find((item) => item.id === correction.id) ?? correction,
      ),
    )
  }, [])

  const createCorrection = useCallback(
    async (input: CreateAttendanceCorrectionInput) => {
      const created = await createAttendanceCorrection(input)
      setCorrections((current) => [created, ...current])
    },
    [],
  )

  return {
    corrections,
    loading,
    error,
    refresh,
    updateStatus,
    bulkApprove,
    createCorrection,
  }
}
