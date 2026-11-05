import { useAsync } from "@/hooks/useAsync"
import { employeeService } from "@/features/employee/employee.Service"

export function useEmployeeDetail(id?: string) {
  const { data, error } = useAsync(
    async () => ({ id, detail: id ? await employeeService.getDetail(id) : null }),
    [id],
    !!id,
  )
  const loading = !!id && data?.id !== id
  return { detail: loading ? null : (data?.detail ?? null), loading, error }
}

/** Only fetched when the "Ringkasan" tab is visible — keeps first paint light. */
export function useAttendanceHistory(id: string, enabled: boolean) {
  return useAsync(() => employeeService.getAttendance(id), [id], enabled)
}

export function useDeductionHistory(id: string, enabled: boolean) {
  return useAsync(() => employeeService.getDeductionHistory(id), [id], enabled)
}
