import { employees } from "@/seeds/employee"

export const headOptions = employees.map((e) => ({ id: e.id, label: `${e.name} — ${e.position}` }))
