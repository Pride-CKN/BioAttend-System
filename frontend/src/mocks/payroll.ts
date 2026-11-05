export type PayrollDeduction = {
  id: string
  employee: string
  period: string
  type: "Late" | "Absence" | "Other"
  amount: number
  status: "draft" | "confirmed"
}

export type PayrollSummary = {
  period: string
  employeeCount: number
  grossSalary: number
  totalDeduction: number
  netSalary: number
  status: "draft" | "processed"
}

export const payrollDeductions: PayrollDeduction[] = [
  { id: "DED-001", employee: "Andi Saputra", period: "October 2026", type: "Late", amount: 50000, status: "confirmed" },
  { id: "DED-002", employee: "Eka Wulandari", period: "October 2026", type: "Absence", amount: 150000, status: "draft" },
  { id: "DED-003", employee: "Dewi Lestari", period: "October 2026", type: "Other", amount: 75000, status: "confirmed" },
]

export const payrollSummaries: PayrollSummary[] = [
  { period: "October 2026", employeeCount: 58, grossSalary: 348000000, totalDeduction: 4250000, netSalary: 343750000, status: "processed" },
  { period: "September 2026", employeeCount: 57, grossSalary: 342000000, totalDeduction: 3900000, netSalary: 338100000, status: "processed" },
]
