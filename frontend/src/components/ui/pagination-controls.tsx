import type { MouseEvent } from "react"
import { cn } from "cn"
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination"

type Props = {
  page: number
  pageCount: number
  onChange: (page: number) => void
  from?: number
  to?: number
  total?: number
  itemLabel?: string
  className?: string
}

function pageWindow(page: number, pageCount: number): Array<number | "ellipsis"> {
  const pages = [...new Set([1, page - 1, page, page + 1, pageCount])]
    .filter((value) => value >= 1 && value <= pageCount)
    .sort((left, right) => left - right)
  const result: Array<number | "ellipsis"> = []

  pages.forEach((value, index) => {
    if (index > 0 && value - pages[index - 1] > 1) result.push("ellipsis")
    result.push(value)
  })

  return result
}

export function PaginationControls({
  page,
  pageCount,
  onChange,
  from,
  to,
  total,
  itemLabel = "item",
  className,
}: Props) {
  const navigate = (nextPage: number) => (event: MouseEvent<HTMLAnchorElement>) => {
    event.preventDefault()
    if (nextPage >= 1 && nextPage <= pageCount && nextPage !== page) onChange(nextPage)
  }

  return <div className={cn("flex flex-col items-center justify-between gap-3 text-xs text-muted-foreground sm:flex-row", className)}>
    {total !== undefined && <span>{total ? `Menampilkan ${from ?? 0}–${to ?? 0} dari ${total} ${itemLabel}` : `Tidak ada ${itemLabel}`}</span>}
    <Pagination className="mx-0 w-auto">
      <PaginationContent>
        <PaginationItem>
          <PaginationPrevious href="#" text="Sebelumnya" aria-disabled={page <= 1} tabIndex={page <= 1 ? -1 : 0} onClick={navigate(page - 1)} />
        </PaginationItem>
        {pageWindow(page, pageCount).map((value, index) => value === "ellipsis" ? (
          <PaginationItem key={`ellipsis-${index}`}><PaginationEllipsis /></PaginationItem>
        ) : (
          <PaginationItem key={value}>
            <PaginationLink href="#" isActive={value === page} aria-label={`Ke halaman ${value}`} onClick={navigate(value)}>{value}</PaginationLink>
          </PaginationItem>
        ))}
        <PaginationItem>
          <PaginationNext href="#" text="Berikutnya" aria-disabled={page >= pageCount} tabIndex={page >= pageCount ? -1 : 0} onClick={navigate(page + 1)} />
        </PaginationItem>
      </PaginationContent>
    </Pagination>
  </div>
}