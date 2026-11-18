import { Link, useNavigate } from "react-router"
import { ArrowLeft, LayoutDashboard } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"

type NotFoundPageProps = {
  message?: string
  backTo?: string
  backLabel?: string
}

export function NotFoundPage({ message = "Halaman yang Anda cari tidak ditemukan.", backTo, backLabel = "Kembali" }: NotFoundPageProps) {
  const navigate = useNavigate()

  return (
    <main className="flex min-h-[60vh] w-full items-center justify-center px-4 py-10">
      <Card className="w-full max-w-md text-center shadow-sm">
        <CardHeader className="items-center gap-2">
          <span className="text-5xl font-bold tracking-tight text-primary">404</span>
          <CardTitle>Halaman tidak ditemukan</CardTitle>
        </CardHeader>
        <CardContent className="space-y-5">
          <p className="text-sm text-muted-foreground">{message}</p>
          <div className="flex flex-wrap justify-center gap-2">
            {backTo ? (
              <Button variant="outline" render={<Link to={backTo} />}><ArrowLeft />{backLabel}</Button>
            ) : (
              <Button variant="outline" onClick={() => navigate(-1)}><ArrowLeft />Kembali</Button>
            )}
            <Button render={<Link to="/dashboard" />}><LayoutDashboard />Ke Dashboard</Button>
          </div>
        </CardContent>
      </Card>
    </main>
  )
}

export function NotFoundEmployee({ id }: { id?: string }) {
  return (
    <NotFoundPage
      message={`Karyawan${id ? ` dengan ID ${id}` : ""} tidak ditemukan.`}
      backTo="/employee"
      backLabel="Kembali ke daftar"
    />
  )
}

export function PageLoading() {
  return <div className="flex min-h-screen flex-1 items-center justify-center text-sm text-muted-foreground">Memuat…</div>
}
