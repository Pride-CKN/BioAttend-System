import { useCallback, useEffect, useState } from "react"
import { useNavigate } from "react-router"
import type { Division, DivisionInput } from "@/features/division/types/division.type"
import { loadDivision, saveDivision } from "@/features/division/services/divisionAction.Service"
import { divisionService } from "@/features/division/services/division.Service"

export function useDivisionForm(id?: string) {
  const navigate = useNavigate()
  const [initial, setInitial] = useState<DivisionInput | null>(null)
  const [current, setCurrent] = useState<Division | null>(null)
  const [headOptions, setHeadOptions] = useState<Array<{ id: string; label: string }>>([])
  const [notFound, setNotFound] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    const run = async () => {
      const headsPromise = divisionService.listHeads()
      if (id) {
        const [division, heads] = await Promise.all([loadDivision(id), headsPromise])
        if (cancelled) return
        setHeadOptions(heads)
        if (!division) return setNotFound(true)
        setCurrent(division)
        setInitial({ code: division.code, name: division.name, status: division.status, headId: division.headId })
      } else {
        const [code, heads] = await Promise.all([divisionService.nextCode(), headsPromise])
        if (!cancelled) {
          setHeadOptions(heads)
          setInitial({ code, name: "", status: "Aktif", headId: null })
        }
      }
    }
    run().catch((e) => !cancelled && setError(e instanceof Error ? e.message : "Gagal memuat data"))
    return () => {
      cancelled = true
    }
  }, [id])

  const save = useCallback(
    async (input: DivisionInput) => {
      setSaving(true)
      setError(null)
      try {
        await saveDivision(id, input)
        navigate("/division")
      } catch (e) {
        setError(e instanceof Error ? e.message : "Gagal menyimpan")
        setSaving(false)
      }
    },
    [id, navigate],
  )

  return { initial, current, headOptions, notFound, saving, error, save, isEdit: Boolean(id) }
}
