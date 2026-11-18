export const primaryBtn = "bg-[#EA232A] text-white hover:bg-[#EA232A]/90"

export const statusClass: Record<string, string> = {
  Aktif: "border-emerald-200 bg-emerald-50 text-emerald-700",
  Izin: "border-blue-200 bg-blue-50 text-blue-700",
  "Tidak Masuk": "border-red-200 bg-red-50 text-red-700",
  Nonaktif: "border-slate-200 bg-slate-100 text-slate-600",
  valid: "border-emerald-700 bg-[#a7b3b0] text-[#66f2c0]",
  duplicate: "border-[#52617a] bg-[#25344d] text-[#d3dce8]",
  error: "border-[#b40000] bg-[#b7a4a6] text-[#ff9696]",
  Hadir: "border-emerald-200 bg-emerald-50 text-emerald-700",
  Terlambat: "border-[#f0d00b]/50 bg-[#f0d00b]/20 text-[#9a5d00]",
  Pending: "border-[#faa419]/40 bg-[#faa419]/15 text-[#9a5d00]",
  Approved: "border-emerald-200 bg-emerald-50 text-emerald-700",
  Rejected: "border-red-200 bg-red-50 text-red-700",
}

export const initials = (name: string) =>
  name.split(" ").filter(Boolean).map((n) => n[0]).join("").slice(0, 2).toUpperCase()

export const toMinutes = (hhmm: string) => {
  const [h, m] = hhmm.split(":").map(Number)
  return h * 60 + m
}

export const pct = (part: number, total: number) =>
  total ? `${((part / total) * 100).toFixed(1).replace(".", ",")}% dari total` : "—"

/** Windowed page list: 1 … 4 5 [6] 7 8 … 25 */
export function pageWindow(page: number, count: number): (number | "…")[] {
  if (count <= 7) return Array.from({ length: count }, (_, i) => i + 1)
  const out: (number | "…")[] = [1]
  const from = Math.max(2, page - 1)
  const to = Math.min(count - 1, page + 1)
  if (from > 2) out.push("…")
  for (let i = from; i <= to; i++) out.push(i)
  if (to < count - 1) out.push("…")
  out.push(count)
  return out
}
