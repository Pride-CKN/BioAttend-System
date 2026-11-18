import { memo } from "react";
import { Download, Plus, Search } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/components/ui/input-group";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

import type {
  EmployeeFilters,
  EmployeeFormOptions,
  EmployeeMasterOption,
} from "@/features/employee/types/employee.type";

type Props = {
  filters: EmployeeFilters;
  options?: EmployeeFormOptions;
  onChange: <K extends keyof EmployeeFilters>(
    key: K,
    value: EmployeeFilters[K],
  ) => void;
  onExport: () => void;
  onAdd: () => void;
};

type FilterSelectProps = {
  all: string;
  value: string;
  options: EmployeeMasterOption[];
  onChange: (value: string) => void;
};

function FilterSelect({ all, value, options, onChange }: FilterSelectProps) {
  return (
    <Select
      value={value || "all"}
      onValueChange={(v) => onChange(v === "all" ? "" : v)}
    >
      <SelectTrigger className="h-9 w-full sm:w-auto sm:min-w-37.5">
        <SelectValue placeholder={all} />
      </SelectTrigger>

      <SelectContent>
        <SelectItem value="all">{all}</SelectItem>
        {options.map((option) => (
          <SelectItem key={option.id} value={option.id}>
            {option.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

export const EmployeeFilterBar = memo(function EmployeeFilterBar({
  filters,
  options,
  onChange,
  onExport,
  onAdd,
}: Props) {
  return (
    <Card className="rounded-lg shadow-none">
      <CardContent className="flex flex-wrap items-center gap-3 p-3">
        {/* Search */}
        <InputGroup className="w-full sm:w-64">
          <InputGroupAddon>
            <Search className="size-4 text-muted-foreground" />
          </InputGroupAddon>
          <InputGroupInput
            placeholder="Cari nama, ID, atau PIN"
            value={filters.keyword}
            onChange={(e) => onChange("keyword", e.target.value)}
          />
        </InputGroup>

        {/* Filters */}
        <FilterSelect
          all="Semua Kantor"
          value={filters.officeId}
          options={options?.offices ?? []}
          onChange={(value) => onChange("officeId", value)}
        />

        <FilterSelect
          all="Semua Division"
          value={filters.divisionId}
          options={options?.divisions ?? []}
          onChange={(value) => onChange("divisionId", value)}
        />

        <FilterSelect
          all="Semua Unit"
          value={filters.unitId}
          options={options?.units ?? []}
          onChange={(value) => onChange("unitId", value)}
        />

        <Select
          value={filters.employmentStatus || "all"}
          onValueChange={(value) =>
            onChange("employmentStatus", value === "all" ? "" : (value ?? ""))
          }
        >
          <SelectTrigger className="h-9 w-full sm:w-auto sm:min-w-37.5">
            <SelectValue placeholder="Semua Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Semua Status</SelectItem>
            <SelectItem value="ACTIVE">Aktif</SelectItem>
            <SelectItem value="INACTIVE">Nonaktif</SelectItem>
            <SelectItem value="RESIGNED">Resigned</SelectItem>
          </SelectContent>
        </Select>

        {/* Actions */}
        <div className="flex w-full gap-2 sm:ml-auto sm:w-auto">
          <Button
            size="sm"
            className="rounded-full border-[#080808] bg-transparent px-4 text-[#080808] transition duration-200 hover:-translate-y-0.5 hover:border-[#EA232A] hover:bg-white hover:text-[#EA232A] active:translate-y-0"
            onClick={onExport}
          >
            <Download className="h-3.5 w-3.5" /> Export
          </Button>
          <Button
            size="sm"
            className="rounded-full border-[#080808] bg-transparent px-4 text-[#080808] transition duration-200 hover:-translate-y-0.5 hover:border-[#EA232A] hover:bg-white hover:text-[#EA232A] active:translate-y-0"
            onClick={onAdd}
          >
            <Plus />
            Tambah Karyawan
          </Button>
        </div>
      </CardContent>
    </Card>
  );
});
