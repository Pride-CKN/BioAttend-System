import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { LEVEL_VALUES } from "../lib/position-options";
import type { PositionFilters } from "../Position.types";
import { Plus } from "lucide-react";

export function PositionFilters({
  filters,
  onChange,
  onReset,
  onButtonClick,
}: {
  filters: PositionFilters;
  onChange: <K extends keyof PositionFilters>(
    key: K,
    value: PositionFilters[K],
  ) => void;
  onReset: () => void;
  onButtonClick: () => void; // 1. Added type definition
}) {
  return (
    <div className="flex flex-wrap items-center gap-2 border-b p-3">
      <Input
        value={filters.search}
        onChange={(event) => onChange("search", event.target.value)}
        placeholder="Cari kode atau nama jabatan"
        className="w-full sm:max-w-[280px]"
      />
      <Select
        value={filters.level}
        onValueChange={(value) => onChange("level", value ?? "all")}
      >
        <SelectTrigger className="w-40">
          <SelectValue placeholder="Semua level" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">Semua level</SelectItem>
          {LEVEL_VALUES.map((level) => (
            <SelectItem key={level} value={String(level)}>
              {level}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Select
        value={filters.status}
        onValueChange={(value) => onChange("status", value ?? "all")}
      >
        <SelectTrigger className="w-36">
          <SelectValue placeholder="Semua status" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">Semua status</SelectItem>
          <SelectItem value="active">Aktif</SelectItem>
          <SelectItem value="inactive">Nonaktif</SelectItem>
        </SelectContent>
      </Select>
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="rounded-full border-[#080808] bg-transparent px-4 text-[#080808] transition duration-200 hover:-translate-y-0.5 hover:border-[#EA232A] hover:bg-white hover:text-[#EA232A] active:translate-y-0"
        onClick={onReset}
      >
        Reset filter
      </Button>
      {/* 2, 3 & 4. Fixed type, variant, and onClick handler */}
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="rounded-full border-[#080808] bg-transparent px-4 text-[#080808] transition duration-200 hover:-translate-y-0.5 hover:border-[#EA232A] hover:bg-white hover:text-[#EA232A] active:translate-y-0"
        onClick={onButtonClick}
      >
        <Plus className="mr-1.5 h-4 w-4" />
        Tambah Jabatan
      </Button>
    </div>
  );
}
