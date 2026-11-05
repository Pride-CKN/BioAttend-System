export const apiEndpoints = {
  attendanceCorrections: "/v1/attendance/corrections",
  attendanceCorrection: (id: string) =>
    `/v1/attendance/corrections/${encodeURIComponent(id)}`,
  attendanceCorrectionApprove: (id: string) =>
    `/v1/attendance/corrections/${encodeURIComponent(id)}/approve`,
  attendanceCorrectionReject: (id: string) =>
    `/v1/attendance/corrections/${encodeURIComponent(id)}/reject`,
  rawLogs: "/v1/attendance/raw-logs",
  rawLogDevices: "/v1/devices",
} as const
