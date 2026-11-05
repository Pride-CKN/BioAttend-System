import { Link } from "react-router"
import { Button } from "@/components/ui/button"

export function NotFoundEmployee({ id }: { id?: string }) {
  return (
    <div className="flex min-h-screen flex-1 flex-col items-center justify-center gap-3 bg-slate-50 text-sm">
      <p className="text-muted-foreground">Karyawan dengan ID {id} tidak ditemukan.</p>
      <Button asChild size="sm" variant="outline"><Link to="/employee">← Kembali ke daftar</Link></Button>
    </div>
  )
}

export function PageLoading() {
  return <div className="flex min-h-screen flex-1 items-center justify-center text-sm text-muted-foreground">Memuat…</div>
}
