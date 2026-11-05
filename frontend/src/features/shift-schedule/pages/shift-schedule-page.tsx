import { useMemo, useState } from "react"
import { CalendarDays, Plus } from "lucide-react"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { useShiftSchedule } from "../hooks/use-shift-schedule"
import { HistoryTable } from "../components/history-table"
import { ScheduleFilters } from "../components/schedule-filters"
import { ScheduleTable } from "../components/schedule-table"
import { ScheduleAssignmentDialog } from "../components/schedule-assignment-dialog"
import { ShiftFormDialog } from "../components/shift-form-dialog"
import { ShiftMasterTable } from "../components/shift-master-table"
import { ShiftDistributionChart } from "../components/shift-distribution-chart"
import { ShiftTabs } from "../components/shift-tabs"
import { TemplateTable } from "../components/template-table"
import { WeeklyScheduleGrid } from "../components/weekly-schedule-grid"
import type { Shift, ViewTab } from "../types"
import type { ShiftInput } from "../services/shift-schedule-service"
import type { EmployeeSchedule } from "../types"

const DEFAULT_DATE = "2026-10-24"

export function ShiftSchedulePage() {
  const [tab, setTab] = useState<ViewTab>("master")
  const [masterQuery, setMasterQuery] = useState("")
  const [scheduleQuery, setScheduleQuery] = useState("")
  const [statusFilter, setStatusFilter] = useState("all")
  const [scheduleShift, setScheduleShift] = useState("all")
  const [department, setDepartment] = useState("all")
  const [date, setDate] = useState(DEFAULT_DATE)
  const [editingShift, setEditingShift] = useState<Shift | null>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)
  const [assignmentOpen, setAssignmentOpen] = useState(false)
  const { shifts, schedules, templates, history, loading, error, toggleShift, copyShift, saveShift, assignSchedule } = useShiftSchedule(date)

  const filteredShifts = useMemo(() => shifts.filter((shift) => {
    const text = `${shift.name} ${shift.code}`.toLowerCase()
    return (!masterQuery || text.includes(masterQuery.toLowerCase())) && (statusFilter === "all" || shift.status === statusFilter)
  }), [masterQuery, shifts, statusFilter])

  const filteredSchedules = useMemo(() => schedules.filter((item) => {
    const text = `${item.employee} ${item.department}`.toLowerCase()
    return (!scheduleQuery || text.includes(scheduleQuery.toLowerCase())) &&
      (scheduleShift === "all" || item.shiftId === scheduleShift) &&
      (department === "all" || item.department === department)
  }), [department, scheduleQuery, scheduleShift, schedules])

  async function handleSaveShift(input: ShiftInput, id?: string) {
    try {
      setActionError(null)
      await saveShift(input, id)
      setFormOpen(false)
      setEditingShift(null)
    } catch (saveError) {
      setActionError(saveError instanceof Error ? saveError.message : "Gagal menyimpan shift.")
    }
  }

  function openCreateShift() {
    setEditingShift(null)
    setActionError(null)
    setFormOpen(true)
  }

  function openEditShift(shift: Shift) {
    setEditingShift(shift)
    setActionError(null)
    setFormOpen(true)
  }

  async function handleAssignSchedule(input: Omit<EmployeeSchedule, "id">) {
    try {
      setActionError(null)
      await assignSchedule(input)
      setAssignmentOpen(false)
    } catch (assignError) {
      setActionError(assignError instanceof Error ? assignError.message : "Gagal menyimpan jadwal.")
    }
  }

  return (
    <div className="mx-auto w-full max-w-[1120px] space-y-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="mb-2 text-sm text-muted-foreground">Dashboard <span className="mx-2">›</span> Absensi <span className="mx-2">›</span><span className="font-medium text-foreground">Shift &amp; Schedule</span></div>
          <h1 className="text-2xl font-bold">Shift &amp; Schedule</h1>
          <p className="text-sm text-muted-foreground">Kelola daftar shift dan jadwal kerja karyawan</p>
        </div>
        <div className="flex gap-2">
          {tab === "schedule" && <Button variant="outline"><CalendarDays />Cetak Jadwal</Button>}
          {(tab === "master" || tab === "schedule") && <Button className="bg-[var(--brand-red)] hover:bg-[var(--brand-maroon)]" onClick={tab === "master" ? openCreateShift : () => setAssignmentOpen(true)}><Plus />Tambah {tab === "master" ? "Shift" : "Jadwal"}</Button>}
        </div>
      </div>
      <Card className="py-0"><CardContent className="px-0 py-0"><ShiftTabs value={tab} onChange={setTab} /></CardContent></Card>
      {error && <Alert variant="destructive"><AlertTitle>Gagal memuat data</AlertTitle><AlertDescription>{error}</AlertDescription></Alert>}
      {actionError && <Alert variant="destructive"><AlertTitle>Aksi gagal</AlertTitle><AlertDescription>{actionError}</AlertDescription></Alert>}
      {tab === "master" && <><ShiftMasterTable shifts={filteredShifts} loading={loading} query={masterQuery} statusFilter={statusFilter} onQueryChange={setMasterQuery} onStatusChange={setStatusFilter} onAdd={openCreateShift} onEdit={openEditShift} onDuplicate={(id) => void copyShift(id)} onToggle={(id, active) => void toggleShift(id, active)} /><ShiftDistributionChart shifts={filteredShifts} /></>}
      {tab === "schedule" && <><Card className="overflow-hidden py-0"><ScheduleFilters query={scheduleQuery} date={date} shiftId={scheduleShift} department={department} shifts={shifts} onQueryChange={setScheduleQuery} onDateChange={setDate} onShiftChange={setScheduleShift} onDepartmentChange={setDepartment} /></Card><ScheduleTable schedules={filteredSchedules} shifts={shifts} loading={loading} /><WeeklyScheduleGrid schedules={filteredSchedules} shifts={shifts} /></>}
      {tab === "template" && <TemplateTable templates={templates} shifts={shifts} />}
      {tab === "history" && <HistoryTable history={history} />}
      <ShiftFormDialog open={formOpen} shift={editingShift} error={actionError} onOpenChange={setFormOpen} onSubmit={handleSaveShift} />
      <ScheduleAssignmentDialog open={assignmentOpen} shifts={shifts} error={actionError} onOpenChange={setAssignmentOpen} onSubmit={handleAssignSchedule} />
    </div>
  )
}
