import { useState, useMemo, useEffect } from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  Search, Download, RefreshCw, Calendar,
  ChevronLeft, ChevronRight, MoreHorizontal, Users, Clock, UserX, CalendarDays, Loader2
} from "lucide-react";

// --- IMPORT SERVICE & TYPES ---
import { getAttendanceRecap } from "@/features/dashboard/services/attendanceRecapService";
import type { AttendanceRecapData } from "@/features/dashboard/types/attendanceRecap";
import { StatusBadge } from "@/components/shared";

export default function AttendanceRecapPage() {
  const [searchTerm, setSearchTerm] = useState("");
  const [data, setData] = useState<AttendanceRecapData[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  // FETCH DATA DARI SERVICE
  useEffect(() => {
    const fetchData = async () => {
      try {
        const result = await getAttendanceRecap();
        setData(result);
      } catch (error) {
        console.error("Gagal mengambil data", error);
      } finally {
        setIsLoading(false);
      }
    };
    fetchData();
  }, []);

  const filteredData = useMemo(() => {
    return data.filter(item => 
      item.name.toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [data, searchTerm]);

  return (
    <div className="w-full min-w-0 space-y-4 pb-10">
      
      {/* 1. HEADER */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <p className="text-xs text-[#616161] font-medium mb-1">Dashboard / Absensi / Rekap Absensi</p>
          <h1 className="text-2xl font-bold tracking-tight text-[#080808]">Attendance Recap</h1>
          <p className="text-[#616161] text-sm mt-1">Rekap kehadiran seluruh karyawan berdasarkan Rentang, departemen, dan status kehadiran.</p>
        </div>
        <div className="flex flex-col sm:flex-row w-full md:w-auto gap-2 shrink-0">
          <Button variant="outline" size="sm" className="w-full sm:w-auto gap-2 border-[#616161]/30 text-[#080808]">
            <RefreshCw className="h-3.5 w-3.5" /> Refresh
          </Button>
          <Button size="sm" className="w-full sm:w-auto bg-[#EA232A] hover:bg-[#852622] text-white gap-2">
            <Download className="h-3.5 w-3.5" /> Export
          </Button>
        </div>
      </div>

      {/* 2. KPI CARDS */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 w-full">
        <Card className="shadow-sm border-l-4 border-l-[#080808]">
          <CardContent className="p-4 flex flex-col justify-between h-full">
            <div className="flex justify-between items-start mb-2">
              <p className="text-xs font-semibold text-[#616161]">Total Record</p>
              <Users className="h-4 w-4 text-[#080808]" />
            </div>
            <h3 className="text-2xl font-bold text-[#080808]">259</h3>
            <p className="text-[11px] mt-1 text-[#616161]">▲ 8 dari bulan ini</p>
          </CardContent>
        </Card>
        <Card className="shadow-sm border-l-4 border-l-[#EA232A]">
          <CardContent className="p-4 flex flex-col justify-between h-full">
            <div className="flex justify-between items-start mb-2">
              <p className="text-xs font-semibold text-[#616161]">Hadir</p>
              <Users className="h-4 w-4 text-[#EA232A]" />
            </div>
            <h3 className="text-2xl font-bold text-[#EA232A]">228</h3>
            <p className="text-[11px] mt-1 text-[#616161]">87.9% dari total</p>
          </CardContent>
        </Card>
        <Card className="shadow-sm border-l-4 border-l-[#F0D00B]">
          <CardContent className="p-4 flex flex-col justify-between h-full">
            <div className="flex justify-between items-start mb-2">
              <p className="text-xs font-semibold text-[#616161]">Terlambat</p>
              <Clock className="h-4 w-4 text-[#FAA419]" />
            </div>
            <h3 className="text-2xl font-bold text-[#FAA419]">14</h3>
            <p className="text-[11px] mt-1 text-[#FAA419]">▲ 3 dari bulan lalu</p>
          </CardContent>
        </Card>
        <Card className="shadow-sm border-l-4 border-l-[#616161]">
          <CardContent className="p-4 flex flex-col justify-between h-full">
            <div className="flex justify-between items-start mb-2">
              <p className="text-xs font-semibold text-[#616161]">Pulang Awal</p>
              <CalendarDays className="h-4 w-4 text-[#616161]" />
            </div>
            <h3 className="text-2xl font-bold text-[#616161]">9</h3>
            <p className="text-[11px] mt-1 text-[#616161]">3.47% dari total</p>
          </CardContent>
        </Card>
        <Card className="shadow-sm border-l-4 border-l-[#852622]">
          <CardContent className="p-4 flex flex-col justify-between h-full">
            <div className="flex justify-between items-start mb-2">
              <p className="text-xs font-semibold text-[#616161]">Tidak Masuk</p>
              <UserX className="h-4 w-4 text-[#852622]" />
            </div>
            <h3 className="text-2xl font-bold text-[#852622]">6</h3>
            <p className="text-[11px] mt-1 text-[#616161]">2.32% dari total</p>
          </CardContent>
        </Card>
      </div>

      {/* 3. FILTER BAR */}
      <Card className="shadow-sm relative z-20 w-full min-w-0">
        <CardContent className="p-2 sm:p-1 flex flex-col lg:flex-row flex-wrap items-stretch lg:items-center gap-2 lg:gap-0">
          <div className="flex items-center gap-2 pl-3 pr-2 lg:border-r border-b lg:border-b-0 border-[#616161]/20 pb-2 lg:pb-0 min-w-0">
            <Search className="h-4 w-4 text-[#616161] shrink-0" />
            <Input 
              placeholder="Cari Nama / NIP karyawan..." 
              className="h-8 border-0 bg-transparent text-sm focus-visible:ring-0 shadow-none px-0 text-[#080808] placeholder:text-[#616161] min-w-[200px]"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <div className="flex items-center gap-2 px-3 lg:border-r border-b lg:border-b-0 border-[#616161]/20 pb-2 lg:pb-0">
            <span className="text-xs text-[#616161]">Tanggal</span>
            <div className="flex items-center gap-1 border border-[#616161]/30 rounded px-2 py-1 bg-white">
              <span className="text-xs font-medium text-[#080808]">10/24/2026</span>
              <Calendar className="h-3 w-3 text-[#616161]" />
            </div>
          </div>
          <div className="flex flex-wrap gap-2 px-2">
            <Select defaultValue="semua-dept">
              <SelectTrigger className="h-8 w-auto min-w-[120px] border-0 bg-transparent text-xs focus:ring-0 shadow-none px-2 text-[#080808]">
                <SelectValue placeholder="Semua Departemen" />
              </SelectTrigger>
              <SelectContent className="bg-white border shadow-md z-50">
                <SelectItem value="semua-dept">Semua Departemen</SelectItem>
                <SelectItem value="produksi">Produksi</SelectItem>
              </SelectContent>
            </Select>
            <Select defaultValue="semua-div">
              <SelectTrigger className="h-8 w-auto min-w-[110px] border-0 bg-transparent text-xs focus:ring-0 shadow-none px-2 text-[#080808]">
                <SelectValue placeholder="Semua Divisi" />
              </SelectTrigger>
              <SelectContent className="bg-white border shadow-md z-50">
                <SelectItem value="semua-div">Semua Divisi</SelectItem>
              </SelectContent>
            </Select>
            <Select defaultValue="semua-status">
              <SelectTrigger className="h-8 w-auto min-w-[110px] border-0 bg-transparent text-xs focus:ring-0 shadow-none px-2 text-[#080808]">
                <SelectValue placeholder="Status" />
              </SelectTrigger>
              <SelectContent className="bg-white border shadow-md z-50">
                <SelectItem value="semua-status">Semua Status</SelectItem>
                <SelectItem value="hadir">Hadir</SelectItem>
              </SelectContent>
            </Select>
            <Select defaultValue="semua-shift">
              <SelectTrigger className="h-8 w-auto min-w-[110px] border-0 bg-transparent text-xs focus:ring-0 shadow-none px-2 text-[#080808]">
                <SelectValue placeholder="Semua Shift" />
              </SelectTrigger>
              <SelectContent className="bg-white border shadow-md z-50">
                <SelectItem value="semua-shift">Semua Shift</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="w-full lg:w-auto lg:ml-auto pr-2 pt-2 lg:pt-0 border-t lg:border-t-0 border-[#616161]/20 shrink-0 flex gap-2 justify-end">
            <Button variant="outline" size="sm" className="gap-2 text-xs h-8 text-[#080808] border-[#616161]/30">
              <Download className="h-3.5 w-3.5" /> Export Excel
            </Button>
            <Button variant="outline" size="sm" className="gap-2 text-xs h-8 text-[#080808] border-[#616161]/30">
              <RefreshCw className="h-3.5 w-3.5" /> Hitung Ulang
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* 4. TABEL DATA */}
      <Card className="shadow-sm overflow-hidden w-full min-w-0">
        <CardHeader className="py-3 flex flex-row justify-between items-center border-b space-y-0">
          <div>
            <CardTitle className="text-sm font-bold text-[#080808]">Rekap Absensi Harian</CardTitle>
            <CardDescription className="text-xs text-[#616161]">Periode 24 Oktober 2026</CardDescription>
          </div>
          <div className="flex bg-[#616161]/10 rounded-md p-1">
            <Button size="sm" className="h-6 text-xs bg-white text-[#080808] shadow-sm hover:bg-white">Tabel</Button>
            <Button size="sm" variant="ghost" className="h-6 text-xs text-[#616161] hover:bg-transparent">Matrix</Button>
            <Button size="sm" variant="ghost" className="h-6 text-xs text-[#616161] hover:bg-transparent">Kalender</Button>
          </div>
        </CardHeader>
        
        <div className="w-full overflow-x-auto">
          <Table className="min-w-[900px]">
            <TableHeader className="bg-[#616161]/5">
              <TableRow>
                <TableHead className="text-xs font-semibold text-[#616161] w-[250px]">KARYAWAN</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">TANGGAL</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">STATUS</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">JAM MASUK</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">JAM KELUAR</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">DURASI</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">TERLAMBAT</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">CATATAN</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={8} className="h-32 text-center text-[#616161]">
                    <Loader2 className="h-6 w-6 animate-spin mx-auto mb-2 text-[#EA232A]" />
                    Memuat data absensi...
                  </TableCell>
                </TableRow>
              ) : filteredData.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="h-32 text-center text-[#616161]">
                    Tidak ada data absensi yang ditemukan.
                  </TableCell>
                </TableRow>
              ) : (
                filteredData.map((row, i) => (
                  <TableRow key={i} className="text-sm border-[#616161]/10 hover:bg-[#616161]/5">
                    <TableCell className="font-medium whitespace-nowrap">
                      <div className="flex items-center gap-3">
                        <Avatar className="h-8 w-8">
                          <AvatarFallback className={`text-xs font-bold ${row.avatarBg}`}>
                            {row.initials}
                          </AvatarFallback>
                        </Avatar>
                        <div>
                          <p className="text-[#080808]">{row.name}</p>
                          <p className="text-[10px] text-[#616161] font-normal">{row.dept}</p>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="text-[#080808]">{row.date}</TableCell>
                    <TableCell>
                      <StatusBadge status={row.status} />
                    </TableCell>
                    <TableCell className="text-[#080808]">{row.in}</TableCell>
                    <TableCell className="text-[#080808]">{row.out}</TableCell>
                    <TableCell className="text-[#080808]">{row.dur}</TableCell>
                    <TableCell className="text-[#080808]">{row.late}</TableCell>
                    <TableCell>
                      <span className={`text-[10px] px-2 py-1 rounded-sm font-medium whitespace-nowrap ${row.noteColor}`}>
                        {row.note}
                      </span>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {/* 5. FOOTER PAGINATION */}
        <div className="flex flex-col md:flex-row justify-between items-center p-3 border-t border-[#616161]/10 text-xs text-[#616161] gap-3">
          <p>Menampilkan {filteredData.length} dari 259 data</p>
          <div className="flex items-center gap-1">
            <Button variant="outline" size="icon" className="h-7 w-7 border-[#616161]/30 text-[#080808]"><ChevronLeft className="h-3.5 w-3.5" /></Button>
            <Button size="icon" className="h-7 w-7 bg-[#EA232A] text-white">1</Button>
            <Button variant="outline" size="icon" className="h-7 w-7 border-[#616161]/30 text-[#080808]">2</Button>
            <Button variant="outline" size="icon" className="h-7 w-7 border-[#616161]/30 text-[#080808]">3</Button>
            <Button variant="outline" size="icon" className="h-7 w-7 border-[#616161]/30 text-[#080808]">4</Button>
            <Button variant="outline" size="icon" className="h-7 w-7 border-[#616161]/30 text-[#080808]">5</Button>
            <Button variant="ghost" size="icon" className="h-7 w-7 text-[#616161]"><MoreHorizontal className="h-3.5 w-3.5" /></Button>
            <Button variant="outline" size="icon" className="h-7 w-7 border-[#616161]/30 text-[#080808]">25</Button>
            <Button variant="outline" size="icon" className="h-7 w-7 border-[#616161]/30 text-[#080808]"><ChevronRight className="h-3.5 w-3.5" /></Button>
          </div>
        </div>
      </Card>
    </div>
  );
}