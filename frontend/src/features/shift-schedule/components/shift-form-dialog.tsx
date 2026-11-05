import { useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import type { Shift } from "../types"
import type { ShiftInput } from "../services/shift-schedule-service"

const emptyForm: ShiftInput = {
  name: "",
  startTime: "08:00",
  endTime: "17:00",
  breakMinutes: 60,
  toleranceMinutes: 15,
  minimumWorkHours: 8,
  overnight: false,
}

export function ShiftFormDialog({
  open,
  shift,
  error,
  onOpenChange,
  onSubmit,
}: {
  open: boolean
  shift: Shift | null
  error: string | null
  onOpenChange: (open: boolean) => void
  onSubmit: (input: ShiftInput, id?: string) => Promise<void>
}) {
  const [form, setForm] = useState<ShiftInput>(emptyForm)

  useEffect(() => {
    if (open) {
      const timer = window.setTimeout(() => {
        setForm(
          shift
            ? {
                name: shift.name,
                startTime: shift.startTime,
                endTime: shift.endTime,
                breakMinutes: shift.breakMinutes,
                toleranceMinutes: shift.toleranceMinutes,
                minimumWorkHours: shift.minimumWorkHours,
                overnight: shift.overnight,
              }
            : emptyForm,
        )
      }, 0)
      return () => window.clearTimeout(timer)
    }
    return undefined
  }, [open, shift])

  function updateField<K extends keyof ShiftInput>(field: K, value: ShiftInput[K]) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{shift ? "Edit Shift" : "Tambah Shift"}</DialogTitle>
          <DialogDescription>Atur jam kerja, toleransi, dan aturan shift.</DialogDescription>
        </DialogHeader>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="space-y-1 text-xs sm:col-span-2">
            Nama shift
            <Input value={form.name} onChange={(event) => updateField("name", event.target.value)} placeholder="Contoh: Shift Pagi" />
          </label>
          <label className="space-y-1 text-xs">
            Jam masuk
            <Input value={form.startTime} onChange={(event) => updateField("startTime", event.target.value)} placeholder="08:00" />
          </label>
          <label className="space-y-1 text-xs">
            Jam pulang
            <Input value={form.endTime} onChange={(event) => updateField("endTime", event.target.value)} placeholder="17:00" />
          </label>
          <label className="space-y-1 text-xs">
            Istirahat (menit)
            <Input type="number" min={0} value={form.breakMinutes} onChange={(event) => updateField("breakMinutes", Number(event.target.value))} />
          </label>
          <label className="space-y-1 text-xs">
            Toleransi (menit)
            <Input type="number" min={0} max={120} value={form.toleranceMinutes} onChange={(event) => updateField("toleranceMinutes", Number(event.target.value))} />
          </label>
          <label className="space-y-1 text-xs">
            Minimal jam kerja
            <Input type="number" min={0} value={form.minimumWorkHours} onChange={(event) => updateField("minimumWorkHours", Number(event.target.value))} />
          </label>
          <label className="space-y-1 text-xs">
            Jenis shift
            <Select value={form.overnight ? "overnight" : "regular"} onValueChange={(value) => updateField("overnight", value === "overnight")}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="regular">Hari yang sama</SelectItem>
                <SelectItem value="overnight">Lewat tengah malam</SelectItem>
              </SelectContent>
            </Select>
          </label>
        </div>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Batal</Button>
          <Button onClick={() => void onSubmit(form, shift?.id)}>Simpan</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
