export class ApiError extends Error {
  readonly status: number
  readonly details: unknown

  constructor(status: number, message: string, details?: unknown) {
    super(message)
    this.name = "ApiError"
    this.status = status
    this.details = details
  }

  /** Error validasi 422 per field: { email: ["..."] } */
  get fieldErrors(): Record<string, string[]> {
    const d = this.details as { errors?: Record<string, string[]> } | undefined
    return d?.errors ?? {}
  }

  /** true kalau server tidak terjangkau (status 0). */
  get isNetworkError() {
    return this.status === 0
  }
}