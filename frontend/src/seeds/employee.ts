export const employees = [
    { id: "1182", name: "Andi Saputra", unit: "Produksi", status: "Aktif", dept: "Produksi", position: "Operator Produksi", shift: "Shift Pagi", absensi: "24 Okt 2026", jamKerja: "07:58 – 17:02", potongan: "2%", color: "bg-red-700", avatar: "" },
    { id: "1045", name: "Budi Santoso", unit: "Operasional", status: "Aktif", dept: "Operasional", position: "Supervisor Produksi", shift: "Shift Pagi", absensi: "24 Okt 2026", jamKerja: "08:17 – 17:10", potongan: "4%", color: "bg-blue-600", avatar: "" },
    { id: "1201", name: "Citra Lestari", unit: "QC", status: "Aktif", dept: "Quality Control", position: "Analis QC", shift: "Shift Pagi", absensi: "24 Okt 2026", jamKerja: "07:45 – 16:50", potongan: "0%", color: "bg-emerald-600", avatar: "" },
    { id: "1330", name: "Dimas Pratama", unit: "Produksi", status: "Izin", dept: "Produksi", position: "Operator Produksi", shift: "Shift Siang", absensi: "24 Okt 2026", jamKerja: "", potongan: "2%", color: "bg-purple-600", avatar: "" },
    { id: "1067", name: "Eka Wulandari", unit: "Logistik", status: "Aktif", dept: "Logistik", position: "Staf Gudang", shift: "Shift Pagi", absensi: "24 Okt 2026", jamKerja: "08:42 – 17:05", potongan: "6%", color: "bg-amber-600", avatar: "" },
    { id: "0988", name: "Fajar Nugroho", unit: "Maintenance", status: "Tidak Masuk", dept: "Maintenance", position: "Teknisi", shift: "Shift Pagi", absensi: "24 Okt 2026", jamKerja: "", potongan: "8%", color: "bg-teal-700", avatar: "" },
    { id: "1256", name: "Gita Ayu", unit: "Produksi", status: "Aktif", dept: "Produksi", position: "Operator Produksi", shift: "Shift Pagi", absensi: "23 Okt 2026", jamKerja: "07:55 – 17:00", potongan: "0%", color: "bg-red-700", avatar: "" },
    { id: "1012", name: "Herman Susanto", unit: "Operasional", status: "Aktif", dept: "Operasional", position: "Operator Produksi", shift: "Shift Malam", absensi: "23 Okt 2026", jamKerja: "23:05 – 00:30", potongan: "4%", color: "bg-amber-600", avatar: "" },
  ]
  
  export type Employee = (typeof employees)[number]
  
  /* ----------------------------- Opsi form/master ----------------------------- */
  
  export const departments = ["Produksi", "Operasional", "Quality Control", "Logistik", "Maintenance"]
  
  export const divisionsByDept: Record<string, string[]> = {
    Produksi: ["Food Processing", "Packaging"],
    Operasional: ["Operations", "Planning"],
    "Quality Control": ["Quality Assurance", "QC Laboratory"],
    Logistik: ["Warehouse & Distribution", "Procurement"],
    Maintenance: ["Engineering", "Utility"],
  }
  
  export const positionsByDept: Record<string, string[]> = {
    Produksi: ["Operator Produksi", "Supervisor Produksi"],
    Operasional: ["Operator Produksi", "Supervisor Produksi", "Staf Operasional"],
    "Quality Control": ["Analis QC", "Inspektur QC"],
    Logistik: ["Staf Gudang", "Driver"],
    Maintenance: ["Teknisi", "Supervisor Maintenance"],
  }
  
  export const shifts = ["Shift Pagi", "Shift Siang", "Shift Malam"]
  
  export const shiftHours: Record<string, string> = {
    "Shift Pagi": "07:00–15:00",
    "Shift Siang": "15:00–23:00",
    "Shift Malam": "23:00–07:00",
  }
  
  export const employmentStatuses = ["Permanen", "Kontrak", "Probation", "Magang"]
  export const genders = ["Laki-laki", "Perempuan"]
  export const bloodTypes = ["A", "B", "AB", "O"]
  export const roles = ["Employee", "Supervisor", "HRD", "Admin"]
  export const devices = ["ZKTeco — Pintu Utama", "ZKTeco — Area Produksi", "ZKTeco — Gudang"]
  
  export const supervisors = [
    { name: "Budi Santoso", title: "KADIV" },
    { name: "Citra Lestari", title: "Kepala QC" },
    { name: "Herman Susanto", title: "Supervisor" },
  ]
  
  export const supervisorLabel = (name: string) => {
    const s = supervisors.find((x) => x.name === name)
    return s ? `${s.name} (${s.title})` : name
  }
  
  /* --------------------------------- Profil --------------------------------- */
  
  export type Profile = {
    nik: string
    tempatLahir: string
    jenisKelamin: string
    golonganDarah: string
    alamat: string
    hp: string
    email: string
    username: string
    role: string
    division: string
    statusKepegawaian: string
    joinDate: string // ISO yyyy-mm-dd
    supervisor: string
    cuti: string
    device: string
    toleransi: string
    wajibFingerprint: boolean
    presensiManual: boolean
  }
  
  // Data lengkap yang belum ada di tabel utama (ganti dengan data API nanti)
  const profileOverrides: Record<string, Partial<Profile>> = {
    "1182": {
      nik: "3273011508930001",
      tempatLahir: "Jakarta, 15 Agustus 1993",
      jenisKelamin: "Laki-laki",
      golonganDarah: "A",
      alamat: "Jl. Raya Bogor KM 42, Jakarta Timur",
      hp: "0812-3456-7890",
      joinDate: "2023-03-12",
      supervisor: "Budi Santoso",
      cuti: "9 dari 12 hari",
    },
  }
  
  const slug = (name: string) => name.toLowerCase().replace(/\s+/g, ".")
  
  export function getProfile(emp: Employee): Profile {
    return {
      nik: "",
      tempatLahir: "",
      jenisKelamin: "Laki-laki",
      golonganDarah: "A",
      alamat: "",
      hp: "",
      email: `${slug(emp.name)}@pridechicken.co.id`,
      username: slug(emp.name),
      role: "Employee",
      division: divisionsByDept[emp.dept]?.[0] ?? emp.unit,
      statusKepegawaian: "Permanen",
      joinDate: "2022-01-01",
      supervisor: supervisors[0].name,
      cuti: "12 dari 12 hari",
      device: devices[0],
      toleransi: "15",
      wajibFingerprint: true,
      presensiManual: false,
      ...profileOverrides[emp.id],
    }
  }
  
  export const findEmployee = (id?: string) => employees.find((e) => e.id === id)
  
  export function nextEmployeeId() {
    const max = Math.max(...employees.map((e) => parseInt(e.id, 10)))
    return String(max + 1).padStart(4, "0")
  }
  
  export function formatDateID(iso: string) {
    if (!iso) return "—"
    return new Date(`${iso}T00:00:00`).toLocaleDateString("id-ID", {
      day: "numeric",
      month: "long",
      year: "numeric",
    })
  }