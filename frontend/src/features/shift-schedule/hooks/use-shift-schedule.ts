import { useCallback, useEffect, useState } from "react"
import type { EmployeeSchedule, ScheduleHistory, Shift, WeeklyTemplate } from "@/features/shift-schedule/types"
import { assignEmployeeSchedule, bulkAssignSchedules, createShift, duplicateShift, getEmployeeSchedules, getScheduleHistory, getShifts, getWeeklyTemplates, updateShift, updateShiftStatus, type ShiftInput } from "../services/shift-schedule-service"

// Mengelola data shift, jadwal karyawan, dan aksi perubahan status shift.
export function useShiftSchedule(date: string) {
  const [shifts, setShifts] = useState<Shift[]>([])
  const [schedules, setSchedules] = useState<EmployeeSchedule[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [templates, setTemplates] = useState<WeeklyTemplate[]>([])
  const [history, setHistory] = useState<ScheduleHistory[]>([])

  const refresh = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [nextShifts, nextSchedules, nextTemplates, nextHistory] = await Promise.all([
        getShifts(),
        getEmployeeSchedules(date),
        getWeeklyTemplates(),
        getScheduleHistory(),
      ])
      setShifts(nextShifts)
      setSchedules(nextSchedules)
      setTemplates(nextTemplates)
      setHistory(nextHistory)
    } catch {
      setError("Gagal memuat data shift dan jadwal.")
    } finally {
      setLoading(false)
    }
  }, [date])

  useEffect(() => {
    const timer = window.setTimeout(() => void refresh(), 0)
    return () => window.clearTimeout(timer)
  }, [refresh])

  const toggleShift = useCallback(async (id: string, active: boolean) => {
    const updated = await updateShiftStatus(id, active ? "active" : "inactive")
    setShifts((current) => current.map((shift) => shift.id === id ? updated : shift))
  }, [])

  const copyShift = useCallback(async (id: string) => {
    const created = await duplicateShift(id)
    setShifts((current) => [...current, created])
  }, [])

  const saveShift = useCallback(async (input: ShiftInput, id?: string) => {
    const saved = id ? await updateShift(id, input) : await createShift(input)
    setShifts((current) => id ? current.map((shift) => shift.id === id ? saved : shift) : [...current, saved])
    return saved
  }, [])

  const assignSchedule = useCallback(async (input: Omit<EmployeeSchedule, "id">) => {
    const saved = await assignEmployeeSchedule(input)
    setSchedules((current) => {
      const found = current.some((item) => item.id === saved.id)
      return found ? current.map((item) => item.id === saved.id ? saved : item) : [...current, saved]
    })
    return saved
  }, [])

  const bulkAssign = useCallback(async (employees: string[], shiftId: string) => {
    const saved = await bulkAssignSchedules(employees, shiftId, date)
    setSchedules((current) => {
      const byId = new Map(current.map((item) => [item.id, item]))
      saved.forEach((item) => byId.set(item.id, item))
      return [...byId.values()]
    })
  }, [date])

  return { shifts, schedules, templates, history, loading, error, refresh, toggleShift, copyShift, saveShift, assignSchedule, bulkAssign }
}
