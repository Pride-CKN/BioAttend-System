import type { DivisionInput } from "@/features/division/types/division.type"

export type DivisionErrors = Partial<Record<keyof DivisionInput, string>>

export function validateDivision(v: DivisionInput): DivisionErrors {
  const errors: DivisionErrors = {}
  if (v.name.trim().length < 3) errors.name = "Nama minimal 3 karakter"
  return errors
}
