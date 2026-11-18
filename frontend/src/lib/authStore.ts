import { create } from "zustand"
import { createJSONStorage, persist } from "zustand/middleware"

const EXPIRY_SKEW_MS = 5_000
const MAX_TIMEOUT_MS = 2_147_483_647

export interface EmployeeProfile {
  id: number
  nip: string
  full_name: string
  division: string
  division_id: number
  unit: string
  unit_id: number
  position: string
  employment_status: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  is_active: boolean
  must_change_password: boolean
  last_login_at: string | null
  roles: string[]
  employee: EmployeeProfile | null
}

interface AuthState {
  token: string | null
  expiresAt: number | null // epoch ms, computed from expires_in at login
  user: AuthUser | null
  login: (token: string, expiresInSeconds?: number | null, user?: AuthUser | null) => void
  logout: () => void
  isExpired: () => boolean
}

type PersistedAuth = Pick<AuthState, "token" | "expiresAt" | "user">

let expiryTimer: ReturnType<typeof setTimeout> | undefined

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      expiresAt: null,
      user: null,

      login: (token, expiresInSeconds, user = null) => {
        const expiresAt = expiresInSeconds ? Date.now() + expiresInSeconds * 1000 : null
        set({ token, expiresAt, user })
        scheduleExpiry()
      },

      logout: () => {
        clearTimeout(expiryTimer)
        set({ token: null, expiresAt: null, user: null })
      },

      isExpired: () => {
        const { token, expiresAt } = get()
        return !!token && expiresAt !== null && Date.now() >= expiresAt - EXPIRY_SKEW_MS
      },
    }),
    {
      name: "auth",
      // temp before sanctum cookies
      storage: createJSONStorage(() => sessionStorage),
      partialize: (s): PersistedAuth => ({ token: s.token, expiresAt: s.expiresAt, user: s.user }),
      // Runs on page load: discard the saved token if it already expired
      merge: (persisted, current) => {
        const saved = persisted as Partial<PersistedAuth> | undefined
        if (!saved?.token) return current
        if (saved.expiresAt != null && Date.now() >= saved.expiresAt - EXPIRY_SKEW_MS) {
          return current
        }
        return { ...current, token: saved.token, expiresAt: saved.expiresAt ?? null, user: saved.user ?? null, }
      },
      onRehydrateStorage: () => () => {
        // store may not be assigned yet when storage is sync
        queueMicrotask(scheduleExpiry)
      },
    },
    
  ),
)

export const useEmployee = () => useAuthStore((s) => s.user?.employee ?? null)
export const useRoles = () => useAuthStore((s) => s.user?.roles ?? [])

export function getInitials(name?: string | null) {
  if (!name) return "?"
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join("")
}

// Auto-logout when the token expires while the app is open.
// Re-arms itself so delays beyond setTimeout's limit still work.
function scheduleExpiry() {
  clearTimeout(expiryTimer)
  const { expiresAt, logout } = useAuthStore.getState()
  if (expiresAt === null) return

  const remaining = expiresAt - EXPIRY_SKEW_MS - Date.now()
  if (remaining <= 0) {    
      logout()
      return
  }
  expiryTimer = setTimeout(scheduleExpiry, Math.min(remaining, MAX_TIMEOUT_MS))
  if (typeof document !== "undefined") {
    const recheck = () => {
      if (document.visibilityState === "visible") scheduleExpiry()
    }
    document.addEventListener("visibilitychange", recheck)
    window.addEventListener("focus", recheck)
  }
}