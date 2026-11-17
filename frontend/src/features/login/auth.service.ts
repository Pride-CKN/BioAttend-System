import { apiDelete, apiGet, apiPost } from "@/api/api-client"
import { apiEndpoints } from "@/api/endpoints"
import { useAuthStore, type AuthUser } from "@/lib/authStore"

interface AuthTokenResponse {
  token: string
  expires_in: number | null // seconds
  user: AuthUser | null
}

export interface LoginPayload {
  email: string
  password: string
}

export interface ActivateAccountPayload {
  email: string
  token: string
  password: string
  password_confirmation: string
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export interface ForgotPasswordPayload {
  email: string
}

export interface Session {
  id: string // "tok_{id}" | "sess_{id}", sent as-is
  type: string
  name: string
  ip_address: string | null
  last_used_at: string | null
  created_at: string
  is_current: boolean
}

export async function login(credentials: LoginPayload) {
  const res = await apiPost<AuthTokenResponse>(apiEndpoints.authLogin, credentials)
  useAuthStore.getState().login(res.token, res.expires_in, res.user)
  return res
}

export async function activateAccount(payload: ActivateAccountPayload) {
  const res = await apiPost<Partial<AuthTokenResponse>>(apiEndpoints.authActivate, payload)
  // If backend auto-logs in on activation and returns a token, store it
  if (res?.token) {
    useAuthStore.getState().login(res.token, res.expires_in ?? null, res.user ?? null)
  }
  return res
}

export async function changePassword(payload: ChangePasswordPayload) {
  const res = await apiPost<{ message?: string; user?: AuthUser }>(
    apiEndpoints.authChangePassword,
    payload
  )

  // Clear must_change_password flag in local auth store if user is loaded
  const store = useAuthStore.getState()
  if (store.user) {
    const updatedUser: AuthUser = res?.user ?? {
      ...store.user,
      must_change_password: false,
    }
    store.login(store.token!, store.expiresAt ?? null, updatedUser)
  }

  return res
}

export const forgotPassword = (payload: ForgotPasswordPayload) =>
  apiPost<{ message?: string }>(apiEndpoints.authForgotPassword, payload)

export const fetchProfile = () =>
  apiGet<AuthUser>(apiEndpoints.authProfile)

export async function logout() {
  try {
    await apiPost<unknown>(apiEndpoints.authLogout, {})
  } finally {
    useAuthStore.getState().logout()
  }
}

export async function logoutAll() {
  try {
    await apiPost<unknown>(apiEndpoints.authLogoutAll, {})
  } finally {
    useAuthStore.getState().logout()
  }
}

export const fetchSessions = () =>
  apiGet<Session[]>(apiEndpoints.authSessions)

export async function revokeSession(session: Session) {
  await apiDelete<unknown>(apiEndpoints.authSession(session.id))
  if (session.is_current) useAuthStore.getState().logout()
}