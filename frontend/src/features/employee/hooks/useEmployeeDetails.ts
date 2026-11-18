import { useAsync } from "@/hooks/useAsync"
import { employeeService } from "@/features/employee/services/employee.api.Service"

export function useEmployeeDetail(id?: string) {
  const { data, error } = useAsync(
    async () => ({ id, detail: id ? await employeeService.getDetail(id) : null }),
    [id],
    !!id,
  )
  const loading = !!id && data?.id !== id
  return { detail: loading ? null : (data?.detail ?? null), loading, error }
}
