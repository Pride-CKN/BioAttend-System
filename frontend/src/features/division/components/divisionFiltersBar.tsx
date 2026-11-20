import { memo } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type { DivisionFilters } from "@/features/division/types/division.type";
import { divisionStatuses } from "@/features/division/lib/divisionConfig";

type Props = {
  filters: DivisionFilters;
  units: string[];
  onChange: (patch: Partial<DivisionFilters>) => void;
};

function DivisionFiltersBarBase({ filters, units, onChange }: Props) {
  return (
    <Card className="py-0">
      <CardContent className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
        <Input
          value={filters.search}
          onChange={(event) => onChange({ search: event.target.value })}
          placeholder="Cari division / unit"
          aria-label="Cari division"
          className="sm:max-w-64"
        />
        <Select
          value={filters.unit || "all"}
          onValueChange={(value) =>
            onChange({ unit: value === "all" ? "" : (value ?? "") })
          }
        >
          <SelectTrigger className="w-full sm:w-48">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Semua Unit</SelectItem>
            {units.map((unit) => (
              <SelectItem key={unit} value={unit}>
                {unit}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select
          value={filters.status || "all"}
          onValueChange={(value) =>
            onChange({
              status: (value === "all"
                ? ""
                : (value ?? "")) as DivisionFilters["status"],
            })
          }
        >
          <SelectTrigger className="w-full sm:w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Semua Status</SelectItem>
            {divisionStatuses.map((status) => (
              <SelectItem key={status} value={status}>
                {status}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </CardContent>
    </Card>
  );
}

export const DivisionFiltersBar = memo(DivisionFiltersBarBase);
