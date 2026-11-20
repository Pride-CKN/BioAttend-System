import type { PositionLevel } from "../Position.types"

export const LEVEL_NAMES: Record<PositionLevel, string> = { 1: "DIREKTUR", 2: "MANAGER", 3: "STAFF" }
export const LEVEL_VALUES: PositionLevel[] = [1, 2, 3]
const DEFAULT_LEVEL: PositionLevel = 3

const NAME_TO_LEVEL = Object.fromEntries(
  LEVEL_VALUES.map((v) => [LEVEL_NAMES[v], v])
) as Record<string, PositionLevel>

export function parseLevel(raw: string | number | null | undefined): PositionLevel {
  if (raw == null) return DEFAULT_LEVEL
  const text = String(raw).trim().toUpperCase()
  const n = Number(text)
  if (LEVEL_VALUES.includes(n as PositionLevel)) return n as PositionLevel
  return NAME_TO_LEVEL[text] ?? DEFAULT_LEVEL
}
