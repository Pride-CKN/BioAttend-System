import { Navigate, useLocation } from "react-router"
import { useAuthStore } from "@/lib/authStore"

// Penjaga route: kalau belum login, lempar ke /login.
export default function RequireAuth({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((state) => Boolean(state.token) && !state.isExpired())
  const mustChange = useAuthStore((s) => s.user?.must_change_password ?? false)
  const location = useLocation()
  if (isAuthenticated && mustChange) {
    return <Navigate to="/change-password" replace />
  }
  return isAuthenticated
    ? <>{children}</>
    : <Navigate to="/login" replace state={{ from: location }} />
}

export function RequireRole({ roles, children }: { roles: string[]; children: React.ReactNode }) {
  const userRoles = useAuthStore((s) => s.user?.roles)
  const allowed = userRoles?.some((r) => roles.includes(r))
  return allowed ? <>{children}</> : <Navigate to="/forbidden" replace />
}