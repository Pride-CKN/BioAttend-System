import { useState } from "react"
import { Plus } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Pagination } from "@/components/shared"
import { PositionCardList } from "../components/position-card-list"
import { PositionFilters } from "../components/position-filters"
import { PositionFormDialog } from "../components/position-form-dialog"
import { PositionHoldersDialog } from "../components/position-holders-dialog"
import { PositionTable } from "../components/position-table"
import { usePositions } from "../hooks/usepositions"
import type { Position, PositionInput } from "../Position.types"
import { PageBreadcrumb } from "@/components/page-breadcrumb"

export function PositionPage() {
  const model = usePositions()
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Position | null>(null)
  const [holdersOf, setHoldersOf] = useState<Position | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)

  async function save(input: PositionInput, id?: string) {
    try { setActionError(null); await model.savePosition(input, id); setFormOpen(false); setEditing(null) }
    catch (error) { setActionError(error instanceof Error ? error.message : "Gagal menyimpan jabatan.") }
  }
  function edit(position: Position) { setEditing(position); setActionError(null); setFormOpen(true) }
  async function toggle(position: Position) {
    try { await model.toggleActive(position.id, !position.active) }
    catch (error) { setActionError(error instanceof Error ? error.message : "Gagal mengubah status jabatan.") }
  }
  function openCreate() { setEditing(null); setActionError(null); setFormOpen(true) }

  return (
    <div className="mx-auto w-full max-w-280 space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div> 
          <PageBreadcrumb items={[{ label: "Dashboard" }, { label: "Jabatan" }]} className="mb-2" />
          <h1 className="text-2xl font-bold">Jabatan</h1>
          <p className="text-sm text-muted-foreground">Kelola jabatan, level, dan pemegang jabatan perusahaan.</p>
        </div>
      </div>
      {(model.error || actionError) && <div role="alert" className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{model.error ?? actionError}</div>}
      <Card className="overflow-hidden py-0">
        <div className="border-b px-4 py-3">
          <h2 className="font-semibold">
            Daftar Jabatan 
            <span className="ml-1 text-xs font-normal text-muted-foreground">{model.total} jabatan</span>
          </h2>
        </div>
        <CardContent className="p-0">
          <div>
            <PositionFilters filters={model.filters} onChange={model.updateFilter} onReset={model.resetFilters} onButtonClick={openCreate} />
          </div>
        </CardContent>
      </Card>
      <div className="hidden md:block">
        <PositionTable items={model.items} loading={model.loading} onEdit={edit} onHolders={(position) => setHoldersOf(position)} onToggle={(position) => void toggle(position)} />
      </div>
      <PositionCardList items={model.items} onEdit={edit} onHolders={(position) => setHoldersOf(position)} />
        <div className="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
          <span>Menampilkan {model.from}–{model.to} dari {model.total}</span>
          <Pagination page={model.page} pageCount={model.pageCount} onChange={model.setPage} />
        </div>
      <PositionFormDialog open={formOpen} position={editing} error={actionError} onOpenChange={setFormOpen} onSubmit={save} />
      <PositionHoldersDialog open={holdersOf !== null} position={holdersOf} holders={model.holders.filter((holder) => holder.positionId === holdersOf?.id)} onOpenChange={(open) => { if (!open) setHoldersOf(null) }} />
    </div>
  )
}

export default PositionPage
