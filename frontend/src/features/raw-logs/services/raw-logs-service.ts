import { devices } from "@/mocks/master-data"
import { initialLogs } from "@/mocks/raw-logs"
import type { Device, RawLog } from "@/features/raw-logs/types"

export async function getRawLogs(): Promise<RawLog[]> {
  return initialLogs
}

export async function getRawLogDevices(): Promise<Device[]> {
  return devices
}
