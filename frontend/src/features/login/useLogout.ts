import { useNavigate } from "react-router"
import { useAuthStore } from "@/lib/authStore"

// Hapus sesi lalu arahkan kembali ke halaman login.
export function useLogout() {
  const navigate = useNavigate()
  const logout = useAuthStore((state) => state.logout)

  return async () => {
    try { await logout() } catch { /* store already cleared in finally */ }
    navigate("/login", { replace: true })
  }
}