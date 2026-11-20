import { Edit, Users } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Switch } from "@/components/ui/switch"
import { Card, CardContent } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import type { Position } from "../Position.types"
import { PositionStatus } from "./position-status"

function PositionToggle({ item, onToggle }: { item: Position; onToggle: (item: Position) => void }) {
  return (
    <Switch checked={item.active} aria-label={`${item.active ? "Nonaktifkan" : "Aktifkan"} ${item.name}`} onCheckedChange={() => onToggle(item)} />
  )
}

export function PositionTable({ items, loading, onEdit, onHolders, onToggle }: { items: Position[]; loading: boolean; onEdit: (item: Position) => void; onHolders: (item: Position) => void; onToggle: (item: Position) => void }) {
  return (
    <Card className="overflow-hidden py-0">
      <CardContent className="p-0">
        {loading ? <div className="space-y-3 p-5">{Array.from({ length: 5 }).map((_, index) => <Skeleton key={index} className="h-10 w-full" />)}</div> : items.length === 0 ? <p className="p-10 text-center text-sm text-muted-foreground">Tidak ada jabatan yang sesuai filter.</p> : (
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow className="bg-muted/50">
                  <TableHead>Nama Jabatan</TableHead>
                  <TableHead>Level</TableHead>
                  <TableHead>Pemegang</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="w-24 text-right">Aksi</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {items.map((item) => <TableRow key={item.id}>
                  <TableCell>
                    <div className="font-medium">{item.name}</div>
                  </TableCell>
                  <TableCell>{item.level}</TableCell>
                  <TableCell>
                    <Button type="button" variant="link" size="sm" className="h-auto gap-1 p-0" onClick={() => onHolders(item)}>
                      <Users className="size-3.5" />{item.holderCount}
                    </Button>
                  </TableCell>
                  <TableCell><PositionStatus active={item.active} /></TableCell>
                  <TableCell>
                    <div className="flex justify-end items-center gap-2">
                      <Button variant="outline" size="icon-sm" aria-label={`Edit ${item.name}`} title="Edit jabatan" onClick={() => onEdit(item)}>
                        <Edit />
                      </Button>
                      <PositionToggle item={item} onToggle={onToggle} />
                    </div>
                  </TableCell>
                </TableRow>)}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
      <div className="border-t px-4 py-2 text-xs text-muted-foreground">Menampilkan {items.length} jabatan</div>
    </Card>
  )
}