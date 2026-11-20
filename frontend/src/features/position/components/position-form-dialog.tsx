import { useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { LEVEL_VALUES } from "../lib/position-options"
import type { Position, PositionInput } from "../Position.types"

const emptyForm: PositionInput = { code: "", name: "", level: 3, active: true }
export function PositionFormDialog({ open, position, error, onOpenChange, onSubmit }: { open: boolean; position: Position | null; error: string | null; onOpenChange: (open: boolean) => void; onSubmit: (input: PositionInput, id?: string) => Promise<void> }) {
  const [form, setForm] = useState<PositionInput>(emptyForm)
  useEffect(() => {
    if (!open) return undefined
    const timer = window.setTimeout(() => setForm(position ? { code: position.code, name: position.name, level: position.level, active: position.active } : emptyForm), 0)
    return () => window.clearTimeout(timer)
  }, [open, position])
  function updateField<K extends keyof PositionInput>(field: K, value: PositionInput[K]) { setForm((current) => ({ ...current, [field]: value })) }
  return <Dialog open={open} onOpenChange={onOpenChange}><DialogContent><DialogHeader><DialogTitle>{position ? "Edit Jabatan" : "Tambah Jabatan"}</DialogTitle><DialogDescription>Atur data jabatan dan struktur organisasi.</DialogDescription></DialogHeader><div className="grid gap-3 sm:grid-cols-2"><label className="space-y-1 text-xs">Kode jabatan<Input value={form.code} onChange={(event) => updateField("code", event.target.value)} placeholder="POS-005" /></label><label className="space-y-1 text-xs">Nama jabatan<Input value={form.name} onChange={(event) => updateField("name", event.target.value)} placeholder="Contoh: Supervisor Operasional" /></label><label className="space-y-1 text-xs">Level<Select value={String(form.level)} onValueChange={(value) => updateField("level", Number(value) as PositionInput["level"])}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{LEVEL_VALUES.map((level) => <SelectItem key={level} value={String(level)}>{level}</SelectItem>)}</SelectContent></Select></label></div>{error && <p className="text-sm text-destructive">{error}</p>}<DialogFooter><Button variant="outline" onClick={() => onOpenChange(false)}>Batal</Button><Button onClick={() => void onSubmit(form, position?.id)}>Simpan</Button></DialogFooter></DialogContent></Dialog>
}
