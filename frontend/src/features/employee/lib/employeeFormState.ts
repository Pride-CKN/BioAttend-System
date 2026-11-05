import type { Employee, EmployeeFormValues, EmployeeProfile } from "@/features/employee/employee.type"
import {
  bloodTypes, departments, devices, divisionsByDept, genders, positionsByDept, shifts, supervisors,
} from "@/features/employee/lib/employee.Option"

export const generatePassword = () => {
  const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789"
  const bytes = crypto.getRandomValues(new Uint32Array(10))
  return Array.from(bytes, (b) => chars[b % chars.length]).join("")
}

export function emptyFormValues(): EmployeeFormValues {
  const dept = departments[0]
  return {
    nama: "", nik: "", tempatLahir: "",
    jenisKelamin: genders[0], golonganDarah: bloodTypes[0], alamat: "",
    hp: "", email: "", username: "", role: "Employee", password: generatePassword(),
    department: dept, division: divisionsByDept[dept][0], position: positionsByDept[dept][0],
    statusKepegawaian: "Permanen", joinDate: new Date().toISOString().slice(0, 10),
    supervisor: supervisors[0].name,
    pin: "", shift: shifts[0], device: devices[0], toleransi: "15",
    wajibFingerprint: true, presensiManual: false,
  }
}

export function formValuesFrom(emp: Employee, p: EmployeeProfile): EmployeeFormValues {
  return {
    nama: emp.name, nik: p.nik, tempatLahir: p.tempatLahir, jenisKelamin: p.jenisKelamin,
    golonganDarah: p.golonganDarah, alamat: p.alamat, hp: p.hp, email: p.email,
    username: p.username, role: p.role, password: "",
    department: emp.dept, division: p.division, position: emp.position,
    statusKepegawaian: p.statusKepegawaian, joinDate: p.joinDate, supervisor: p.supervisor,
    pin: emp.id, shift: emp.shift, device: p.device, toleransi: p.toleransi,
    wajibFingerprint: p.wajibFingerprint, presensiManual: p.presensiManual,
  }
}
