import type { AttendanceRecapData } from '@/features/dashboard/types/attendanceRecap';

export const getAttendanceRecap = async (): Promise<AttendanceRecapData[]> => {
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve([
        { id: "1", name: "Andi Saputra", dept: "Produksi · Shift Pagi", initials: "AS", date: "24 Okt 2026", status: "Hadir", in: "07:58", out: "17:02", dur: "9j 04m", late: "0m", note: "Tepat Waktu", noteColor: "text-[#EA232A] bg-[#EA232A]/10", avatarBg: "bg-[#EA232A]/10 text-[#EA232A]" },
        { id: "2", name: "Budi Santoso", dept: "Operasional · Shift Pagi", initials: "BS", date: "24 Okt 2026", status: "Terlambat", in: "08:17", out: "17:10", dur: "8j 53m", late: "17m", note: "Terlambat 17m", noteColor: "text-[#FAA419] bg-[#F0D00B]/20", avatarBg: "bg-[#616161]/10 text-[#616161]" },
        { id: "3", name: "Citra Lestari", dept: "Quality Control", initials: "CL", date: "24 Okt 2026", status: "Hadir", in: "07:45", out: "16:50", dur: "9j 05m", late: "0m", note: "Tepat Waktu", noteColor: "text-[#EA232A] bg-[#EA232A]/10", avatarBg: "bg-[#EA232A]/10 text-[#EA232A]" },
        { id: "4", name: "Dimas Pratama", dept: "Produksi · Shift Siang", initials: "DP", date: "24 Okt 2026", status: "Izin", in: "—", out: "—", dur: "—", late: "—", note: "Keperluan 1 jam", noteColor: "text-[#616161] bg-[#616161]/10", avatarBg: "bg-[#616161]/10 text-[#616161]" },
        { id: "5", name: "Eka Wulandari", dept: "Logistik", initials: "EW", date: "24 Okt 2026", status: "Terlambat", in: "08:42", out: "17:05", dur: "8j 23m", late: "42m", note: "Terlambat 42m", noteColor: "text-[#FAA419] bg-[#F0D00B]/20", avatarBg: "bg-[#EA232A]/10 text-[#EA232A]" },
        { id: "6", name: "Fajar Nugroho", dept: "Maintenance", initials: "FN", date: "24 Okt 2026", status: "Tidak Masuk", in: "—", out: "—", dur: "—", late: "—", note: "Tanpa keterangan", noteColor: "text-[#852622] bg-[#852622]/10", avatarBg: "bg-[#616161]/10 text-[#616161]" },
        { id: "7", name: "Gilang Adi", dept: "Produksi · Shift Pagi", initials: "GA", date: "24 Okt 2026", status: "Hadir", in: "07:55", out: "17:00", dur: "9j 05m", late: "0m", note: "Tepat Waktu", noteColor: "text-[#EA232A] bg-[#EA232A]/10", avatarBg: "bg-[#EA232A]/10 text-[#EA232A]" },
        { id: "8", name: "Herman Susanto", dept: "Operasional · Shift Siang", initials: "HS", date: "23 Okt 2026", status: "Pulang Awal", in: "23:00", out: "00:30", dur: "1j 30m", late: "—", note: "Pulang awal 1j 30m", noteColor: "text-[#FAA419] bg-[#F0D00B]/20", avatarBg: "bg-[#616161]/10 text-[#616161]" },
      ]);
    }, 500);
  });
};

// FUNGSI BARU UNTUK VERIFIKASI (Diperlukan oleh useAttendanceRecap)
export const verifyAttendance = async (ids: string[]): Promise<boolean> => {
  return new Promise((resolve) => {
    // Gunakan console.log agar parameter 'ids' terpakai dan tidak error ESLint
    console.log("Memverifikasi data absensi dengan ID:", ids);
    
    setTimeout(() => resolve(true), 1000);
  });
};