import type { RawLog } from "@/features/raw-logs/types"
import { devices, employees } from "./master-data"

const pad = (n: number) => String(n).padStart(2, "0")

const fromMockup: RawLog[] = [
  {
    id: 14208,
    scannedAt: "2026-10-24 17:02:38",
    device: "ZKTeco",
    deviceId: "ZKTECO-001",
    pin: "1182",
    employee: "Andi Saputra",
    status: "valid",
    type: "check_out",
    processing: "accepted",
  },
  {
    id: 14207,
    scannedAt: "2026-10-24 17:01:55",
    device: "ZKTeco",
    deviceId: "ZKTECO-001",
    pin: "1182",
    employee: "Andi Saputra",
    status: "valid",
    type: "check_out",
    processing: "accepted",
  },
  {
    id: 14206,
    scannedAt: "2026-10-24 12:31:02",
    device: "ZKTeco",
    deviceId: "ZKTECO-001",
    pin: "1182",
    employee: "Andi Saputra",
    status: "duplicate",
    type: "duplicate",
    processing: "ignored",
  },
  {
    id: 14205,
    scannedAt: "2026-10-24 08:05:44",
    device: "ZKTeco",
    deviceId: "ZKTECO-001",
    pin: "1182",
    employee: "Andi Saputra",
    status: "duplicate",
    type: "duplicate",
    processing: "ignored",
  },
  {
    id: 14204,
    scannedAt: "2026-10-24 07:58:12",
    device: "ZKTeco",
    deviceId: "ZKTECO-001",
    pin: "1182",
    employee: "Andi Saputra",
    status: "valid",
    type: "check_in",
    processing: "accepted",
  },
  {
    id: 14203,
    scannedAt: "2026-10-24 08:42:09",
    device: "ZKTeco",
    deviceId: "ZKTECO-001",
    pin: "1067",
    employee: "Eka Wulandari",
    status: "valid",
    type: "check_in",
    processing: "accepted",
  },
  {
    id: 14202,
    scannedAt: "2026-10-24 08:42:01",
    device: "ZKTeco",
    deviceId: "ZKTECO-001",
    pin: "1067",
    employee: "Eka Wulandari",
    status: "duplicate",
    type: "duplicate",
    processing: "ignored",
  },
  {
    id: 14201,
    scannedAt: "2026-10-24 08:00:00",
    device: "ZKTeco",
    deviceId: "ZKTECO-001",
    pin: "9999",
    employee: null,
    status: "error",
    type: "unknown",
    processing: "rejected",
  },
]

const generated: RawLog[] = Array.from({ length: 42 }, (_, i): RawLog => {
  const emp = employees[i % employees.length]
  const dev = devices[i % devices.length]
  const day = i < 21 ? "2026-10-23" : "2026-10-22"
  const time = `${pad(7 + (i % 11))}:${pad((i * 7) % 60)}:${pad((i * 13) % 60)}`
  const base = {
    id: 14200 - i,
    scannedAt: `${day} ${time}`,
    device: dev.name,
    deviceId: dev.id,
  }

  if (i % 9 === 4)
    return {
      ...base,
      pin: "9999",
      employee: null,
      status: "error",
      type: "unknown",
      processing: "rejected",
    }
  if (i % 11 === 6)
    return {
      ...base,
      pin: emp.pin,
      employee: emp.name,
      status: "error",
      type: "check_in",
      processing: "rejected",
    }
  if (i % 5 === 3)
    return {
      ...base,
      pin: emp.pin,
      employee: emp.name,
      status: "duplicate",
      type: "duplicate",
      processing: "ignored",
    }
  return {
    ...base,
    pin: emp.pin,
    employee: emp.name,
    status: "valid",
    type: i % 2 ? "check_out" : "check_in",
    processing: "accepted",
  }
})

export const initialLogs: RawLog[] = [...fromMockup, ...generated]
