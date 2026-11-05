import { memo } from "react"

type Props = { page: number; totalPages: number; from: number; to: number; total: number; onPage: (p: number) => void }

// Jendela halaman: 1 … (page-1, page, page+1) … last — tetap ringkas walau halaman banyak.
function pageWindow(page: number, totalPages: number): (number | "…")[] {
  const set = new Set([1, totalPages, page - 1, page, page + 1].filter((p) => p >= 1 && p <= totalPages))
  const sorted = [...set].sort((a, b) => a - b)
  const out: (number | "…")[] = []
  sorted.forEach((p, i) => {
    if (i && p - sorted[i - 1] > 1) out.push("…")
    out.push(p)
  })
  return out
}

function PaginationBase({ page, totalPages, from, to, total, onPage }: Props) {
  const btn = "h-8 min-w-8 rounded-md border px-2 text-xs disabled:opacity-40"
  return (
    <div className="flex flex-col items-center justify-between gap-2 border-t px-3 py-3 text-xs text-muted-foreground sm:flex-row">
      <span>{total ? `Menampilkan ${from}–${to} dari ${total} divisi` : "Tidak ada data"}</span>
      <nav className="flex items-center gap-1" aria-label="Paginasi">
        <button className={btn} disabled={page <= 1} onClick={() => onPage(page - 1)} aria-label="Sebelumnya">‹</button>
        {pageWindow(page, totalPages).map((p, i) =>
          p === "…" ? (
            <span key={`e${i}`} className="px-1">…</span>
          ) : (
            <button
              key={p}
              onClick={() => onPage(p)}
              aria-current={p === page ? "page" : undefined}
              className={`${btn} ${p === page ? "border-red-700 bg-red-700 text-white" : "bg-white text-foreground"}`}
            >
              {p}
            </button>
          ),
        )}
        <button className={btn} disabled={page >= totalPages} onClick={() => onPage(page + 1)} aria-label="Berikutnya">›</button>
      </nav>
    </div>
  )
}

export const Pagination = memo(PaginationBase)
