"use client"

import { useEffect, useState } from "react"
import { useNavigate } from "react-router"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { StatusBadge } from "@/components/shared"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Button } from "@/components/ui/button"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Users, UserCheck, UserX, Clock, CalendarDays, Banknote,
  Calendar, Download, RefreshCw, AlertTriangle, Info,
  ChevronLeft, ChevronRight, MoreHorizontal,
  type LucideIcon
} from "lucide-react"
import {
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip as RechartsTooltip,
  ResponsiveContainer, PieChart, Pie, Cell, BarChart, Bar
} from "recharts"

import { getDashboardData } from "@/features/dashboard/services/dashboardService"
import type { DashboardDataType } from "@/features/dashboard/types/dashboard"

// PENTING: Mapper untuk mengubah string dari API menjadi Komponen Ikon
const IconMap: Record<string, LucideIcon> = {
  Users,
  UserCheck,
  Clock,
  UserX,
  CalendarDays,
  Banknote,
};

function getNoteColor(note: string) {
  if (note.includes("Terlambat")) return "text-[#FAA419]"
  if (note.includes("Tepat")) return "text-[#EA232A]"
  if (note.includes("Keperluan")) return "text-[#616161]"
  return "text-[#852622]"
}

export default function DashboardPage() {
  const navigate = useNavigate() // <-- PANGGIL HOOK NAVIGASI
  const [data, setData] = useState<DashboardDataType | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    const fetchData = async () => {
      try {
        const result = await getDashboardData()
        setData(result)
      } catch (error) {
        console.error("Gagal mengambil data dashboard", error)
      } finally {
        setIsLoading(false)
      }
    }
    fetchData()
  }, [])

  if (isLoading || !data) {
    return (
      <div className="w-full h-[80vh] flex items-center justify-center text-[#616161]">
        <RefreshCw className="animate-spin h-6 w-6 mr-2" /> Memuat data dashboard...
      </div>
    )
  }

  return (
    <div className="w-full min-w-0 space-y-4 pb-10">
      
      {/* 1. HEADER */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <p className="text-xs text-[#616161] font-medium mb-1">Dashboard / Dashboard HRD</p>
          <h1 className="text-2xl font-bold tracking-tight text-[#080808]">Dashboard HRD</h1>
          <p className="text-[#616161] text-sm mt-1 max-w-2xl">Ringkasan absensi, payroll deduction, dan indikator workforce PT Pride Chicken</p>
        </div>
        <div className="flex flex-col sm:flex-row w-full md:w-auto gap-2 shrink-0">
          <Button variant="outline" size="sm" className="w-full sm:w-auto gap-2 text-[#080808] border-[#616161]/30 hover:bg-[#616161]/10">
            <RefreshCw className="h-3.5 w-3.5" /> Refresh
          </Button>
          <Button size="sm" className="w-full sm:w-auto bg-[#EA232A] hover:bg-[#852622] text-white gap-2">
            <Users className="h-3.5 w-3.5" /> Tanya AI Agent
          </Button>
        </div>
      </div>
    {/* Hai */}


      {/* 2. KPI CARDS */}
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 w-full">
        {data.kpiCards.map((kpi, i) => {
          const IconComponent = IconMap[kpi.icon] || Users;
          return (
            <Card key={i} className={`shadow-sm border-l-4 min-w-0 ${kpi.borderColor}`}>
              <CardContent className="p-4 flex flex-col justify-between h-full">
                <div className="flex justify-between items-start mb-2">
                  <p className="text-xs font-semibold text-[#616161] line-clamp-1">{kpi.title}</p>
                  <IconComponent className={`h-4 w-4 shrink-0 ${kpi.color}`} />
                </div>
                <h3 className={`text-2xl font-bold ${kpi.color}`}>{kpi.val}</h3>
                <p className={`text-[11px] mt-1 ${kpi.subColor} line-clamp-2`}>{kpi.sub}</p>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {/* 3. FILTER BAR */}
      <Card className="shadow-sm relative z-20 w-full min-w-0">
        <CardContent className="p-2 sm:p-1 flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 sm:gap-0">
          <div className="flex items-center gap-2 sm:pl-3 sm:pr-2 sm:border-r border-b sm:border-b-0 border-[#616161]/20 pb-2 sm:pb-0 min-w-0">
            <Calendar className="h-4 w-4 text-[#616161] ml-1 sm:ml-0 shrink-0" />
            <Select defaultValue="oktober">
              <SelectTrigger className="h-8 w-full sm:w-[140px] border-0 bg-transparent text-sm font-medium focus:ring-0 focus:outline-none shadow-none px-0 text-[#080808]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-white border border-gray-200 shadow-md z-50">
                <SelectItem value="oktober">Oktober 2026</SelectItem>
                <SelectItem value="september">September 2026</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Select defaultValue="semua-dept">
            <SelectTrigger className="h-8 w-full sm:w-auto min-w-[150px] border-0 bg-transparent text-sm focus:ring-0 focus:outline-none shadow-none px-1 sm:px-3 text-[#080808]">
              <SelectValue placeholder="Semua Departemen" />
            </SelectTrigger>
            <SelectContent className="bg-white border border-gray-200 shadow-md z-50">
              <SelectItem value="semua-dept">Semua Departemen</SelectItem>
              <SelectItem value="produksi">Produksi</SelectItem>
              <SelectItem value="operasional">Operasional</SelectItem>
            </SelectContent>
          </Select>
          <Select defaultValue="semua-div">
            <SelectTrigger className="h-8 w-full sm:w-auto min-w-[130px] border-0 bg-transparent text-sm focus:ring-0 focus:outline-none shadow-none px-1 sm:px-3 text-[#080808]">
              <SelectValue placeholder="Semua Divisi" />
            </SelectTrigger>
            <SelectContent className="bg-white border border-gray-200 shadow-md z-50">
              <SelectItem value="semua-div">Semua Divisi</SelectItem>
            </SelectContent>
          </Select>
          <Select defaultValue="semua-shift">
            <SelectTrigger className="h-8 w-full sm:w-auto min-w-[130px] border-0 bg-transparent text-sm focus:ring-0 focus:outline-none shadow-none px-1 sm:px-3 text-[#080808]">
              <SelectValue placeholder="Semua Shift" />
            </SelectTrigger>
            <SelectContent className="bg-white border border-gray-200 shadow-md z-50">
              <SelectItem value="semua-shift">Semua Shift</SelectItem>
              <SelectItem value="pagi">Shift Pagi</SelectItem>
              <SelectItem value="siang">Shift Siang</SelectItem>
            </SelectContent>
          </Select>
          <div className="w-full sm:w-auto sm:ml-auto sm:pr-2 pt-2 sm:pt-0 border-t sm:border-t-0 border-[#616161]/20 shrink-0">
            <Button variant="outline" size="sm" className="w-full sm:w-auto gap-2 text-xs h-8 text-[#080808] border-[#616161]/30 hover:bg-[#616161]/10">
              <Download className="h-3.5 w-3.5" /> Export Data
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* 4. GRAFIK SECTION */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 relative z-10 w-full min-w-0">
        <Card className="lg:col-span-2 shadow-sm min-w-0">
          <CardHeader className="pb-2 flex flex-row justify-between items-center border-b space-y-0">
            <div>
              <CardTitle className="text-sm font-bold text-[#080808]">Tren Kehadiran — 30 Hari Terakhir</CardTitle>
              <CardDescription className="text-xs text-[#616161]">Persentase hadir per hari.</CardDescription>
            </div>
            <Select defaultValue="30">
              <SelectTrigger className="h-7 w-[90px] text-xs border-[#616161]/30 text-[#080808]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent className="bg-white border border-gray-200 shadow-md z-50">
                <SelectItem value="30">30 Hari</SelectItem>
                <SelectItem value="90">90 Hari</SelectItem>
                <SelectItem value="365">1 Tahun</SelectItem>
              </SelectContent>
            </Select>
          </CardHeader>
          <CardContent className="h-[250px] pt-4 px-2 sm:px-6">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={data.trendData} margin={{ top: 5, right: 10, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorHadir" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#EA232A" stopOpacity={0.2} />
                    <stop offset="95%" stopColor="#EA232A" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <XAxis dataKey="date" axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: "#616161" }} />
                <YAxis axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: "#616161" }} domain={[0, 100]} tickFormatter={(v) => `${v}%`} />
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#f3f4f6" />
                <RechartsTooltip />
                <Area type="monotone" dataKey="hadir" stroke="#EA232A" strokeWidth={2} fillOpacity={1} fill="url(#colorHadir)" dot={{ r: 2, fill: "#EA232A" }} activeDot={{ r: 4 }} />
              </AreaChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card className="shadow-sm min-w-0">
          <CardHeader className="pb-0 border-b">
            <CardTitle className="text-sm font-bold text-[#080808]">Komposisi Absensi</CardTitle>
            <CardDescription className="text-xs text-[#616161]">Bulan berjalan</CardDescription>
          </CardHeader>
          <CardContent className="h-auto lg:h-[250px] p-4 flex items-center justify-center">
            <div className="grid grid-cols-1 sm:grid-cols-2 w-full h-full items-center gap-4 lg:gap-0">
              <div className="relative flex justify-center items-center h-[150px] sm:h-full w-full">
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie data={data.donutData} cx="50%" cy="50%" innerRadius={50} outerRadius={70} paddingAngle={2} dataKey="value" stroke="none">
                      {data.donutData.map((entry, index) => (<Cell key={`cell-${index}`} fill={entry.color} />))}
                    </Pie>
                    <RechartsTooltip content={() => null} />
                  </PieChart>
                </ResponsiveContainer>
                <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                  <span className="text-2xl font-bold text-[#080808]">259</span>
                  <span className="text-[10px] text-[#616161] text-center leading-tight">Total Karyawan<br/>Hari Ini</span>
                </div>
              </div>
              <div className="flex flex-col gap-2 px-2 sm:pl-4 w-full">
                {data.donutData.map((item) => (
                  <div key={item.name} className="flex items-center justify-between text-xs sm:text-sm">
                    <div className="flex items-center gap-2">
                      <div className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: item.color }} />
                      <span className="text-[#616161]">{item.name}</span>
                    </div>
                    <span className="font-semibold text-[#080808]">{item.value}</span>
                  </div>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* 5. BAR CHART & ALERTS */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 w-full min-w-0">
        <Card className="shadow-sm min-w-0">
          <CardHeader className="pb-2 border-b">
            <CardTitle className="text-sm font-bold text-[#080808]">Perbandingan Kehadiran per Departemen</CardTitle>
            <CardDescription className="text-xs text-[#616161]">Persentase hadir bulan berjalan</CardDescription>
          </CardHeader>
          <CardContent className="h-[220px] pt-4 px-2 sm:px-6">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data.barData} layout="vertical" margin={{ top: 0, right: 30, left: 10, bottom: 0 }}>
                <XAxis type="number" hide />
                <YAxis dataKey="name" type="category" axisLine={false} tickLine={false} tick={{ fontSize: 10, fill: "#616161" }} width={90} />
                <RechartsTooltip cursor={{ fill: "transparent" }} />
                <Bar dataKey="value" fill="#CA373D" radius={[0, 4, 4, 0]} barSize={10} label={{ position: "right", fill: "#616161", fontSize: 10 }} />
              </BarChart>
            </ResponsiveContainer>
            <p className="text-[10px] text-[#616161] text-right mt-2">Total 228 hadir dari 248 karyawan aktif</p>
          </CardContent>
        </Card>

        <Card className="shadow-sm min-w-0">
          <CardHeader className="py-3 flex flex-row justify-between items-center border-b space-y-0">
            <CardTitle className="text-sm font-bold flex items-center gap-2 text-[#080808]">
              <AlertTriangle className="h-4 w-4 text-[#EA232A]" /> Alert & Indikator
            </CardTitle>
            <Button variant="link" className="text-xs h-auto p-0 text-[#CA373D]">Lihat semua</Button>
          </CardHeader>
          <CardContent className="p-0">
            <div className="p-3 border-b bg-[#EA232A]/5 flex items-start gap-3">
              <AlertTriangle className="h-4 w-4 text-[#EA232A] mt-0.5 shrink-0" />
              <div>
                <p className="text-sm font-semibold text-[#EA232A]">5 karyawan tidak masuk tanpa keterangan</p>
                <p className="text-xs text-[#616161]">Segera hubungi: Ricky, Olla, Dimas, Fajar, Hendra, Yoga.</p>
              </div>
            </div>
            <div className="p-3 border-b bg-[#F0D00B]/10 flex items-start gap-3">
              <Clock className="h-4 w-4 text-[#FAA419] mt-0.5 shrink-0" />
              <div>
                <p className="text-sm font-semibold text-[#FAA419]">3 pengajuan cuti menunggu approval</p>
                <p className="text-xs text-[#616161]">Total 4 hari kerja. Deadline approval 3 hari lagi.</p>
              </div>
            </div>
            <div className="p-3 bg-[#616161]/5 flex items-start gap-3">
              <Info className="h-4 w-4 text-[#616161] mt-0.5 shrink-0" />
              <div>
                <p className="text-sm font-semibold text-[#616161] flex flex-wrap items-center gap-2">
                  Perangkat ZKTeco
                  <Badge className="bg-[#616161]/20 text-[#616161] text-[10px] px-1.5 py-0 hover:bg-[#616161]/30">sedang offline</Badge>
                </p>
                <p className="text-xs text-[#616161]">Terakhir sinkron 08:12 • retry otomatis dijadwalkan.</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* 6. TABEL ABSENSI */}
      <Card className="shadow-sm overflow-hidden w-full min-w-0">
        <CardHeader className="py-3 flex flex-col sm:flex-row justify-between items-start sm:items-center border-b space-y-2 sm:space-y-0">
          <div>
            <CardTitle className="text-sm font-bold text-[#080808]">Absensi Terbaru — Hari Ini</CardTitle>
            <CardDescription className="text-xs text-[#616161]">Live dari perangkat (24 Okt 2026)</CardDescription>
          </div>
          <Button variant="outline" size="sm" className="text-xs h-7 w-full sm:w-auto text-[#080808] border-[#616161]/30 hover:bg-[#616161]/10 shrink-0">Lihat Selengkapnya</Button>
        </CardHeader>
        
        <div className="w-full overflow-x-auto">
          <Table className="min-w-[700px] sm:min-w-full">
            <TableHeader className="bg-[#616161]/5">
              <TableRow>
                <TableHead className="text-xs font-semibold text-[#616161]">KARYAWAN</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">STATUS</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">JAM MASUK</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">JAM KELUAR</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">DURASI</TableHead>
                <TableHead className="text-xs font-semibold text-[#616161]">CATATAN</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.tableData.map((row, i) => {
                const initials = row.name.split(" ").map((n) => n[0]).join("")
                return (
                  <TableRow 
                    key={i} 
                    // FITUR BARU: Klik baris untuk pindah ke detail absensi
                    onClick={() => navigate(`/dashboard/attendance/${i + 1}`)} 
                    className="text-sm border-[#616161]/10 cursor-pointer hover:bg-[#616161]/5 transition-colors"
                  >
                    <TableCell className="font-medium whitespace-nowrap">
                      <div className="flex items-center gap-3">
                        <Avatar className="h-8 w-8">
                          <AvatarFallback className={`text-xs font-bold ${i % 2 === 0 ? "bg-[#EA232A]/10 text-[#EA232A]" : "bg-[#616161]/10 text-[#616161]"}`}>{initials}</AvatarFallback>
                        </Avatar>
                        <div>
                          <p className="text-[#080808]">{row.name}</p>
                          <p className="text-[10px] text-[#616161] font-normal">{row.dept}</p>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={row.status} />
                    </TableCell>
                    <TableCell className="text-[#080808]">{row.in}</TableCell>
                    <TableCell className="text-[#080808]">{row.out}</TableCell>
                    <TableCell className="text-[#080808] whitespace-nowrap">{row.dur}</TableCell>
                    <TableCell><span className={`text-xs font-medium whitespace-nowrap ${getNoteColor(row.note)}`}>{row.note}</span></TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </div>
      </Card>

      {/* FOOTER / PAGINATION */}
      <div className="flex flex-col md:flex-row justify-between items-center text-xs text-[#616161] px-2 gap-3 w-full min-w-0">
        <p className="text-center md:text-left truncate">Menampilkan 6 dari 259 data • Sinkron terakhir 08:12</p>
        <div className="flex items-center gap-1 overflow-x-auto max-w-full pb-2 md:pb-0">
          <Button variant="outline" size="icon" className="h-7 w-7 shrink-0 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10"><ChevronLeft className="h-3.5 w-3.5" /></Button>
          <Button size="icon" className="h-7 w-7 shrink-0 bg-[#EA232A] hover:bg-[#852622] text-white">1</Button>
          <Button variant="outline" size="icon" className="h-7 w-7 shrink-0 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10">2</Button>
          <Button variant="outline" size="icon" className="h-7 w-7 shrink-0 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10">3</Button>
          <Button variant="outline" size="icon" className="h-7 w-7 shrink-0 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10">4</Button>
          <Button variant="outline" size="icon" className="h-7 w-7 shrink-0 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10">5</Button>
          <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0 text-[#616161] hover:bg-[#616161]/10" disabled><MoreHorizontal className="h-3.5 w-3.5" /></Button>
          <Button variant="outline" size="icon" className="h-7 w-7 shrink-0 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10">25</Button>
          <Button variant="outline" size="icon" className="h-7 w-7 shrink-0 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10"><ChevronRight className="h-3.5 w-3.5" /></Button>
        </div>
      </div>

      {/* FLOATING CHAT WIDGET */}
      <div className="fixed bottom-4 right-4 bg-[#080808] text-white text-[10px] sm:text-xs px-3 sm:px-4 py-2 rounded-full shadow-lg flex items-center gap-2 cursor-pointer z-50 hover:bg-[#616161] transition-colors border border-[#616161]/30">
        <div className="w-3 h-3 sm:w-4 sm:h-4 bg-[#EA232A] rounded-full flex items-center justify-center shrink-0"><div className="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-white rounded-full" /></div>
        <span className="whitespace-nowrap">Diperbarui 08:15</span>
      </div>
    </div>
  )
}