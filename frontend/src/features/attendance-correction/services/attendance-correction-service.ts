import { attendanceCorrections } from "@/mocks/attendance"
import type { AttendanceCorrection, CorrectionStatus } from "@/features/attendance-correction/types"

export type CreateAttendanceCorrectionInput = Omit<
  AttendanceCorrection,
  "id" | "status" | "decisionNote" | "submittedAt"
>

// Saat ini memakai mock; ganti implementasinya dengan apiClient saat Laravel siap.
export async function getAttendanceCorrections(): Promise<AttendanceCorrection[]> {
  return attendanceCorrections.map((correction) => ({ ...correction }))
}

// Mengubah status satu pengajuan koreksi dan menyimpan catatan opsional.
export async function updateCorrectionStatus(
  id: string,
  status: CorrectionStatus,
  note?: string,
): Promise<AttendanceCorrection> {
  const correction = attendanceCorrections.find((item) => item.id === id)
  if (!correction) {
    throw new Error(`Correction ${id} not found`)
  }

  correction.status = status
  if (note) correction.decisionNote = note
  return { ...correction }
}

// Mengubah status beberapa pengajuan dalam satu operasi bulk.
export async function updateCorrectionsStatus(
  ids: string[],
  status: CorrectionStatus,
): Promise<AttendanceCorrection[]> {
  return Promise.all(ids.map((id) => updateCorrectionStatus(id, status)))
}

// Membuat pengajuan koreksi baru dengan ID dan status pending.
export async function createAttendanceCorrection(
  input: CreateAttendanceCorrectionInput,
): Promise<AttendanceCorrection> {
  const correction: AttendanceCorrection = {
    ...input,
    id: `COR-2026-${String(attendanceCorrections.length + 1).padStart(3, "0")}`,
    status: "pending",
    submittedAt: new Date().toISOString().slice(0, 16).replace("T", " "),
  }
  attendanceCorrections.unshift(correction)
  return { ...correction }
}
