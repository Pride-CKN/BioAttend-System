import { useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import type { EmployeeSchedule, Shift } from "../types"

type AssignmentInput = Omit<EmployeeSchedule, "id">

const initialForm: AssignmentInput = {
  employee: "",
  initials: "",
  department: "Produksi",
  shiftId: "",
  date: "2026-10-24",
  validFrom: "2026-10-24",
  validTo: "",
  scope: "employee",
  source: "Manual",
  status: "active",
  note: "",
}

export function ScheduleAssignmentDialog({
  open,
  shifts,
  error,
  onOpenChange,
  onSubmit,
}: {
  open: boolean
  shifts: Shift[]
  error: string | null
  onOpenChange: (open: boolean) => void
  onSubmit: (input: AssignmentInput) => Promise<void>
}) {
  const [form, setForm] = useState<AssignmentInput>(initialForm)

  useEffect(() => {
    if (!open) return
    const timer = window.setTimeout(() => {
      setForm((current) => ({ ...initialForm, date: current.date || initialForm.date, shiftId: shifts[0]?.id ?? "" }))
    }, 0)
    return () => window.clearTimeout(timer)
  }, [open, shifts])

  function setField<K extends keyof AssignmentInput>(field: K, value: AssignmentInput[K]) {
    setForm((current) => ({ ...current, [field]: value }))
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Tambah Jadwal</DialogTitle>
          <DialogDescription>Tetapkan shift, rentang berlaku, dan catatan jadwal karyawan.</DialogDescription>
        </DialogHeader>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="space-y-1 text-xs sm:col-span-2">Karyawan<Input value={form.employee} onChange={(event) => setField("employee", event.target.value)} placeholder="Nama karyawan" /></label>
          <label className="space-y-1 text-xs">Department<Input value={form.department} onChange={(event) => setField("department", event.target.value)} /></label>
          <label className="space-y-1 text-xs">Shift<Select value={form.shiftId} onValueChange={(value) => setField("shiftId", value ?? "")}><SelectTrigger><SelectValue placeholder="Pilih shift" /></SelectTrigger><SelectContent>{shifts.map((shift) => <SelectItem key={shift.id} value={shift.id}>{shift.name}</SelectItem>)}</SelectContent></Select></label>
          <label className="space-y-1 text-xs">Tanggal jadwal<Input type="date" value={form.date} onChange={(event) => setField("date", event.target.value)} /></label>
          <label className="space-y-1 text-xs">Berlaku mulai<Input type="date" value={form.validFrom} onChange={(event) => setField("validFrom", event.target.value)} /></label>
          <label className="space-y-1 text-xs">Berlaku sampai (opsional)<Input type="date" value={form.validTo} onChange={(event) => setField("validTo", event.target.value)} /></label>
          <label className="space-y-1 text-xs">Tingkat penugasan<Select value={form.scope} onValueChange={(value) => setField("scope", (value ?? "employee") as AssignmentInput["scope"])}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="company">Perusahaan</SelectItem><SelectItem value="division">Divisi</SelectItem><SelectItem value="employee">Perorangan</SelectItem></SelectContent></Select></label>
          <label className="space-y-1 text-xs sm:col-span-2">Catatan<Input value={form.note} onChange={(event) => setField("note", event.target.value)} placeholder="Catatan jadwal (opsional)" /></label>
        </div>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <DialogFooter><Button variant="outline" onClick={() => onOpenChange(false)}>Batal</Button><Button onClick={() => void onSubmit({ ...form, initials: form.employee.split(" ").map((part) => part[0]).join("").slice(0, 2).toUpperCase() })}>Simpan Jadwal</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
