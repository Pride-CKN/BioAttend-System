export type RecapStatus = "Hadir" | "Terlambat" | "Tidak Masuk" | "Izin" | "Pulang Awal";

export type AttendanceRecapData = {
  id: string;
  name: string;
  dept: string;
  initials: string;
  date: string;
  status: RecapStatus;
  in: string;
  out: string;
  dur: string;
  late: string;
  note: string;
  noteColor: string;
  avatarBg: string;
};