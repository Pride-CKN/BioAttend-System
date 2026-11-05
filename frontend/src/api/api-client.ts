import { ApiError } from "./api-error"

const API_BASE_URL = (import.meta.env.VITE_API_URL ?? "/api").replace(/\/$/, "")

type ApiRequestOptions = RequestInit & {
  query?: Record<string, string | number | boolean | undefined>
}

function getRequestUrl(endpoint: string, query?: ApiRequestOptions["query"]) {
  const url = new URL(
    `${API_BASE_URL}/${endpoint.replace(/^\//, "")}`,
    window.location.origin,
  )

  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== undefined) url.searchParams.set(key, String(value))
  })

  return url
}

async function readResponseBody(response: Response): Promise<unknown> {
  if (response.status === 204) return undefined

  const contentType = response.headers.get("content-type") ?? ""
  if (contentType.includes("application/json")) return response.json()

  return response.text()
}

// Menjalankan request API dengan header, parsing response, dan error terpusat.
export async function apiClient<T>(
  endpoint: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const { query, headers, ...requestInit } = options
  const response = await fetch(getRequestUrl(endpoint, query), {
    ...requestInit,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...headers,
    },
  })
  const body = await readResponseBody(response)

  if (!response.ok) {
    const message =
      typeof body === "object" &&
      body !== null &&
      "message" in body &&
      typeof body.message === "string"
        ? body.message
        : `Permintaan API gagal (${response.status}).`

    throw new ApiError(response.status, message, body)
  }

  return body as T
}

export const apiGet = <T>(
  endpoint: string,
  query?: ApiRequestOptions["query"],
) => apiClient<T>(endpoint, { method: "GET", query })

export const apiPost = <T>(endpoint: string, body?: unknown) =>
  apiClient<T>(endpoint, {
    method: "POST",
    body: body === undefined ? undefined : JSON.stringify(body),
  })
