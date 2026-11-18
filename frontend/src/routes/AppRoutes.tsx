import { lazy } from "react";
import { Navigate, Route, Routes } from "react-router";
import AuthPage from "@/features/login/pages/LoginPage";
import ActivateAccountPage from "@/features/login/pages/ActivateAccount";
import ChangePasswordPage from "@/features/login/pages/ChangePassword";
import { NotFoundPage } from "@/NotFoundPage";
import RequireAuth from "@/routes/RequireAuth";
import { useAuthStore } from "@/lib/authStore";
import Layout from "@/layout";
import { menuGroups } from "@/config/navigation";

// Standalone pages not necessarily in the main sidebar menu
const EmployeeForm = lazy(() => import("@/features/employee/employeeForm"));
const EmployeeDetail = lazy(() => import("@/features/employee/employeeDetail"));
const DivisionForm = lazy(() => import("@/features/department/departmentForm"));

function GuestOnly({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((s) => Boolean(s.token) && !s.isExpired())
  return isAuthenticated ? <Navigate to="/dashboard" replace /> : <>{children}</>
}

export default function AppRoutes() {
  // Automatically extract all menu items that have an href and a component
  const dynamicRoutes = menuGroups.flatMap((group) => group.items);

  return (
    <Routes>
      {/* Public Login Route */}
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route path="/login" element={<GuestOnly><AuthPage /></GuestOnly>} />
      <Route path="/activate" element={<GuestOnly><ActivateAccountPage /></GuestOnly>} />

      {/* Protected Layout Routes */}
      <Route path="/change-password" element={<RequireAuth><ChangePasswordPage /></RequireAuth>} />
      <Route element={<RequireAuth><Layout /></RequireAuth>}>
        {/* Dynamically mapped routes from navigation config */}
        {dynamicRoutes.map((route) => {
          const Component = route.component;
          return <Route key={route.href} path={route.href} element={<Component />} />;
        })}

        {/* Supplementary/Parametric routes not listed directly as sidebar items */}
        <Route path="/employee/new" element={<EmployeeForm />} />
        <Route path="/employee/:id" element={<EmployeeDetail />} />
        <Route path="/employee/:id/edit" element={<EmployeeForm />} />
        <Route path="/division/new" element={<DivisionForm />} />
        <Route path="/division/:id/edit" element={<DivisionForm />} />
        <Route path="/division/new" element={<DivisionForm />} />
        <Route path="/division/:id/edit" element={<DivisionForm />} />
      </Route>

      {/* 404 Catch-All */}
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
}