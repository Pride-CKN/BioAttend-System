import { memo } from "react";
import { Link } from "react-router";
import { TableCell, TableRow } from "@/components/ui/table";
import type {
  Division,
  DivisionStatus,
} from "@/features/division/types/division.type";
import {
  formatShortDate,
  headName,
} from "@/features/division/lib/division.Utils";
import { StatusSwitch } from "@/features/division/components/statusSwitch";
import { Edit } from "lucide-react";
import { Button } from "@/components/ui/button";

type RowProps = {
  div: Division;
  onToggle: (id: string, next: DivisionStatus) => void;
};

const badge = (status: DivisionStatus) =>
  status === "Aktif"
    ? "bg-emerald-50 text-emerald-700 border-emerald-200"
    : "bg-gray-100 text-gray-600 border-gray-200";

const editLink =
  "rounded-md border px-2.5 py-1 text-xs font-medium hover:bg-gray-50";

/* Desktop: satu baris tabel. memo → hanya baris yang datanya berubah yang re-render. */
export const DivisionRow = memo(function DivisionRow({
  div,
  onToggle,
}: RowProps) {
  return (
    <TableRow className="text-sm">
      <TableCell>
        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">
          {div.code}
        </span>
      </TableCell>
      <TableCell className="font-semibold">{div.name}</TableCell>
      <TableCell className="text-muted-foreground">
        {div.units.length
          ? div.units.map((unit) => unit.name).join(", ")
          : "—"}
      </TableCell>
      <TableCell className="whitespace-nowrap">
        <span
          className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs ${badge(div.status)}`}
        >
          <span aria-hidden="true">●</span>
          {div.status}
        </span>
      </TableCell>
      <TableCell>{headName(div.headId)}</TableCell>
      <TableCell className="text-muted-foreground">
        {formatShortDate(div.updatedAt)}
      </TableCell>
      <TableCell className="whitespace-nowrap text-center">
        <div className="flex items-center justify-center gap-2">
          <Button
            variant="outline"
            size="icon-sm"
            aria-label={`Edit ${div.name}`}
            title="Edit divisi"
            render={<Link to={`/division/${div.id}/edit`} />}
          >
            <Edit />
          </Button>
          <StatusSwitch
            id={div.id}
            name={div.name}
            status={div.status}
            onToggle={onToggle}
          />
        </div>
      </TableCell>
    </TableRow>
  );
});

/* Mobile: kartu division. */
export const DivisionCard = memo(function DivisionCard({
  div,
  onToggle,
}: RowProps) {
  return (
    <li className="rounded-lg border bg-white p-3">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium">
            {div.code}
          </span>
          <p className="mt-1 truncate font-semibold">{div.name}</p>
          <p className="text-xs text-muted-foreground">
            {div.units.map((unit) => unit.name).join(", ") || "Belum ada unit"}
          </p>
        </div>
        <span
          className={`shrink-0 rounded-full border px-2 py-0.5 text-xs ${badge(div.status)}`}
        >
          ● {div.status}
        </span>
      </div>
      <dl className="mt-3 grid grid-cols-2 gap-2 text-xs">
        <div>
          <dt className="text-muted-foreground">Jumlah Unit</dt>
          <dd className="font-medium">{div.units.length}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Kepala Unit</dt>
          <dd className="truncate font-medium">{headName(div.headId)}</dd>
        </div>
      </dl>
      <div className="mt-3 flex items-center justify-between border-t pt-3">
        <StatusSwitch
          id={div.id}
          name={div.name}
          status={div.status}
          onToggle={onToggle}
        />
        <Link to={`/division/${div.id}/edit`} className={editLink}>
          Edit
        </Link>
      </div>
    </li>
  );
});
