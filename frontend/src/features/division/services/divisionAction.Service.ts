import type { Division, DivisionInput, DivisionStatus, Unit, UnitInput } from "@/features/division/types/division.type"
import { divisionService } from "@/features/division/services/division.Service"
import { divisionStore } from "@/features/division/division.store"

let inflight: Promise<void> | null = null

export function loadDivisions(force = false): Promise<void> {
  if (!force && divisionStore.getState().loaded) return Promise.resolve()
  inflight ??= divisionService
    .list()
    .then((items) => divisionStore.setAll(items))
    .finally(() => {
      inflight = null
    })
  return inflight
}

export async function loadDivision(id: string): Promise<Division | null> {
  const cached = divisionStore.getState().items.find((d) => d.id === id)
  if (cached) return cached
  const fetched = await divisionService.get(id)
  if (fetched) divisionStore.upsert(fetched)
  return fetched
}

export async function changeDivisionStatus(id: string, status: DivisionStatus) {
  const previous = divisionStore.getState().items.find((d) => d.id === id)
  if (!previous) return
  divisionStore.upsert({ ...previous, status })
  try {
    divisionStore.upsert(await divisionService.setStatus(id, status))
  } catch (e) {
    divisionStore.upsert(previous)
    throw e
  }
}

export async function addDivisionUnit(divisionId: string, input: UnitInput): Promise<Unit> {
  const unit = await divisionService.createUnit(divisionId, input)
  const division = divisionStore.getState().items.find((item) => item.id === divisionId)
  if (division) divisionStore.upsert({ ...division, units: [...division.units, unit] })
  return unit
}

export async function changeDivisionUnitStatus(divisionId: string, unitId: string, status: DivisionStatus): Promise<void> {
  const division = divisionStore.getState().items.find((item) => item.id === divisionId)
  const previous = division?.units.find((unit) => unit.id === unitId)
  if (!division || !previous) return
  const updateUnit = (unit: Unit) => divisionStore.upsert({
    ...division,
    units: division.units.map((item) => item.id === unit.id ? unit : item),
  })
  updateUnit({ ...previous, status })
  try {
    updateUnit(await divisionService.setUnitStatus(unitId, status))
  } catch (error) {
    updateUnit(previous)
    throw error
  }
}

export async function saveDivision(id: string | undefined, input: DivisionInput) {
  const saved = id ? await divisionService.update(id, input) : await divisionService.create(input)
  divisionStore.upsert(saved)
  return saved
}
