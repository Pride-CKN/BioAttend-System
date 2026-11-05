import { useCallback, useEffect, useState } from "react"
import { useNavigate } from "react-router"
import type { Department, DepartmentInput } from "@/features/department/department.type"
import { loadDepartment, saveDepartment } from "@/features/department/departmentsAction.Service"
import { departmentService } from "@/features/department/deparment.Service"

export function useDepartmentForm(id?: string) {
  const navigate = useNavigate()
  const [initial, setInitial] = useState<DepartmentInput | null>(null)
  const [current, setCurrent] = useState<Department | null>(null)
  const [notFound, setNotFound] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    const run = async () => {
      if (id) {
        const dept = await loadDepartment(id)
        if (cancelled) return
        if (!dept) return setNotFound(true)
        setCurrent(dept)
        setInitial({ code: dept.code, name: dept.name, division: dept.division, status: dept.status, headId: dept.headId })
      } else {
        const code = await departmentService.nextCode()
        if (!cancelled) setInitial({ code, name: "", division: "", status: "Aktif", headId: null })
      }
    }
    run().catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Gagal memuat data"))
    return () => {
      cancelled = true
    }
  }, [id])

  const save = useCallback(
    async (input: DepartmentInput) => {
      setSaving(true)
      setError(null)
      try {
        await saveDepartment(id, input)
        navigate("/department")
      } catch (e) {
        setError(e instanceof Error ? e.message : "Gagal menyimpan")
        setSaving(false)
      }
    },
    [id, navigate],
  )

  return { initial, current, notFound, saving, error, save, isEdit: Boolean(id) }
}
