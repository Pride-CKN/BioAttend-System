import { Navigate } from "react-router"
import { useAuthStore } from "@/lib/authStore"

// Penjaga route: kalau belum login, lempar ke /login.
export default function RequireAuth({ children }: { children: React.ReactNode }) {
  const isAuthenticated = useAuthStore((state) => Boolean(state.token) && !state.isExpired())
  return isAuthenticated ? <>{children}</> : <Navigate to="/login" replace />
}