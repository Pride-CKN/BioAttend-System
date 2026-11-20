import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import type { Position, PositionHolder } from "../Position.types"

export function PositionHoldersDialog({ position, holders, open, onOpenChange }: { position: Position | null; holders: PositionHolder[]; open: boolean; onOpenChange: (open: boolean) => void }) {
  return <Dialog open={open} onOpenChange={onOpenChange}><DialogContent><DialogHeader><DialogTitle>Pemegang {position?.name ?? "Jabatan"}</DialogTitle><DialogDescription>Daftar karyawan yang menggunakan jabatan ini.</DialogDescription></DialogHeader>{holders.length === 0 ? <p className="text-sm text-muted-foreground">Belum ada karyawan.</p> : <div className="space-y-2">{holders.map((holder) => <div key={holder.id} className="rounded-lg border p-3"><p className="font-medium">{holder.employeeName}</p><p className="text-xs text-muted-foreground">{holder.employeeCode} · {holder.division}</p></div>)}</div>}</DialogContent></Dialog>
}
