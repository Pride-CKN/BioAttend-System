import type { DashboardDataType } from '@/features/dashboard/types/dashboard';

export const getDashboardData = async (): Promise<DashboardDataType> => {
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve({
        kpiCards: [
          { title: "Total Karyawan", val: "248", sub: "▲ 4 orang bulan ini", icon: "Users", color: "text-[#080808]", subColor: "text-[#616161]", borderColor: "border-l-[#080808]" },
          { title: "Hadir", val: "228", sub: "91.9% tingkat kehadiran", icon: "UserCheck", color: "text-[#EA232A]", subColor: "text-[#616161]", borderColor: "border-l-[#EA232A]" },
          { title: "Terlambat", val: "14", sub: "▲ 3 dari bulan lalu", icon: "Clock", color: "text-[#FAA419]", subColor: "text-[#FAA419]", borderColor: "border-l-[#F0D00B]" },
          { title: "Tidak Masuk", val: "6", sub: "2 menunggu izin approval", icon: "UserX", color: "text-[#852622]", subColor: "text-[#616161]", borderColor: "border-l-[#852622]" },
          { title: "Cuti / Izin", val: "11", sub: "3 pending, 8 disetujui", icon: "CalendarDays", color: "text-[#C4954D]", subColor: "text-[#616161]", borderColor: "border-l-[#C4954D]" },
          { title: "Potongan Rata-rata", val: "3.2%", sub: "▲ 0.4% periode berjalan", icon: "Banknote", color: "text-[#CA373D]", subColor: "text-[#CA373D]", borderColor: "border-l-[#CA373D]" },
        ],
        trendData: Array.from({ length: 30 }, (_, i) => ({
          date: `${i + 1}`,
          hadir: 80 + Math.floor(Math.random() * 20),
        })),
        donutData: [
          { name: "Hadir", value: 228, color: "#EA232A" },
          { name: "Terlambat", value: 14, color: "#F0D00B" },
          { name: "Izin", value: 7, color: "#616161" },
          { name: "Cuti", value: 4, color: "#C4954D" },
          { name: "Tidak Masuk", value: 6, color: "#080808" },
        ],
        barData: [
          { name: "Produksi", value: 142 },
          { name: "Operasional", value: 38 },
          { name: "Quality Control", value: 26 },
          { name: "Logistik", value: 14 },
          { name: "Maintenance", value: 8 },
        ],
        tableData: [
          { name: "Dhika Munawar", dept: "Produksi · Shift Pagi", status: "Hadir", in: "07:58", out: "17:02", dur: "9j 04m", note: "Tepat Waktu" },
          { name: "Rangga Driya", dept: "Operasional · Shift Pagi", status: "Hadir", in: "08:17", out: "17:10", dur: "8j 53m", note: "Terlambat 17m" },
          { name: "Dimas Aja", dept: "Quality Control", status: "Hadir", in: "07:45", out: "16:50", dur: "9j 05m", note: "Tepat Waktu" },
          { name: "Dhika Munawar", dept: "Produksi · Shift Siang", status: "Izin", in: "—", out: "—", dur: "—", note: "Keperluan 1 jam" },
          { name: "Dhika Munawar", dept: "Logistik", status: "Terlambat", in: "08:42", out: "17:05", dur: "8j 23m", note: "Terlambat 42m" },
          { name: "Dhika Munawar", dept: "Maintenance", status: "Tidak Masuk", in: "—", out: "—", dur: "—", note: "Tanpa keterangan" },
        ],
      });
    }, 500);
  });
};