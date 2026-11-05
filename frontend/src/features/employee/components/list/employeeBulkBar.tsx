import { Button } from "@/components/ui/button"
import type { BulkAction } from "@/features/employee/employee.type"

export function EmployeeBulkBar({
  count, onAction, onClear,
}: { count: number; onAction: (a: BulkAction) => void; onClear: () => void }) {
  if (count === 0) return null
  return (
    <div className="flex flex-wrap items-center gap-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm">
      <span className="font-medium">{count} dipilih</span>
      <Button size="sm" variant="outline" onClick={() => onAction("aktifkan")}>Aktifkan</Button>
      <Button size="sm" variant="outline" onClick={() => onAction("nonaktifkan")}>Nonaktifkan</Button>
      <Button size="sm" variant="outline" onClick={() => onAction("export")}>Export terpilih</Button>
      <Button size="sm" variant="destructive" onClick={() => onAction("hapus")}>Hapus</Button>
      <Button size="sm" variant="ghost" onClick={onClear}>Batal</Button>
    </div>
  )
}
