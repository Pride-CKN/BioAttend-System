import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";
import { NavUser } from "@/components/nav-user";
import {
  type LucideIcon,
  BarChart3,
  Bell,
  BrainCircuit,
  BriefcaseBusiness,
  Calculator,
  Calendar,
  CalendarCheck,
  ChartNoAxesColumn,
  ClipboardCheck,
  Database,
  File,
  FileChartColumn,
  Folder,
  Globe,
  Home,
  Laptop,
  ListChecks,
  Settings,
  Timer,
  UserCog,
  Users,
} from "lucide-react";
import { Link, useLocation } from "react-router";

type MenuItem = { name: string; icon: LucideIcon; href?: string };
type MenuGroup = { label: string; icon: LucideIcon; items: MenuItem[] };

const menu: MenuGroup[] = [
  {
    label: "Utama",
    icon: Home,
    items: [{ name: "Dashboard HRD", icon: Home, href: "/dashboard" }],
  },
  {
    label: "Master Data",
    icon: Folder,
    items: [
      { name: "Employee", icon: Users, href: "/employee" },
      { name: "Divisi & Unit", icon: Folder, href: "/department" },
      { name: "Position", icon: BriefcaseBusiness },
    ],
  },
  {
    label: "Absensi",
    icon: Calendar,
    items: [
      {
        name: "Attendance Recap",
        icon: ChartNoAxesColumn,
        href: "/dashboard/attendance",
      },
      { name: "Raw Attendance Logs", icon: Database, href: "/raw-logs" },
      {
        name: "Attendance Correction",
        icon: ClipboardCheck,
        href: "/attendance-correction",
      },
      {
        name: "Shift & Schedule",
        icon: CalendarCheck,
        href: "/shift-schedule",
      },
    ],
  },
  {
    label: "Pengajuan",
    icon: File,
    items: [
      { name: "Leave & Permission", icon: Calendar },
      { name: "Overtime", icon: Timer },
    ],
  },
  {
    label: "Payroll",
    icon: Calculator,
    items: [
      { name: "Deduction", icon: Calculator },
      { name: "Payroll Calculation", icon: Calculator },
    ],
  },
  {
    label: "Insight",
    icon: BarChart3,
    items: [
      { name: "AI HR Attendance Agent", icon: BrainCircuit },
      { name: "Reports", icon: FileChartColumn },
    ],
  },
  {
    label: "Pengaturan",
    icon: Settings,
    items: [
      { name: "Device Management", icon: Laptop },
      { name: "Google Integration", icon: Globe },
      { name: "Notifications", icon: Bell },
      { name: "User & Role", icon: UserCog },
      { name: "Audit Logs", icon: ListChecks },
      { name: "System Settings", icon: Settings },
    ],
  },
];

function isMenuActive(pathname: string, href?: string) {
  if (!href) return false;
  if (href === "/dashboard") return pathname === href;
  return pathname === href || pathname.startsWith(href + "/");
}

import ptPrideChickenLogo from "@/assets/pt-pride-chicken-logo.png";
// Menampilkan navigasi utama aplikasi dan identitas perusahaan.
export function AppSidebar() {
  const location = useLocation();

  return (
    <Sidebar collapsible="icon" className="border-r-0 bg-[#852622] text-white">
      <SidebarHeader className="border-sidebar-border px-3 py-3 group-data-[collapsible=icon]:px-2">
        <div className="flex items-center gap-2 group-data-[collapsible=icon]:justify-center">
          <div className="size-9 shrink-0 overflow-hidden rounded-full bg-white group-data-[collapsible=icon]:size-8">
            <img
              src={ptPrideChickenLogo}
              alt="Logo PT. Pride Chicken"
              className="size-full object-contain"
            />
          </div>
          <div className="min-w-0 group-data-[collapsible=icon]:hidden">
            <div className="truncate text-sm font-bold tracking-tight text-white">
              PT. PRIDE CHICKEN
            </div>
            <div className="truncate text-xs tracking-wide text-white/75">
              HR MANAGEMENT SYSTEM
            </div>
          </div>
        </div>
      </SidebarHeader>

      <SidebarContent>
        {menu.map((group) => (
          <SidebarGroup key={group.label}>
            <SidebarGroupLabel className="gap-2 text-white/75">
              <group.icon className="size-4" />
              {group.label}
            </SidebarGroupLabel>
            <SidebarMenu>
              {group.items.map((item) => (
                <SidebarMenuItem key={item.name}>
                  <SidebarMenuButton
                    render={item.href ? <Link to={item.href} /> : undefined}
                    isActive={isMenuActive(location.pathname, item.href)}
                    disabled={!item.href}
                    className="text-white/90 data-active:bg-[var(--brand-maroon)] data-active:text-white data-[active=true]:hover:bg-[var(--brand-maroon)] hover:bg-white/15 hover:text-white"
                  >
                    <item.icon className="size-4" />
                    {item.name}
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroup>
        ))}
      </SidebarContent>

      <SidebarFooter className="border-sidebar-border px-2 py-3 group-data-[collapsible=icon]:px-1">
        <NavUser
          user={{
            name: "Rio Pratama",
            email: "HR Manager",
            avatar: "",
          }}
        />
      </SidebarFooter>
    </Sidebar>
  );
}
