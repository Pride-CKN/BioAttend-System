import { useMemo, useState } from "react"
import { AlertTriangle, CheckCircle2, Download, Info, Plus, Search, XCircle } from "lucide-react"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { StatusBadge } from "@/components/shared"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
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
import { Textarea } from "@/components/ui/textarea"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useAttendanceCorrections } from "../hooks/use-attendance-corrections"
import {
  exportCorrectionsCsv,
  exportCorrectionsExcel,
  printCorrectionsPdf,
} from "../services/attendance-correction-export"
import type { AttendanceCorrection, CorrectionStatus } from "../types"
import type { CreateAttendanceCorrectionInput } from "../services/attendance-correction-service"

const statusOptions: Array<"all" | CorrectionStatus> = [
  "all",
  "pending",
  "approved",
  "rejected",
]
const statusLabels: Record<(typeof statusOptions)[number], string> = {
  all: "Semua Status",
  pending: "Pending",
  approved: "Approved",
  rejected: "Rejected",
}
const PAGE_SIZE = 10
const correctionTypes = ["Check-In", "Check-Out", "Status Tidak Masuk"]

// Menghasilkan rentang tanggal yang valid untuk koreksi manual.
function getDateWindow() {
  const today = new Date()
  const minimum = new Date(today)
  minimum.setDate(today.getDate() - 30)
  return {
    today: today.toISOString().slice(0, 10),
    minimum: minimum.toISOString().slice(0, 10),
  }
}

function StatCard({
  label,
  value,
  detail,
  tone,
}: {
  label: string
  value: number
  detail: string
  tone: "red" | "orange" | "green" | "gold"
}) {
  const toneClasses = {
    red: "border-l-[#ca373d]",
    orange: "border-l-[#faa419]",
    green: "border-l-emerald-500",
    gold: "border-l-[#c4954d]",
  }

  return (
    <Card className={`border-l-4 py-0 ${toneClasses[tone]}`}>
      <CardContent className="space-y-1 px-4 py-3">
        <p className="text-xs font-medium text-muted-foreground">{label}</p>
        <p className="text-2xl font-bold">{value}</p>
        <p className="text-xs text-muted-foreground">{detail}</p>
      </CardContent>
    </Card>
  )
}

export function AttendanceCorrectionPage() {
  const {
    corrections,
    loading,
    error,
    updateStatus,
    bulkApprove,
    createCorrection,
  } = useAttendanceCorrections()
  const [query, setQuery] = useState("")
  const [status, setStatus] = useState<(typeof statusOptions)[number]>("all")
  const [period, setPeriod] = useState("October 2026")
  const [selected, setSelected] = useState<AttendanceCorrection | null>(null)
  const [action, setAction] = useState<"approved" | "rejected" | null>(null)
  const [note, setNote] = useState("")
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const [page, setPage] = useState(1)
  const [manualOpen, setManualOpen] = useState(false)
  const [manualError, setManualError] = useState("")
  const [manualForm, setManualForm] = useState<CreateAttendanceCorrectionInput>({
    employee: "",
    date: "",
    requestedType: correctionTypes[0],
    originalValue: "",
    correctedValue: "",
    reason: "",
    requester: "Rio Pratama",
  })

  // Menerapkan filter pencarian, periode, dan status sebelum pagination atau export.
  const filtered = useMemo(() => {
    const normalized = query.trim().toLowerCase()
    return corrections.filter((correction) => {
      const matchesQuery =
        !normalized ||
        correction.id.toLowerCase().includes(normalized) ||
        correction.employee.toLowerCase().includes(normalized)
      const matchesStatus = status === "all" || correction.status === status
      const periodPrefix =
        period === "October 2026"
          ? "2026-10"
          : period === "September 2026"
            ? "2026-09"
            : ""
      const matchesPeriod = !periodPrefix || correction.date.startsWith(periodPrefix)
      return matchesQuery && matchesStatus && matchesPeriod
    })
  }, [corrections, period, query, status])

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const currentPage = Math.min(page, totalPages)
  const rows = filtered.slice(
    (currentPage - 1) * PAGE_SIZE,
    currentPage * PAGE_SIZE,
  )
  const pendingRows = rows.filter((item) => item.status === "pending")
  const allPendingSelected =
    pendingRows.length > 0 && pendingRows.every((item) => selectedIds.includes(item.id))

  function toggleSelection(id: string) {
    setSelectedIds((current) =>
      current.includes(id)
        ? current.filter((selectedId) => selectedId !== id)
        : [...current, id],
    )
  }

  // Memilih atau membatalkan semua pengajuan pending di halaman aktif.
  function toggleAllPending() {
    setSelectedIds((current) =>
      allPendingSelected
        ? current.filter((id) => !pendingRows.some((row) => row.id === id))
        : [...new Set([...current, ...pendingRows.map((row) => row.id)])],
    )
  }

  // Menyetujui pengajuan yang dipilih, atau semua pending di halaman aktif.
  async function submitBulkApprove() {
    const idsToApprove =
      selectedIds.length > 0
        ? selectedIds
        : pendingRows.map((correction) => correction.id)
    if (idsToApprove.length === 0) return
    await bulkApprove(idsToApprove)
    setSelectedIds([])
  }

  // Membuka dialog untuk mengisi pengajuan koreksi manual baru.
  function openManualCorrection() {
    setManualError("")
    setManualForm({
      employee: "",
      date: "",
      requestedType: correctionTypes[0],
      originalValue: "",
      correctedValue: "",
      reason: "",
      requester: "Rio Pratama",
    })
    setManualOpen(true)
  }

  // Memvalidasi dan menyimpan pengajuan koreksi manual.
  async function submitManualCorrection() {
    const { minimum, today } = getDateWindow()
    if (
      !manualForm.employee ||
      !manualForm.date ||
      !manualForm.originalValue ||
      !manualForm.correctedValue ||
      !manualForm.reason
    ) {
      setManualError("Semua field wajib diisi.")
      return
    }
    if (manualForm.date < minimum || manualForm.date > today) {
      setManualError("Tanggal koreksi harus berada dalam 30 hari terakhir.")
      return
    }
    await createCorrection(manualForm)
    setManualOpen(false)
    setPage(1)
  }

  const stats = {
    total: corrections.length,
    pending: corrections.filter((item) => item.status === "pending").length,
    approved: corrections.filter((item) => item.status === "approved").length,
    rejected: corrections.filter((item) => item.status === "rejected").length,
  }

  // Membuka dialog konfirmasi untuk aksi approve atau reject.
  function openAction(correction: AttendanceCorrection, nextAction: "approved" | "rejected") {
    setSelected(correction)
    setAction(nextAction)
    setNote("")
  }

  // Menjalankan aksi approve atau reject setelah konfirmasi pengguna.
  async function submitAction() {
    if (!selected || !action) return
    await updateStatus(selected.id, action, note.trim() || undefined)
    setSelected(null)
    setAction(null)
  }

  return (
    <div className="mx-auto w-full max-w-[1120px] space-y-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="mb-2 text-sm text-muted-foreground">
            Dashboard <span className="mx-2">›</span> Absensi <span className="mx-2">›</span>
            <span className="font-medium text-foreground">Attendance Correction</span>
          </div>
          <h1 className="text-2xl font-bold">Attendance Correction</h1>
          <p className="text-sm text-muted-foreground">Review dan setujui koreksi data absensi karyawan</p>
        </div>
        <div className="flex gap-2">
          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <Button variant="outline">
                  <Download />
                  Export
                </Button>
              }
            />
            <DropdownMenuContent align="end" className="w-44">
              <DropdownMenuItem onClick={() => exportCorrectionsCsv(filtered)}>
                Export CSV
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => exportCorrectionsExcel(filtered)}>
                Export Excel
              </DropdownMenuItem>
              <DropdownMenuItem
                onClick={() => {
                  try {
                    printCorrectionsPdf(filtered)
                  } catch (exportError) {
                    window.alert(
                      exportError instanceof Error
                        ? exportError.message
                        : "Gagal membuka dialog PDF.",
                    )
                  }
                }}
              >
                Export PDF
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total Koreksi" value={stats.total} detail="Bulan berjalan" tone="red" />
        <StatCard label="Pending" value={stats.pending} detail="Perlu review HRD" tone="orange" />
        <StatCard label="Approved" value={stats.approved} detail="Disetujui" tone="green" />
        <StatCard label="Rejected" value={stats.rejected} detail="Ditolak" tone="gold" />
      </div>

      <Card className="py-0">
        <CardContent className="flex flex-wrap items-center gap-2 px-3 py-3">
          <div className="flex w-full items-center gap-2 sm:max-w-[320px]">
            <div className="relative min-w-0 flex-1">
              <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari ID koreksi atau nama" className="pl-9" />
            </div>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-[11px] font-medium text-muted-foreground">Status</span>
            <Select value={status} onValueChange={(value) => setStatus((value ?? "all") as (typeof statusOptions)[number])}>
              <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
              <SelectContent>{statusOptions.map((option) => <SelectItem key={option} value={option}>{statusLabels[option]}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-[11px] font-medium text-muted-foreground">Jenis</span>
            <Select value="all-types" onValueChange={() => undefined}>
              <SelectTrigger className="w-32"><SelectValue placeholder="Semua jenis" /></SelectTrigger>
              <SelectContent><SelectItem value="all-types">Semua jenis</SelectItem></SelectContent>
            </Select>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-[11px] font-medium text-muted-foreground">Periode</span>
            <Select value={period} onValueChange={(value) => setPeriod(value ?? "October 2026")}>
              <SelectTrigger className="w-36"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="October 2026">Oktober 2026</SelectItem>
                <SelectItem value="September 2026">September 2026</SelectItem>
                <SelectItem value="Semua Periode">Semua Periode</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Button
            size="sm"
            className="ml-auto border-[#852622] bg-[#852622] text-white hover:bg-[#6b1c1a]"
            disabled={pendingRows.length === 0}
            onClick={() => void submitBulkApprove()}
          >
            Bulk approve
            {selectedIds.length > 0
              ? ` (${selectedIds.length})`
              : ` (${pendingRows.length})`}
          </Button>
        </CardContent>
      </Card>

      {error && (
        <Alert variant="destructive">
          <AlertTriangle />
          <AlertTitle>Gagal memuat data</AlertTitle>
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      <Card className="overflow-hidden py-0">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
          <div>
            <h2 className="font-semibold">Daftar Pengajuan Koreksi</h2>
            <p className="text-xs text-muted-foreground">Menunggu persetujuan HRD</p>
          </div>
          <Button size="sm" className="border-[#852622] bg-[#852622] text-white hover:bg-[#6b1c1a]" onClick={openManualCorrection}>
            <Plus />
            Koreksi Manual
          </Button>
        </div>
        <CardContent className="p-0">
          {loading ? (
            <div className="space-y-3 p-5">{Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} className="h-10 w-full" />)}</div>
          ) : filtered.length === 0 ? (
            <div className="p-10 text-center text-sm text-muted-foreground">Tidak ada pengajuan yang sesuai filter.</div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow className="bg-muted/50">
                    <TableHead className="w-10">
                      <input
                        type="checkbox"
                        checked={allPendingSelected}
                        onChange={toggleAllPending}
                        aria-label="Pilih semua pengajuan pending"
                      />
                    </TableHead>
                    <TableHead>ID</TableHead>
                    <TableHead>Karyawan</TableHead>
                    <TableHead>Tanggal</TableHead>
                    <TableHead>Perubahan</TableHead>
                    <TableHead>Alasan</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Diajukan</TableHead>
                    <TableHead>Catatan Keputusan</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((correction) => (
                    <TableRow key={correction.id} className="cursor-pointer" onClick={() => setSelected(correction)}>
                      <TableCell onClick={(event) => event.stopPropagation()}>
                        <input
                          type="checkbox"
                          checked={selectedIds.includes(correction.id)}
                          disabled={correction.status !== "pending"}
                          onChange={() => toggleSelection(correction.id)}
                          aria-label={`Pilih ${correction.id}`}
                        />
                      </TableCell>
                      <TableCell className="font-medium">{correction.id}</TableCell>
                      <TableCell>{correction.employee}</TableCell>
                      <TableCell>{new Date(correction.date).toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" })}</TableCell>
                      <TableCell>{correction.requestedType}: {correction.originalValue} → {correction.correctedValue}</TableCell>
                      <TableCell className="max-w-56 truncate">{correction.reason}                      </TableCell>
                      <TableCell>
                        <StatusBadge status={correction.status[0].toUpperCase() + correction.status.slice(1)} />
                        {correction.status === "pending" && (
                          <p className="mt-1 text-[10px] text-muted-foreground">Klik untuk review</p>
                        )}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">{correction.requester}</TableCell>
                      <TableCell className="max-w-56 truncate text-xs text-muted-foreground">
                        {correction.decisionNote || "-"}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
        <div className="flex items-center justify-between border-t px-4 py-2 text-xs text-muted-foreground">
          <span>
            Menampilkan {filtered.length === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1}-
            {Math.min(currentPage * PAGE_SIZE, filtered.length)} dari {filtered.length} pengajuan
          </span>
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage === 1}
              onClick={() => setPage((value) => Math.max(1, value - 1))}
            >
              ‹
            </Button>
            {Array.from({ length: totalPages }, (_, index) => index + 1).map((value) => (
              <Button
                key={value}
                variant={value === currentPage ? "default" : "outline"}
                size="sm"
                onClick={() => setPage(value)}
              >
                {value}
              </Button>
            ))}
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage === totalPages}
              onClick={() => setPage((value) => Math.min(totalPages, value + 1))}
            >
              ›
            </Button>
          </div>
        </div>
      </Card>

      <Alert className="border-[#c4954d]/40 bg-[#c4954d]/10">
        <Info />
        <AlertTitle>Validasi koreksi absensi</AlertTitle>
        <AlertDescription>
          Tanggal koreksi maksimal 30 hari terakhir. Nilai baru wajib valid dan penolakan harus disertai catatan minimal 10 karakter.
        </AlertDescription>
      </Alert>

      <Dialog open={selected !== null} onOpenChange={(open) => { if (!open) { setSelected(null); setAction(null) } }}>
        <DialogContent className="max-w-lg">
          {selected && !action ? (
            <>
              <DialogHeader>
                <DialogTitle>Detail {selected.id}</DialogTitle>
                <DialogDescription>Informasi pengajuan koreksi absensi.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-3 rounded-xl bg-muted/50 p-4 text-sm sm:grid-cols-2">
                <div><p className="text-xs text-muted-foreground">Karyawan</p><p className="font-medium">{selected.employee}</p></div>
                <div><p className="text-xs text-muted-foreground">Tanggal</p><p className="font-medium">{selected.date}</p></div>
                <div><p className="text-xs text-muted-foreground">Perubahan</p><p className="font-medium">{selected.requestedType}</p></div>
                <div><p className="text-xs text-muted-foreground">Nilai</p><p className="font-medium">{selected.originalValue} → {selected.correctedValue}</p></div>
                <div className="sm:col-span-2"><p className="text-xs text-muted-foreground">Alasan</p><p className="font-medium">{selected.reason}</p></div>
                {selected.decisionNote && (
                  <div className="sm:col-span-2"><p className="text-xs text-muted-foreground">Catatan keputusan</p><p className="font-medium">{selected.decisionNote}</p></div>
                )}
              </div>
              {selected.status === "pending" && (
                <DialogFooter>
                  <Button variant="outline" onClick={() => openAction(selected, "rejected")}><XCircle />Tolak</Button>
                  <Button onClick={() => openAction(selected, "approved")}><CheckCircle2 />Setujui</Button>
                </DialogFooter>
              )}
            </>
          ) : selected && action ? (
            <>
              <DialogHeader>
                <DialogTitle>{action === "approved" ? "Setujui" : "Tolak"} Pengajuan</DialogTitle>
                <DialogDescription>{selected.id} · {selected.employee}</DialogDescription>
              </DialogHeader>
              <Textarea value={note} onChange={(event) => setNote(event.target.value)} placeholder={action === "rejected" ? "Catatan penolakan (minimal 10 karakter)" : "Catatan (opsional)"} minLength={action === "rejected" ? 10 : undefined} />
              <DialogFooter>
                <Button variant="outline" onClick={() => setAction(null)}>Kembali</Button>
                <Button variant={action === "rejected" ? "destructive" : "default"} disabled={action === "rejected" && note.trim().length < 10} onClick={() => void submitAction()}>Konfirmasi</Button>
              </DialogFooter>
            </>
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={manualOpen} onOpenChange={setManualOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Koreksi Manual</DialogTitle>
            <DialogDescription>
              Buat pengajuan koreksi absensi baru. Tanggal maksimal 30 hari terakhir.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 sm:grid-cols-2">
            <Input
              placeholder="Nama karyawan"
              value={manualForm.employee}
              onChange={(event) => setManualForm({ ...manualForm, employee: event.target.value })}
            />
            <Input
              type="date"
              min={getDateWindow().minimum}
              max={getDateWindow().today}
              value={manualForm.date}
              onChange={(event) => setManualForm({ ...manualForm, date: event.target.value })}
            />
            <Select
              value={manualForm.requestedType}
              onValueChange={(value) => setManualForm({ ...manualForm, requestedType: value ?? correctionTypes[0] })}
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                {correctionTypes.map((type) => <SelectItem key={type} value={type}>{type}</SelectItem>)}
              </SelectContent>
            </Select>
            <Input
              placeholder="Nilai lama"
              value={manualForm.originalValue}
              onChange={(event) => setManualForm({ ...manualForm, originalValue: event.target.value })}
            />
            <Input
              placeholder="Nilai baru"
              value={manualForm.correctedValue}
              onChange={(event) => setManualForm({ ...manualForm, correctedValue: event.target.value })}
            />
            <div className="sm:col-span-2">
              <Textarea
                placeholder="Alasan koreksi"
                value={manualForm.reason}
                onChange={(event) => setManualForm({ ...manualForm, reason: event.target.value })}
              />
            </div>
          </div>
          {manualError && <p className="text-sm text-destructive">{manualError}</p>}
          <DialogFooter>
            <Button variant="outline" onClick={() => setManualOpen(false)}>Batal</Button>
            <Button onClick={() => void submitManualCorrection()}>Simpan Pengajuan</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
