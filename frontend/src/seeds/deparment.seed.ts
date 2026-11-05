import type { Department } from "@/features/department/department.type"

export const departmentSeed: Department[] = [
  { id: "1", code: "PRD-01", name: "Produksi", division: "Food Processing", totalEmployee: 142, status: "Aktif", headId: "1045", updatedAt: "2026-10-24" },
  { id: "2", code: "PRD-02", name: "Operasional", division: "Food Processing", totalEmployee: 38, status: "Aktif", headId: "1045", updatedAt: "2026-10-24" },
  { id: "3", code: "PRD-03", name: "Quality Control", division: "Food Processing", totalEmployee: 26, status: "Aktif", headId: "1201", updatedAt: "2026-10-18" },
  { id: "4", code: "PRD-04", name: "Logistik", division: "Distribution", totalEmployee: 14, status: "Aktif", headId: "1067", updatedAt: "2026-10-12" },
  { id: "5", code: "PRD-05", name: "Maintenance", division: "Support", totalEmployee: 8, status: "Aktif", headId: "0988", updatedAt: "2026-10-05" },
  { id: "6", code: "PRD-06", name: "Human Resources", division: "Support", totalEmployee: 12, status: "Aktif", headId: "1012", updatedAt: "2026-10-01" },
  { id: "7", code: "PRD-07", name: "Finance & Accounting", division: "Support", totalEmployee: 8, status: "Nonaktif", headId: null, updatedAt: "2026-09-28" },
]
