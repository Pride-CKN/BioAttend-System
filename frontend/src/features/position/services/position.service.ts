import {
  masterCreate,
  masterGet,
  masterList,
  masterSetActive,
  masterUpdate,
} from "@/api/master";
import type { Position, PositionHolder, PositionInput } from "../Position.types";
import { LEVEL_VALUES, parseLevel } from "../lib/position-options";

type PositionDto = {
  id: number;
  code: string;
  name: string;
  level: string | null;
  is_active: boolean;
  employees_count?: number;
};

const mapPosition = (d: PositionDto): Position => ({
  id: String(d.id),
  code: d.code,
  name: d.name,
  level: parseLevel(d.level),
  active: d.is_active,
  holderCount: d.employees_count ?? 0,
});

const toBody = (i: {
  code: string;
  name: string;
  level: number | string | null;
}) => ({
  code: i.code.trim().toUpperCase(),
  name: i.name.trim(),
  level: i.level == null ? null : String(i.level),
});

function validate(input: PositionInput) {
  if (!input.code.trim()) throw new Error("Kode jabatan wajib diisi.");
  if (!input.name.trim()) throw new Error("Nama jabatan wajib diisi.");
  if (!LEVEL_VALUES.includes(input.level)) {
    throw new Error(`Level harus salah satu dari: ${LEVEL_VALUES.join(", ")}.`);
  }
}

export async function getPositions(): Promise<Position[]> {
  return (await masterList<PositionDto>("positions")).map(mapPosition);
}

export async function getPositionHolders(
  _positionId: string,
): Promise<PositionHolder[]> {
  return [];
}

export async function createPosition(input: PositionInput): Promise<Position> {
  validate(input);
  const created = await masterCreate<PositionDto>("positions", {
    ...toBody(input),
    is_active: input.active,
  });
  return mapPosition(created);
}

export async function updatePosition(
  id: string,
  input: PositionInput,
): Promise<Position> {
  validate(input);
  await masterUpdate("positions", id, {
    ...toBody(input),
    is_active: input.active,
  });
  return mapPosition(await masterGet<PositionDto>("positions", id));
}

export async function setPositionActive(
  id: string,
  active: boolean,
): Promise<Position> {
  return mapPosition(
    await masterSetActive<PositionDto>("positions", id, active, toBody),
  );
}
