import {
employees as seed,
getProfile,
nextEmployeeId,
type Employee,
} from "@/seeds/employee"
import type {
AttendanceRow,
BulkAction,
DeductionHistoryItem,
EmployeeDetail,
EmployeeFormValues,
EmployeeProfile,
EmployeeQuery,
EmployeeStats,
Page,
} from "@/features/employee/employee.type"

export interface EmployeeService {
    list(q: EmployeeQuery): Promise<Page<Employee>>
    stats(): Promise<EmployeeStats>
    getDetail(id: string): Promise<EmployeeDetail | null>
    getAttendance(id: string): Promise<AttendanceRow[]>
    getDeductionHistory(id: string): Promise<DeductionHistoryItem[]>
    findPinOwner(pin: string, exceptId?: string): Promise<Employee | undefined>
    nextId(): string
    save(id: string | undefined, values: EmployeeFormValues, mode: "draft" | "aktif"): Promise<string>
    bulk(action: BulkAction, ids: string[]): Promise<void>
    remove(ids: string[]): Promise<void>
}

/* ------------------------------ Dummy backend ----------------------------- */

const SIMULATED_LATENCY_MS = 0 // set to 150–400 to feel loading states
const sleep = (ms: number) => new Promise<void>((r) => setTimeout(r, ms))
const latency = () => (SIMULATED_LATENCY_MS ? sleep(SIMULATED_LATENCY_MS) : Promise.resolve())

let db: Employee[] = [...seed]
const byId = new Map<string, Employee>(db.map((e) => [e.id, e]))
const profileOverrides = new Map<string, Partial<EmployeeProfile>>()
const nameKey = new Map<string, string>() // precomputed lowercase name

const indexOne = (e: Employee) => nameKey.set(e.id, e.name.toLowerCase())
db.forEach(indexOne)

/* Caches — invalidated on every write. Repeat views/filters cost ~0ms. */
const queryCache = new Map<string, Page<Employee>>()
const QUERY_CACHE_MAX = 50
let statsCache: EmployeeStats | null = null
const detailCache = new Map<string, EmployeeDetail>()
const attendanceCache = new Map<string, AttendanceRow[]>()

const invalidate = () => {
    queryCache.clear()
    statsCache = null
    detailCache.clear()
}

const profileOf = (e: Employee): EmployeeProfile =>
({ ...getProfile(e), ...profileOverrides.get(e.id) }) as EmployeeProfile

    function runQuery(q: EmployeeQuery): Page<Employee> {
    const kw = q.keyword.trim().toLowerCase()
    const matched = db.filter(
        (e) =>
        (!q.dept || e.dept === q.dept) &&
        (!q.unit || e.unit === q.unit) &&
        (!q.status || e.status === q.status) &&
        (!q.shift || e.shift === q.shift) &&
        (!kw || nameKey.get(e.id)!.includes(kw) || e.id.includes(kw)),
    )
    const pageCount = Math.max(1, Math.ceil(matched.length / q.pageSize))
    const page = Math.min(q.page, pageCount)
    const start = (page - 1) * q.pageSize
    return {
        items: matched.slice(start, start + q.pageSize),
        total: matched.length,
        page,
        pageSize: q.pageSize,
        pageCount,
    }
}

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"]
const baseRows: AttendanceRow[] = [
    { tanggal: "24 Okt 2026", status: "Hadir", masuk: "07:58", keluar: "17:02" },
    { tanggal: "23 Okt 2026", status: "Hadir", masuk: "07:55", keluar: "17:00" },
    { tanggal: "22 Okt 2026", status: "Hadir", masuk: "08:02", keluar: "17:01" },
    { tanggal: "21 Okt 2026", status: "Cuti", catatan: "Cuti tahunan" },
    { tanggal: "20 Okt 2026", status: "Hadir", masuk: "07:52", keluar: "16:58" },
    { tanggal: "19 Okt 2026", status: "Hadir", masuk: "08:15", keluar: "17:05" },
    { tanggal: "18 Okt 2026", status: "Hadir", masuk: "08:41", keluar: "17:03" },
    { tanggal: "17 Okt 2026", status: "Hadir", masuk: "07:57", keluar: "17:00" },
]

function buildAttendance(): AttendanceRow[] {
    const rows = [...baseRows]
    const start = new Date(2026, 9, 17)
    const fmt = (n: number) =>
        `${String(Math.floor(n / 60)).padStart(2, "0")}:${String(n % 60).padStart(2, "0")}`
    for (let i = 1; rows.length < 30; i++) {
        const d = new Date(start)
        d.setDate(start.getDate() - i)
        rows.push({
        tanggal: `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`,
        status: "Hadir",
        masuk: fmt(7 * 60 + 45 + ((i * 7) % 20)),
        keluar: fmt(17 * 60 + ((i * 3) % 8)),
        })
    }
    return rows
}

export const employeeService: EmployeeService = {
    async list(q) {
        await latency()
        const key = JSON.stringify(q)
        const hit = queryCache.get(key)
        if (hit) return hit
        const res = runQuery(q)
        if (queryCache.size >= QUERY_CACHE_MAX) queryCache.delete(queryCache.keys().next().value!)
        queryCache.set(key, res)
        return res
    },

    async stats() {
        await latency()
        if (statsCache) return statsCache
        const month = new Date().toISOString().slice(0, 7)
        const s: EmployeeStats = { total: db.length, active: 0, leave: 0, inactive: 0, notCheckedIn: 0, newThisMonth: 0 }
        for (const e of db) {
        if (e.status === "Aktif") s.active++
        else if (e.status === "Izin") s.leave++
        else if (e.status === "Nonaktif") s.inactive++
        else if (e.status === "Tidak Masuk") s.notCheckedIn++
        if (profileOf(e).joinDate?.startsWith(month)) s.newThisMonth++
        }
        return (statsCache = s)
    },

    async getDetail(id) {
        await latency()
        const cached = detailCache.get(id)
        if (cached) return cached
        const employee = byId.get(id)
        if (!employee) return null
        const detail = { employee, profile: profileOf(employee) }
        detailCache.set(id, detail)
        return detail
    },

    async getAttendance(id) {
        await latency()
        let rows = attendanceCache.get(id)
        if (!rows) attendanceCache.set(id, (rows = buildAttendance()))
        return rows
    },

    async getDeductionHistory() {
        await latency()
        return [
        { bulan: "September 2026", persen: "4%", warn: true },
        { bulan: "Agustus 2026", persen: "0%", warn: false },
        { bulan: "Juli 2026", persen: "2%", warn: false },
        ]
    },

    async findPinOwner(pin, exceptId) {
        await latency()
        const owner = byId.get(pin)
        return owner && owner.id !== exceptId ? owner : undefined
    },

    nextId: () => nextEmployeeId(),

    async save(id, v, mode) {
        await latency()
        const status = mode === "aktif" ? "Aktif" : "Draft"
        const existing = id ? byId.get(id) : undefined
        const next = {
        ...(existing ?? { absensi: "—", jamKerja: "", potongan: "0%", avatar: "", color: "bg-slate-500" }),
        id: v.pin,
        name: v.nama,
        dept: v.department,
        unit: v.division,
        position: v.position,
        shift: v.shift,
        status: existing && mode === "aktif" ? existing.status : status,
        } as Employee
        if (existing) db = db.map((e) => (e.id === existing.id ? next : e))
        else db = [next, ...db]
        byId.delete(existing?.id ?? "")
        byId.set(next.id, next)
        indexOne(next)
        profileOverrides.set(next.id, {
        nik: v.nik, tempatLahir: v.tempatLahir, jenisKelamin: v.jenisKelamin, golonganDarah: v.golonganDarah,
        alamat: v.alamat, hp: v.hp, email: v.email, username: v.username, role: v.role,
        division: v.division, statusKepegawaian: v.statusKepegawaian, joinDate: v.joinDate,
        supervisor: v.supervisor, device: v.device, toleransi: v.toleransi,
        wajibFingerprint: v.wajibFingerprint, presensiManual: v.presensiManual,
        } as Partial<EmployeeProfile>)
        invalidate()
        return next.id
    },

    async bulk(action, ids) {
        await latency()
        const set = new Set(ids)
        if (action === "hapus") return employeeService.remove(ids)
        if (action === "export") return console.log("Export", ids) // TODO: CSV when API exists
        const status = action === "aktifkan" ? "Aktif" : "Nonaktif"
        db = db.map((e) => {
        if (!set.has(e.id)) return e
        const n = { ...e, status } as Employee
        byId.set(n.id, n)
        return n
        })
        invalidate()
    },

    async remove(ids) {
        await latency()
        const set = new Set(ids)
        db = db.filter((e) => !set.has(e.id))
        ids.forEach((i) => {
        byId.delete(i)
        nameKey.delete(i)
        profileOverrides.delete(i)
        })
        invalidate()
    },
}
