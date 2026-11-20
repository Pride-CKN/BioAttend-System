import { Button } from "@/components/ui/button"
import type { Position } from "../Position.types"
import { PositionStatus } from "./position-status"

export function PositionCardList({ items, onEdit, onHolders }: { items: Position[]; onEdit: (item: Position) => void; onHolders: (item: Position) => void }) {
  return <div className="grid gap-3 md:hidden">{items.map((item) => <article key={item.id} className="rounded-lg border bg-white p-3 shadow-sm"><div className="flex items-start justify-between gap-2"><div><p className="text-xs text-muted-foreground">{item.code}</p><h3 className="font-semibold">{item.name}</h3></div><PositionStatus active={item.active} /></div><p className="mt-2 text-sm"> {item.level}</p><p className="text-xs text-muted-foreground">{item.holderCount} pemegang</p><div className="mt-3 flex gap-2"><Button size="sm" variant="outline" onClick={() => onEdit(item)}>Edit</Button><Button size="sm" variant="ghost" onClick={() => onHolders(item)}>Lihat pemegang</Button></div></article>)}</div>
}
