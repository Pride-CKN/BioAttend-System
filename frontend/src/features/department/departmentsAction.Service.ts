import type { Department, DepartmentInput, DepartmentStatus } from "@/features/department/department.type"
import { departmentService } from "@/features/department/deparment.Service"
import { departmentStore } from "@/features/department/department.store"

let inflight: Promise<void> | null = null

export function loadDepartments(force = false): Promise<void> {
  if (!force && departmentStore.getState().loaded) return Promise.resolve()
  inflight ??= departmentService
    .list()
    .then((items) => departmentStore.setAll(items))
    .finally(() => {
      inflight = null
    })
  return inflight
}

export async function loadDepartment(id: string): Promise<Department | null> {
  const cached = departmentStore.getState().items.find((d) => d.id === id)
  if (cached) return cached
  const fetched = await departmentService.get(id)
  if (fetched) departmentStore.upsert(fetched)
  return fetched
}

export async function changeDepartmentStatus(id: string, status: DepartmentStatus) {
  const previous = departmentStore.getState().items.find((d) => d.id === id)
  if (!previous) return
  departmentStore.upsert({ ...previous, status })
  try {
    departmentStore.upsert(await departmentService.setStatus(id, status))
  } catch (e) {
    departmentStore.upsert(previous)
    throw e
  }
}

export async function saveDepartment(id: string | undefined, input: DepartmentInput) {
  const saved = id ? await departmentService.update(id, input) : await departmentService.create(input)
  departmentStore.upsert(saved)
  return saved
}
