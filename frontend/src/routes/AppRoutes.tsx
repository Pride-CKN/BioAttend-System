import { lazy } from "react";
import { Link, Navigate, Route, Routes } from "react-router";
import Layout from "@/layout";

const EmployeeList = lazy(() => import("@/features/employee/employeeList"));
const EmployeeDetail = lazy(() => import("@/features/employee/employeeDetail"));
const EmployeeForm = lazy(() => import("@/features/employee/employeeForm"));
const DepartmentList = lazy(
  () => import("@/features/department/departmentList"),
);
const DepartmentForm = lazy(
  () => import("@/features/department/departmentForm"),
);
const DashboardPage = lazy(() => import("@/features/dashboard/DashboardPage"));
const LoginPage = lazy(() => import("@/features/login/LoginPage"));
const AttendanceRecapPage = lazy(
  () => import("@/features/AttendanceRecapPage/AttendanceRecapPage"),
);
const RawLogsPage = lazy(() =>
  import("@/features/raw-logs/pages/raw-logs-page").then((module) => ({
    default: module.RawLogsPage,
  })),
);
const AttendanceCorrectionPage = lazy(() =>
  import("@/features/attendance-correction/pages/attendance-correction-page").then(
    (module) => ({ default: module.AttendanceCorrectionPage }),
  ),
);
const ShiftSchedulePage = lazy(() =>
  import("@/features/shift-schedule/pages/shift-schedule-page").then(
    (module) => ({
      default: module.ShiftSchedulePage,
    }),
  ),
);

function NotFound() {
  return (
    <div className="flex min-h-screen flex-1 flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
      <p>Halaman tidak ditemukan.</p>
      <Link to="/employee" className="text-red-700 hover:underline">
        Ke daftar karyawan
      </Link>
    </div>
  );
}

export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<LoginPage />} />
      <Route element={<Layout />}>
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/dashboard/attendance" element={<AttendanceRecapPage />} />
        <Route path="/employee" element={<EmployeeList />} />
        <Route path="/employee/new" element={<EmployeeForm />} />
        <Route path="/employee/:id" element={<EmployeeDetail />} />
        <Route path="/employee/:id/edit" element={<EmployeeForm />} />
        <Route path="/department" element={<DepartmentList />} />
        <Route path="/department/new" element={<DepartmentForm />} />
        <Route path="/department/:id/edit" element={<DepartmentForm />} />
        <Route path="/raw-logs" element={<RawLogsPage />} />
        <Route path="/attendance-correction" element={<AttendanceCorrectionPage />} />
        <Route path="/shift-schedule" element={<ShiftSchedulePage />} />
        <Route path="*" element={<NotFound />} />
      </Route>
    </Routes>
  );
}
