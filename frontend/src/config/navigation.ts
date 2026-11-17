import { lazy, type ComponentType, type LazyExoticComponent } from "react";
import { 
  Home, 
  Users, 
  Folder,
  Calendar, 
  ChartNoAxesColumn, 
  Database, 
  ClipboardCheck, 
  CalendarCheck, 
  Settings, 
  type LucideIcon 
} from "lucide-react";

export type MenuItem = {
  name: string;
  href: string;
  icon: LucideIcon;
  component: LazyExoticComponent<ComponentType<unknown>>;
};

export type MenuGroup = {
  label: string;
  icon: LucideIcon;
  items: MenuItem[];
};

export const menuGroups: MenuGroup[] = [
  {
    label: "Home",
    icon: Home,
    items: [
      { 
        name: "Dashboard Utama", 
        href: "/dashboard", 
        icon: Home, 
        component: lazy(() => import("@/features/dashboard/DashboardPage")) 
      },
    ],
  },
  {
    label: "Dashboard",
    icon: Folder,
    items: [
      { 
        name: "Karyawan", 
        href: "/employee", 
        icon: Users, 
        component: lazy(() => import("@/features/employee/employeeList")) 
      },
      { 
        name: "Divisi & Unit",
        href: "/division",
        icon: Folder, 
        component: lazy(() => import("@/features/department/departmentList")) 
      },
    ],
  },
  {
    label: "Absensi",
    icon: Calendar,
    items: [
      {
        name: "Rekap Absensi",
        href: "/dashboard/attendance",
        icon: ChartNoAxesColumn,
        component: lazy(() => import("@/features/AttendanceRecapPage/AttendanceRecapPage")),
      },
      { 
        name: "Log Absensi Mentah", 
        href: "/raw-logs", 
        icon: Database, 
        component: lazy(() => import("@/features/raw-logs/pages/raw-logs-page").then(m => ({ default: m.RawLogsPage }))) 
      },
      {
        name: "Koreksi Absensi",
        href: "/attendance-correction",
        icon: ClipboardCheck,
        component: lazy(() => import("@/features/attendance-correction/pages/attendance-correction-page").then(m => ({ default: m.AttendanceCorrectionPage }))),
      },
      {
        name: "Jadwal & Shift",
        href: "/shift-schedule",
        icon: CalendarCheck,
        component: lazy(() => import("@/features/shift-schedule/pages/shift-schedule-page").then(m => ({ default: m.ShiftSchedulePage }))),
      },
    ],
  },
  {
    label: "Pengaturan",
    icon: Settings,
    items: [
    ],
  },
];