import type { Department, DepartmentInput, DepartmentStatus } from "@/features/department/department.type"
import { departmentSeed } from "@/seeds/deparment.seed"

let db: Department[] = departmentSeed.map((d) => ({ ...d }))
const latency = <T,>(value: T, ms = 150) => new Promise<T>((resolve) => setTimeout(() => resolve(value), ms))
const today = () => new Date().toISOString().slice(0, 10)

export const departmentService = {
  list: () => latency(db.map((d) => ({ ...d }))),

  get: (id: string) => latency(db.find((d) => d.id === id) ?? null),

  nextCode: () => {
    const max = db.reduce((m, d) => Math.max(m, parseInt(d.code.split("-")[1] ?? "0", 10)), 0)
    return latency(`PRD-${String(max + 1).padStart(2, "0")}`, 0)
  },

  async create(input: DepartmentInput): Promise<Department> {
    if (db.some((d) => d.code === input.code)) throw new Error(`Kode ${input.code} sudah digunakan`)
    const created: Department = {
      ...input,
      id: String(Math.max(0, ...db.map((d) => Number(d.id))) + 1),
      totalEmployee: 0,
      updatedAt: today(),
    }
    db = [...db, created]
    return latency({ ...created })
  },

  async update(id: string, input: DepartmentInput): Promise<Department> {
    const current = db.find((d) => d.id === id)
    if (!current) throw new Error("Department tidak ditemukan")
    if (db.some((d) => d.code === input.code && d.id !== id)) throw new Error(`Kode ${input.code} sudah digunakan`)
    const updated: Department = { ...current, ...input, updatedAt: today() }
    db = db.map((d) => (d.id === id ? updated : d))
    return latency({ ...updated })
  },

  async setStatus(id: string, status: DepartmentStatus): Promise<Department> {
    const current = db.find((d) => d.id === id)
    if (!current) throw new Error("Department tidak ditemukan")
    const updated: Department = { ...current, status, updatedAt: today() }
    db = db.map((d) => (d.id === id ? updated : d))
    return latency({ ...updated }, 100)
  },
}
