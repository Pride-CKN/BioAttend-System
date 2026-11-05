import type { ViewTab } from "../types"

const tabs: Array<{ value: ViewTab; label: string }> = [
  { value: "master", label: "Daftar Shift" },
  { value: "schedule", label: "Daftar Jadwal" },
  { value: "template", label: "Template Mingguan" },
  { value: "history", label: "Riwayat Perubahan" },
]

// Menampilkan navigasi antar bagian pengelolaan shift dan jadwal.
export function ShiftTabs({
  value,
  onChange,
}: {
  value: ViewTab
  onChange: (value: ViewTab) => void
}) {
  return (
    <div className="flex gap-5 overflow-x-auto px-4">
      {tabs.map((tab) => (
        <button
          key={tab.value}
          type="button"
          onClick={() => onChange(tab.value)}
          className={`whitespace-nowrap border-b-2 px-1 py-3 text-xs font-medium ${
            value === tab.value
              ? "border-[var(--brand-red)] text-[var(--brand-red)]"
              : "border-transparent text-muted-foreground hover:text-foreground"
          }`}
        >
          {tab.label}
        </button>
      ))}
    </div>
  )
}
