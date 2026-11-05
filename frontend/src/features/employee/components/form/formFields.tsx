/* eslint-disable react-refresh/only-export-components */
import type { ReactNode } from "react"

const baseInput =
  "h-9 w-full rounded-md border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-muted-foreground"
export const inputCls = (err?: string) => `${baseInput} ${err ? "border-red-400" : "border-input"}`

export function Section({ no, title, id, children }: { no: number; title: string; id: string; children: ReactNode }) {
  return (
    <section id={id} className="scroll-mt-4 overflow-hidden rounded-lg border bg-white">
      <div className="flex items-center gap-2 border-b bg-slate-50 px-4 py-2.5">
        <span className="flex size-5 items-center justify-center rounded-full bg-red-700 text-[11px] font-bold text-white">{no}</span>
        <h2 className="text-sm font-bold">{title}</h2>
      </div>
      <div className="grid gap-x-4 gap-y-3 p-4 @xl:grid-cols-2">{children}</div>
    </section>
  )
}

export function Field({
  label, required, hint, error, full, children,
}: { label: string; required?: boolean; hint?: string; error?: string; full?: boolean; children: ReactNode }) {
  return (
    <label className={`block space-y-1 ${full ? "@xl:col-span-2" : ""}`}>
      <span className="text-xs font-semibold">
        {label}
        {required && <span className="text-red-600"> *</span>}
      </span>
      {children}
      {error ? (
        <span className="block text-[11px] text-red-600">{error}</span>
      ) : (
        hint && <span className="block text-[11px] text-muted-foreground">{hint}</span>
      )}
    </label>
  )
}

export function Select({
  value, options, onChange, error,
}: { value: string; options: string[]; onChange: (v: string) => void; error?: string }) {
  return (
    <select className={inputCls(error)} value={value} onChange={(e) => onChange(e.target.value)}>
      {options.map((o) => <option key={o} value={o}>{o}</option>)}
    </select>
  )
}

export function Switch({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) {
  return (
    <div className="flex items-center gap-2 text-sm">
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        aria-label={label}
        onClick={() => onChange(!checked)}
        className={`relative h-5 w-9 shrink-0 rounded-full transition-colors ${checked ? "bg-emerald-600" : "bg-slate-300"}`}
      >
        <span className={`absolute top-0.5 size-4 rounded-full bg-white shadow transition-all ${checked ? "left-[18px]" : "left-0.5"}`} />
      </button>
      <span className="text-xs">{label}</span>
    </div>
  )
}

export function Pill({ tone, children }: { tone: "ok" | "empty" | "new"; children: ReactNode }) {
  const cls = {
    ok: "border-emerald-200 bg-emerald-50 text-emerald-700",
    empty: "border-slate-200 bg-slate-100 text-slate-600",
    new: "border-blue-200 bg-blue-50 text-blue-700",
  }[tone]
  return (
    <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium ${cls}`}>
      <span className="size-1.5 rounded-full bg-current" />
      {children}
    </span>
  )
}

export function Notice({ tone, title, children }: { tone: "error" | "warn" | "ok"; title: string; children: ReactNode }) {
  const cls = {
    error: "border-red-200 bg-red-50 text-red-800",
    warn: "border-amber-200 bg-amber-50 text-amber-800",
    ok: "border-emerald-200 bg-emerald-50 text-emerald-800",
  }[tone]
  const icon = tone === "ok" ? "✓" : "⚠"
  return (
    <div className={`rounded-md border p-3 text-xs ${cls}`}>
      <p className="font-semibold">{icon} {title}</p>
      <div className="mt-0.5 pl-4">{children}</div>
    </div>
  )
}

export function SideCard({ title, hint, id, children }: { title: string; hint: string; id?: string; children: ReactNode }) {
  return (
    <div id={id} className="scroll-mt-4 overflow-hidden rounded-lg border bg-white">
      <div className="flex items-baseline gap-2 border-b px-4 py-2.5">
        <h3 className="text-sm font-bold">{title}</h3>
        <span className="text-[11px] text-muted-foreground">{hint}</span>
      </div>
      {children}
    </div>
  )
}
