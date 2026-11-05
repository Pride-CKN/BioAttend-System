import type { Device, Employee } from "@/features/raw-logs/types"

export type Department = {
  id: string
  name: string
  division: string
  headcount: number
  active: boolean
}

export type Position = {
  id: string
  name: string
  level: string
  department: string
  active: boolean
}

export const departments: Department[] = [
  { id: "DPT-HRD", name: "Human Resources", division: "Corporate", headcount: 8, active: true },
  { id: "DPT-OPS", name: "Operations", division: "Restaurant", headcount: 32, active: true },
  { id: "DPT-FIN", name: "Finance", division: "Corporate", headcount: 6, active: true },
  { id: "DPT-IT", name: "Information Technology", division: "Corporate", headcount: 5, active: true },
  { id: "DPT-MKT", name: "Marketing", division: "Corporate", headcount: 7, active: true },
]

export const positions: Position[] = [
  { id: "POS-001", name: "HR Manager", level: "Manager", department: "Human Resources", active: true },
  { id: "POS-002", name: "HR Officer", level: "Staff", department: "Human Resources", active: true },
  { id: "POS-003", name: "Restaurant Manager", level: "Manager", department: "Operations", active: true },
  { id: "POS-004", name: "Crew Member", level: "Staff", department: "Operations", active: true },
  { id: "POS-005", name: "System Administrator", level: "Staff", department: "Information Technology", active: true },
]

export const employees: Employee[] = [
  { pin: "1182", name: "Andi Saputra", active: true },
  { pin: "1067", name: "Eka Wulandari", active: true },
  { pin: "1204", name: "Budi Santoso", active: true },
  { pin: "1311", name: "Dewi Lestari", active: true },
  { pin: "1420", name: "Rizky Pratama", active: false },
  { pin: "1533", name: "Maya Putri", active: true },
]

export const devices: Device[] = [
  { id: "ZKTECO-001", name: "ZKTeco" },
  { id: "ZKTECO-002", name: "ZKTeco Lobby" },
  { id: "ZKTECO-003", name: "ZKTeco Office" },
]
