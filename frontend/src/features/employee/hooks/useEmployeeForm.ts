import { useCallback, useMemo, useState } from "react"
import { useNavigate } from "react-router"
import { employeeService } from "@/features/employee/employee.Service"
import { emptyFormValues, formValuesFrom } from "@/features/employee/lib/employeeFormState"
import { isPlacementValid, validateEmployeeForm } from "@/features/employee/employeeValidation"
import { divisionsByDept, positionsByDept } from "@/features/employee/lib/employee.Option"
import { usePinAvailability } from "@/features/employee/hooks/usePinAvailability"
import type { EmployeeDetail, EmployeeFormErrors, EmployeeFormValues } from "@/features/employee/employee.type"

export function useEmployeeForm(detail?: EmployeeDetail | null) {
  const navigate = useNavigate()
  const employee = detail?.employee
  const isEdit = !!employee
  const backTo = isEdit ? `/employee/${employee!.id}` : "/employee"

  const [form, setForm] = useState<EmployeeFormValues>(() =>
    detail ? formValuesFrom(detail.employee, detail.profile) : emptyFormValues(),
  )
  const [errors, setErrors] = useState<EmployeeFormErrors>({})
  const [saving, setSaving] = useState(false)
  const employeeId = useMemo(() => employee?.id ?? employeeService.nextId(), [employee])

  const { owner: pinOwner } = usePinAvailability(form.pin, employee?.id)
  const placementValid = isPlacementValid(form)

  const set = useCallback(<K extends keyof EmployeeFormValues>(key: K, value: EmployeeFormValues[K]) => {
    setForm((p) => ({ ...p, [key]: value }))
    setErrors((p) => (p[key] ? { ...p, [key]: undefined } : p))
  }, [])

  const changeDepartment = useCallback((dept: string) => {
    setForm((p) => ({
      ...p,
      department: dept,
      division: divisionsByDept[dept]?.[0] ?? "",
      position: positionsByDept[dept]?.[0] ?? "",
    }))
  }, [])

  const save = async (mode: "draft" | "aktif") => {
    if (mode === "aktif") {
      const taken = !!(await employeeService.findPinOwner(form.pin, employee?.id))
      const e = validateEmployeeForm(form, { isEdit, pinTaken: taken })
      setErrors(e)
      if (Object.keys(e).length > 0) {
        window.scrollTo({ top: 0, behavior: "smooth" })
        return
      }
    }
    setSaving(true)
    try {
      await employeeService.save(employee?.id, form, mode)
      navigate(backTo)
    } finally {
      setSaving(false)
    }
  }

  return {
    form, errors, errorCount: Object.values(errors).filter(Boolean).length,
    isEdit, employeeId, backTo, saving,
    pinOwner, placementValid,
    set, changeDepartment, save,
    cancel: () => navigate(backTo),
  }
}

export type EmployeeFormApi = Pick<ReturnType<typeof useEmployeeForm>, "form" | "errors" | "set" | "changeDepartment" | "isEdit">
