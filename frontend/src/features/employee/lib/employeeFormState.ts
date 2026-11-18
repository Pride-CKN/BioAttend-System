import type { Employee, EmployeeFormValues, EmployeeProfile } from "@/features/employee/types/employee.type"

export function emptyFormValues(): EmployeeFormValues {
  return {
    full_name: "",
    jenisKelamin: "",
    hp: "",
    email: "",
    role: "EMPLOYEE",
    officeId: "",
    office: "",
    division: "",
    divisionId: "",
    unit: "",
    unitId: "",
    position: "",
    positionId: "",
    joinDate: new Date().toISOString().slice(0, 10),
    supervisor: "",
    managerId: "",
    pin: "",
    autoGenerateEmployeeCode: true,
  }
}

export function formValuesFrom(emp: Employee, p: EmployeeProfile): EmployeeFormValues {
  return {
    full_name: emp.full_name,
    jenisKelamin: p.jenisKelamin,
    hp: p.hp,
    email: p.email,
    role: p.role || "EMPLOYEE",
    officeId: emp.officeId ?? "",
    office: "",
    division: emp.division,
    divisionId: emp.divisionId ?? "",
    unit: emp.unit === "—" ? "" : emp.unit,
    unitId: emp.unitId ?? "",
    position: emp.position === "—" ? "" : emp.position,
    positionId: emp.positionId ?? "",
    joinDate: emp.joinDate ?? "",
    supervisor: p.supervisor,
    managerId: emp.managerId ?? "",
    pin: emp.nip ?? "",
    autoGenerateEmployeeCode: false,
  }
}
