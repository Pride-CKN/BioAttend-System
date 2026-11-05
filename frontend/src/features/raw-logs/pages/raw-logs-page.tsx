import { useEffect, useMemo, useState, type ReactNode } from "react"
import { AlertTriangle, ArrowLeft, ArrowRight, Download, Info, Search } from "lucide-react"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { StatusBadge } from "@/components/shared"
import { useRawLogs } from "../hooks/use-raw-logs"
import { processingLabel, statusLabel, typeLabel } from "../types"

const PAGE_SIZE = 10
const DEFAULT_DATE = "2026-10-24" // sesuai mockup: 10/24/2026
const ALL_DEVICE = "Semua Perangkat"
const ALL_STATUS = "Semua"
const ALL_TYPE = "Semua Tipe"

const COLUMNS = [
  "Timestamp",
  "Perangkat",
  "Device ID",
  "PIN",
  "Karyawan",
  "Status",
  "Tipe",
  "Diproses",
  "Log ID",
]

// Membentuk daftar nomor halaman dengan ellipsis saat halaman cukup banyak.
function pageList(page: number, total: number): (number | "…")[] {
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  if (page <= 3) return [1, 2, 3, 4, 5, "…", total]
  if (page >= total - 2)
    return [1, "…", total - 4, total - 3, total - 2, total - 1, total]
  return [1, "…", page - 1, page, page + 1, "…", total]
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-center gap-2">
      <span className="text-xs font-medium text-muted-foreground">{label}</span>
      {children}
    </div>
  )
}

// Menampilkan tabel raw logs dengan filter, pagination, dan export.
export function RawLogsPage() {
  // Tes state: tambahkan ?state=loading | empty | error di URL
  const forced = new URLSearchParams(window.location.search).get("state")

  const [q, setQ] = useState("")
  const [date, setDate] = useState(DEFAULT_DATE)
  const [device, setDevice] = useState(ALL_DEVICE)
  const [status, setStatus] = useState(ALL_STATUS)
  const [type, setType] = useState(ALL_TYPE)
  const [page, setPage] = useState(1)
  const [autoRefresh, setAutoRefresh] = useState(true)
  const { devices, logs, loading } = useRawLogs(autoRefresh)

  useEffect(() => {
    const id = setTimeout(() => setPage(1), 0)
    return () => clearTimeout(id)
  }, [q, date, device, status, type])

  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase()
    return logs.filter((l) => {
      if (date && !l.scannedAt.startsWith(date)) return false
      if (device !== ALL_DEVICE && l.deviceId !== device) return false
      if (status !== ALL_STATUS && statusLabel[l.status] !== status) return false
      if (type !== ALL_TYPE && typeLabel[l.type] !== type) return false
      if (s) {
        const hit =
          String(l.id).includes(s) ||
          l.pin.includes(s) ||
          (l.employee ?? "").toLowerCase().includes(s)
        if (!hit) return false
      }
      return true
    })
  }, [q, date, device, status, type, logs])

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const currentPage = Math.min(page, totalPages)
  const rows = filtered.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE
  )
  const hasFilter =
    q !== "" ||
    date !== "" ||
    device !== ALL_DEVICE ||
    status !== ALL_STATUS ||
    type !== ALL_TYPE

  const view =
    forced === "loading" || loading
      ? "loading"
      : forced === "error"
        ? "error"
        : forced === "empty" || rows.length === 0
          ? "empty"
          : "data"

  function resetFilters() {
    setQ("")
    setDate("")
    setDevice(ALL_DEVICE)
    setStatus(ALL_STATUS)
    setType(ALL_TYPE)
  }

  function exportCsv() {
    const header = [
      "Log ID",
      "Timestamp",
      "Perangkat",
      "Device ID",
      "PIN",
      "Karyawan",
      "Status",
      "Tipe",
      "Diproses",
    ]
    const data = filtered.map((l) => [
      l.id,
      l.scannedAt,
      l.device,
      l.deviceId,
      l.pin,
      l.employee ?? "PIN tidak dikenal",
      statusLabel[l.status],
      typeLabel[l.type],
      processingLabel[l.processing],
    ])
    const csv = [header, ...data]
      .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))
      .join("\n")
    const url = URL.createObjectURL(
      new Blob([csv], { type: "text/csv;charset=utf-8" })
    )
    const a = document.createElement("a")
    a.href = url
    a.download = "raw-attendance-logs.csv"
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div className="space-y-4">
      {/* Breadcrumb + judul + aksi */}
      <div className="space-y-3">
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink href="/">Dashboard</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbLink href="/absensi">Absensi</BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>Raw Attendance Logs</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>

        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold">Raw Attendance Logs</h1>
            <p className="text-sm text-muted-foreground">
              Log mentah seluruh scan fingerprint dari perangkat attendance
            </p>
          </div>
          <Button variant="outline" onClick={exportCsv}>
            <Download className="size-4" />
            Export CSV
          </Button>
        </div>
      </div>

      {/* Filter: satu baris, label di sebelah kiri tiap kontrol */}
      <Card>
        <CardContent className="flex flex-wrap items-center gap-x-4 gap-y-3">
          <div className="relative w-full sm:w-64">
            <Search
              className="absolute left-2.5 top-2.5 size-4 text-muted-foreground"
            />
            <Input
              className="pl-8"
              placeholder="Cari log, PIN, atau nama"
              value={q}
              onChange={(e) => setQ(e.target.value)}
            />
          </div>

          <Field label="Tanggal">
            <Input
              type="date"
              className="w-40"
              value={date}
              onChange={(e) => setDate(e.target.value)}
            />
          </Field>

          <Field label="Perangkat">
            <Select
              value={device}
              onValueChange={(v) => setDevice(v ?? ALL_DEVICE)}
            >
              <SelectTrigger className="w-48">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {[ALL_DEVICE, ...devices.map((d) => d.id)].map((v) => (
                  <SelectItem key={v} value={v}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <Field label="Status">
            <Select
              value={status}
              onValueChange={(v) => setStatus(v ?? ALL_STATUS)}
            >
              <SelectTrigger className="w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {[ALL_STATUS, ...Object.values(statusLabel)].map((v) => (
                  <SelectItem key={v} value={v}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <Field label="Tipe">
            <Select value={type} onValueChange={(v) => setType(v ?? ALL_TYPE)}>
              <SelectTrigger className="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {[ALL_TYPE, ...Object.values(typeLabel)].map((v) => (
                  <SelectItem key={v} value={v}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
        </CardContent>
      </Card>

      {/* Info box */}
      <Alert className="border-blue-200 bg-blue-50 text-blue-900">
        <Info />
        <AlertTitle className="font-semibold">
          Raw log tidak dapat dihapus
        </AlertTitle>
        <AlertDescription className="text-blue-900/80">
          Seluruh log perangkat disimpan permanen untuk keperluan audit dan
          investigasi. Duplicate scan tetap disimpan namun tidak menghasilkan
          record absensi baru.
        </AlertDescription>
      </Alert>

      {/* Tabel */}
      <Card className="gap-0 py-0">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
          <div className="flex flex-wrap items-baseline gap-x-3">
            <h2 className="font-semibold">Raw Attendance Logs</h2>
            <span className="text-xs text-muted-foreground">
              {filtered.length.toLocaleString("id-ID")} baris · sinkron terakhir
              08:12
            </span>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setAutoRefresh((v) => !v)}
          >
            Auto refresh: {autoRefresh ? "30s" : "mati"}
          </Button>
        </div>

        <CardContent className="p-0">
          {view === "loading" && (
            <div className="space-y-3 p-4">
              {Array.from({ length: 6 }).map((_, i) => (
                <Skeleton key={i} className="h-10 w-full" />
              ))}
            </div>
          )}

          {view === "error" && (
            <div className="p-4">
              <Alert variant="destructive">
                <AlertTriangle />
                <AlertTitle>Gagal memuat log</AlertTitle>
                <AlertDescription>
                  Terjadi kesalahan saat mengambil data dari server. Coba muat
                  ulang.
                </AlertDescription>
              </Alert>
            </div>
          )}

          {view === "empty" && (
            <div className="flex flex-col items-center gap-2 p-10 text-center">
              <p className="font-medium">Belum ada log pada filter ini</p>
              <p className="text-sm text-muted-foreground">
                Ubah atau reset filter untuk melihat log lainnya.
              </p>
              {hasFilter && (
                <Button variant="outline" size="sm" onClick={resetFilters}>
                  Reset filter
                </Button>
              )}
            </div>
          )}

          {view === "data" && (
            <>
              {/* Desktop: tabel lebar */}
              <div className="hidden md:block">
                <Table>
                  <TableHeader>
                    <TableRow className="bg-muted/50 hover:bg-muted/50">
                      {COLUMNS.map((c) => (
                        <TableHead
                          key={c}
                          className="text-xs font-semibold uppercase tracking-wide text-muted-foreground"
                        >
                          {c}
                        </TableHead>
                      ))}
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {rows.map((l) => (
                      <TableRow key={l.id}>
                        <TableCell>{l.scannedAt}</TableCell>
                        <TableCell className="text-xs">{l.device}</TableCell>
                        <TableCell className="text-xs">{l.deviceId}</TableCell>
                        <TableCell>{l.pin}</TableCell>
                        <TableCell>
                          {l.employee ?? (
                            <span className="text-destructive">
                              PIN tidak dikenal
                            </span>
                          )}
                        </TableCell>
                        <TableCell>
                          <StatusBadge status={l.status} />
                        </TableCell>
                        <TableCell>{typeLabel[l.type]}</TableCell>
                        <TableCell>{processingLabel[l.processing]}</TableCell>
                        <TableCell className="text-muted-foreground">
                          #{l.id}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              {/* Mobile: list ringkas dengan badge */}
              <div className="divide-y md:hidden">
                {rows.map((l) => (
                  <div key={l.id} className="space-y-2 p-4">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <div className="font-medium">
                          {l.employee ?? (
                            <span className="text-destructive">
                              PIN tidak dikenal
                            </span>
                          )}
                        </div>
                        <div className="text-xs text-muted-foreground">
                          PIN {l.pin} · #{l.id}
                        </div>
                      </div>
                      <StatusBadge status={l.status} />
                    </div>
                    <div className="text-xs">{l.scannedAt}</div>
                    <div className="text-xs text-muted-foreground">
                      {l.deviceId} · {typeLabel[l.type]} ·{" "}
                      {processingLabel[l.processing]}
                    </div>
                  </div>
                ))}
              </div>

              {/* Pagination */}
              <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
                <p className="text-xs text-muted-foreground">
                  Menampilkan {rows.length} dari{" "}
                  {filtered.length.toLocaleString("id-ID")} log · retention 24
                  bulan
                </p>
                <div className="flex items-center gap-1">
                  <Button
                    size="icon"
                    variant="outline"
                    className="size-8"
                    disabled={currentPage === 1}
                    onClick={() => setPage(currentPage - 1)}
                  >
                    <ArrowLeft className="size-4" />
                  </Button>
                  {pageList(currentPage, totalPages).map((p, i) =>
                    p === "…" ? (
                      <span
                        key={`gap-${i}`}
                        className="px-2 text-muted-foreground"
                      >
                        …
                      </span>
                    ) : (
                      <Button
                        key={p}
                        size="icon"
                        variant={p === currentPage ? "default" : "outline"}
                        className="size-8"
                        onClick={() => setPage(p)}
                      >
                        {p}
                      </Button>
                    )
                  )}
                  <Button
                    size="icon"
                    variant="outline"
                    className="size-8"
                    disabled={currentPage === totalPages}
                    onClick={() => setPage(currentPage + 1)}
                  >
                    <ArrowRight className="size-4" />
                  </Button>
                </div>
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}