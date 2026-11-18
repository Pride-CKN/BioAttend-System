import { useCallback, useEffect, useState } from "react"
import { useNavigate } from "react-router"
import { employeeService } from "@/features/employee/services/employee.api.Service"
import { emptyFormValues, formValuesFrom } from "@/features/employee/lib/employeeFormState"
import { isPlacementValid, validateEmployeeForm } from "@/features/employee/validation/employeeValidation"
import { usePinAvailability } from "@/features/employee/hooks/usePinAvailability"
import type { EmployeeDetail, EmployeeFormErrors, EmployeeFormOptions, EmployeeFormValues } from "@/features/employee/types/employee.type"

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
  const [options, setOptions] = useState<EmployeeFormOptions>({ offices: [], divisions: [], units: [], positions: [], managers: [] })
  const [optionsError, setOptionsError] = useState<string | null>(null)
  const [activation, setActivation] = useState<{ token: string; expiresAt: string } | null>(null)
  const employeeId = employee?.employeeCode ?? "Dibuat saat disimpan"

  useEffect(() => {
    let cancelled = false
    employeeService.getFormOptions()
      .then((nextOptions) => {
        if (cancelled) return
        const filteredDivisions = nextOptions.divisions.filter(
          (item) =>
            !item.name?.toLowerCase().includes("direktur") &&
            !item.name?.toLowerCase().includes("(dir)") &&
            item.code?.toUpperCase() !== "DIR"
        )
  
        const sanitizedOptions = {
          ...nextOptions,
          divisions: filteredDivisions,
        }
        setOptions(sanitizedOptions)
        setForm((current) => {
          const office = sanitizedOptions.offices.find((item) => item.id === current.officeId) ?? sanitizedOptions.offices[0]
          const division = filteredDivisions.find((item) => item.id === current.divisionId) ?? filteredDivisions[0]
          const units = sanitizedOptions.units.filter((item) => item.divisionId === division?.id)
          const unit = units.find((item) => item.id === current.unitId)
          const position = sanitizedOptions.positions.find((item) => item.id === current.positionId)
          const manager = sanitizedOptions.managers.find((item) => item.id === current.managerId)
          return {
            ...current,
            officeId: office?.id ?? "",
            office: office?.name ?? "",
            divisionId: division?.id ?? "",
            division: division?.name ?? "",
            unitId: unit?.id ?? "",
            unit: unit?.name ?? "",
            positionId: position?.id ?? "",
            position: position?.name ?? "",
            managerId: manager?.id ?? "",
            supervisor: manager?.name ?? "",
          }
        })
      })
      .catch((error: unknown) => {
        if (!cancelled) setOptionsError(error instanceof Error ? error.message : "Gagal memuat pilihan karyawan.")
      })
    return () => { cancelled = true }
  }, [])

  const { owner: pinOwner } = usePinAvailability(form.autoGenerateEmployeeCode ? "" : form.pin, employee?.id)
  const placementValid = isPlacementValid(form)

  const set = useCallback(<K extends keyof EmployeeFormValues>(key: K, value: EmployeeFormValues[K]) => {
    setForm((p) => ({ ...p, [key]: value }))
    setErrors((p) => (p[key] ? { ...p, [key]: undefined } : p))
  }, [])

  const changeOffice = useCallback((name: string) => {
    const office = options.offices.find((item) => item.name === name)
    set("office", name)
    set("officeId", office?.id ?? "")
  }, [options.offices, set])

  const changeDivision = useCallback((name: string) => {
    const division = options.divisions.find((item) => item.name === name)
    const unit = options.units.find((item) => item.divisionId === division?.id)
    const position = options.positions[0]
    setForm((current) => ({
      ...current,
      division: name,
      divisionId: division?.id ?? "",
      unit: unit?.name ?? "",
      unitId: unit?.id ?? "",
      position: position?.name ?? "",
      positionId: position?.id ?? "",
      supervisor: "",
      managerId: "",
    }))
  }, [options.divisions, options.positions, options.units])

  const changeUnit = useCallback((name: string) => {
    const unit = options.units.find((item) => item.name === name && item.divisionId === form.divisionId)
    setForm((current) => ({
      ...current,
      unit: name,
      unitId: unit?.id ?? "",
      supervisor: "",
      managerId: "",
    }))
  }, [form.divisionId, options.units])

  const changePosition = useCallback((name: string) => {
    const position = options.positions.find((item) => item.name === name)
    set("position", name)
    set("positionId", position?.id ?? "")
  }, [options.positions, set])

  const changeManager = useCallback((name: string) => {
    const manager = options.managers.find((item) => item.name === name)
    set("supervisor", name)
    set("managerId", manager?.id ?? "")
  }, [options.managers, set])

  const save = async (mode: "draft" | "aktif") => {
    setActivation(null)
    const isEditMode = Boolean(employee?.id)
    const mustValidatePin = isEditMode || !form.autoGenerateEmployeeCode
  
    // Hanya hit API jika format PIN sudah valid 6 digit
    const taken =
      mustValidatePin && /^\d{6}$/.test(form.pin.trim())
        ? !!(await employeeService.findPinOwner(form.pin.trim(), employee?.id))
        : false
  
    // Validasi tetap jalan baik untuk "aktif" maupun "draft"
    const e = validateEmployeeForm(form, { isEdit: isEditMode, pinTaken: taken })
    setErrors(e)
    if (Object.keys(e).length > 0) {
      window.scrollTo({ top: 0, behavior: "smooth" })
      return
    }
  
    setSaving(true)
    try {
      const result = await employeeService.save(employee?.id, form, mode)
      if (result.activation) setActivation(result.activation)
      else navigate(backTo)
    } finally {
      setSaving(false)
    }
  }

  return {
    form, errors, errorCount: Object.values(errors).filter(Boolean).length,
    options, optionsError,
    isEdit, employeeId, backTo, saving, activation,
    pinOwner, placementValid,
    set, changeOffice, changeDivision, changeUnit, changePosition, changeManager, save,
    cancel: () => navigate(backTo),
  }
}

export type EmployeeFormApi = Pick<ReturnType<typeof useEmployeeForm>, "form" | "errors" | "set" | "options" | "changeOffice" | "changeDivision" | "changeUnit" | "changePosition" | "changeManager" | "isEdit">
