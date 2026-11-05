export type CorrectionStatus = "pending" | "approved" | "rejected"

export type AttendanceCorrection = {
  id: string
  employee: string
  date: string
  requestedType: string
  originalValue: string
  correctedValue: string
  reason: string
  requester: string
  status: CorrectionStatus
  decisionNote?: string
  submittedAt: string
}
