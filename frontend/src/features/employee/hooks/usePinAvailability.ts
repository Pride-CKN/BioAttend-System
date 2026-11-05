import { useAsync } from "@/hooks/useAsync"
import { useDebouncedValue } from "@/hooks/useDebouncedValue"
import { employeeService } from "@/features/employee/employee.Service"

export function usePinAvailability(pin: string, exceptId?: string) {
  const debounced = useDebouncedValue(pin, 300)
  const { data } = useAsync(
    async () => ({ pin: debounced, owner: await employeeService.findPinOwner(debounced, exceptId) }),
    [debounced, exceptId],
    !!debounced,
  )
  return { owner: pin && data?.pin === pin ? data.owner : undefined }
}
