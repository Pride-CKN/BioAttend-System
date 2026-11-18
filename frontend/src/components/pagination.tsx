import { memo } from "react"
import { PaginationControls } from "@/components/ui/pagination-controls"

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
  return <PaginationControls page={page} pageCount={totalPages} from={from} to={to} total={total} itemLabel="division" onChange={onPage} className="border-t px-3 py-3" />
}

export const Pagination = memo(PaginationBase)

