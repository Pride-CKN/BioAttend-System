import { Link, useLocation } from "react-router"
import { 
  LayoutDashboard, Users, CalendarCheck, FileText, 
  ShieldAlert, LogOut, Clock, Banknote, X,
  Building2, Briefcase, CalendarOff, Timer, Calculator, 
  BrainCircuit, PieChart, Fingerprint, Globe, Activity,
  UserCog, Laptop
} from "lucide-react"
import { cn } from "@/lib/utils"
import logoPride from "@/assets/logo-pride.png"

const menuGroups = [
  { 
    title: "Utama", 
    items: [
      { name: "Dashboard HRD", icon: LayoutDashboard, path: "/dashboard" }
    ] 
  },
  { 
    title: "Manajemen Karyawan", 
    items: [
      { name: "Data Karyawan", icon: Users, path: "/employee" },
      { name: "Departemen & Divisi", icon: Building2, path: "/department" },
      { name: "Position", icon: Briefcase, path: "/dashboard/positions" },
      { name: "Jadwal & Shift", icon: Clock, path: "/shift-schedule" },
    ] 
  },
  { 
    title: "Absensi & Cuti", 
    items: [
      { name: "Rekap Absensi", icon: CalendarCheck, path: "/dashboard/attendance" },
      { name: "Pengajuan Cuti", icon: FileText, path: "/dashboard/leaves" },
      { name: "Regular Leave Log", icon: CalendarOff, path: "/dashboard/leave-log" },
      { name: "Overtime", icon: Timer, path: "/dashboard/overtime" },
    ] 
  },
  { 
    title: "Payroll & Pengaturan", 
    items: [
      { name: "Deduction", icon: Calculator, path: "/dashboard/deductions" },
      { name: "Payroll Calculation", icon: Banknote, path: "/dashboard/payroll" },
    ] 
  },
  { 
    title: "AI & Analytics", 
    items: [
      { name: "AI HR Attendance Agent", icon: BrainCircuit, path: "/dashboard/ai-agent" },
      { name: "Reports", icon: PieChart, path: "/dashboard/reports" },
    ] 
  },
  { 
    title: "Sistem & Integrasi", 
    items: [
      { name: "Device Management", icon: Fingerprint, path: "/dashboard/devices" },
      { name: "Google Integration", icon: Globe, path: "/dashboard/google" },
      { name: "Monitoring", icon: Activity, path: "/dashboard/monitoring" },
      { name: "User & Role", icon: UserCog, path: "/dashboard/users" },
      { name: "Audit Log", icon: ShieldAlert, path: "/dashboard/audit" },
      { name: "System Settings", icon: Laptop, path: "/dashboard/settings" },
    ] 
  }
]

interface SidebarProps {
  isOpen: boolean;
  setIsOpen: (val: boolean) => void;
}

export default function Sidebar({ isOpen, setIsOpen }: SidebarProps) {
  const location = useLocation()

  return (
    <>
      {/* Overlay Mobile */}
      {isOpen && (
        <div className="fixed inset-0 bg-black/50 z-[60] lg:hidden backdrop-blur-sm" onClick={() => setIsOpen(false)} />
      )}

      {/* Sidebar Container */}
      <div className={cn(
        "w-64 bg-[#080808] text-gray-300 h-screen flex flex-col fixed left-0 top-0 border-r border-gray-800 z-[70] transition-transform duration-300 ease-in-out",
        isOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0"
      )}>
        
        {/* Header Logo */}
        <div className="p-5 flex items-center justify-between border-b border-gray-800 shrink-0">
          <div className="flex items-center gap-3">
            <div className="bg-white p-1 rounded">
              <img src={logoPride} alt="Logo" className="w-6 h-6 object-contain" />
            </div>
            <div>
              <h1 className="font-bold text-sm text-white tracking-wide">PRIDE ATTENDANCE</h1>
              <p className="text-[10px] text-gray-500">HRD System</p>
            </div>
          </div>
          <button className="lg:hidden text-gray-400 hover:text-white" onClick={() => setIsOpen(false)}>
            <X size={20} />
          </button>
        </div>

        {/* Menu Navigasi */}
        <nav className="flex-1 p-3 overflow-y-auto custom-scrollbar space-y-6">
          {menuGroups.map((group, idx) => (
            <div key={idx}>
              <p className="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-2 px-3">{group.title}</p>
              <div className="space-y-0.5">
                {group.items.map((menu) => {
                  // Logika Active: Menyala jika URL sama PERSIS, atau jika berada di sub-path (kecuali dashboard root)
                  const isActive = location.pathname === menu.path || 
                                   (menu.path !== "/dashboard" && location.pathname.startsWith(menu.path + "/"))
                  
                  return (
                    <Link
                      key={menu.name}
                      to={menu.path}
                      onClick={() => setIsOpen(false)}
                      className={cn(
                        "flex items-center gap-3 px-3 py-2 rounded-md text-[13px] transition-all duration-200",
                        isActive 
                          ? "bg-[#EA232A] text-white font-medium shadow-md shadow-[#EA232A]/20" 
                          : "text-gray-400 hover:bg-gray-800/80 hover:text-white"
                      )}
                    >
                      <menu.icon size={16} className={isActive ? "text-white" : "text-gray-500"} />
                      <span className="truncate">{menu.name}</span>
                    </Link>
                  )
                })}
              </div>
            </div>
          ))}
        </nav>

        {/* Footer Profil */}
        <div className="p-4 border-t border-gray-800 shrink-0">
          <div className="flex items-center gap-3 mb-3 px-1">
             <div className="w-8 h-8 rounded-full bg-[#EA232A]/20 border border-[#EA232A]/30 flex items-center justify-center text-[#EA232A] font-bold text-xs shrink-0">SA</div>
             <div className="min-w-0">
               <p className="text-sm text-white font-medium truncate">Staf Admin</p>
               <p className="text-[11px] text-gray-500 truncate">HRD Department</p>
             </div>
          </div>
          <button 
            onClick={() => window.location.href = '/'} 
            className="flex items-center gap-3 px-3 py-2 w-full rounded-md text-sm font-medium text-[#CA373D] hover:bg-[#CA373D]/10 transition-colors"
          >
            <LogOut size={16} /> Logout
          </button>
        </div>
        
      </div>
    </>
  )
}