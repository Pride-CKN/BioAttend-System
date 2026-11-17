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

  // Auth & Profile
  authActivate: "/auth/activate",
  authChangePassword: "/auth/change-password",
  authForgotPassword: "/auth/forgot-password",
  authLogin: "/auth/login",
  authLogout: "/auth/logout",
  authLogoutAll: "/auth/logout-all",
  authProfile: "/auth/profile",
  authSessions: "/auth/sessions",
  authSession: (id: string) =>
    `/auth/sessions/${encodeURIComponent(id)}`,
} as const
