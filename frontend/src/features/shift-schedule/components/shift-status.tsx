export function ShiftStatus({ active }: { active: boolean }) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-medium ${
        active
          ? "bg-emerald-50 text-emerald-700"
          : "bg-[#616161]/10 text-[#616161]"
      }`}
    >
      <span
        className={`size-1.5 rounded-full ${
          active ? "bg-emerald-500" : "bg-[#616161]"
        }`}
      />
      {active ? "Aktif" : "Nonaktif"}
    </span>
  )
}
