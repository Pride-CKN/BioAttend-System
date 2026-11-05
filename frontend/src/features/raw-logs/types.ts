export type LogStatus = "valid" | "duplicate" | "error";
export type LogType = "check_in" | "check_out" | "duplicate" | "unknown";
export type Processing = "accepted" | "ignored" | "rejected" | "queued";

export type RawLog = {
  id: number;
  scannedAt: string;
  device: string;
  deviceId: string;
  pin: string;
  employee: string | null;
  status: LogStatus;
  type: LogType;
  processing: Processing;
};

export type Device = { id: string; name: string };
export type Employee = { pin: string; name: string; active: boolean };

export const statusLabel: Record<LogStatus, string> = {
  valid: "Valid",
  duplicate: "Duplicate",
  error: "Error",
};

export const typeLabel: Record<LogType, string> = {
  check_in: "Check-In",
  check_out: "Check-Out",
  duplicate: "Duplicate",
  unknown: "Unknown PIN",
};

export const processingLabel: Record<Processing, string> = {
  accepted: "Accepted",
  ignored: "Ignored",
  rejected: "Rejected",
  queued: "Queued",
};