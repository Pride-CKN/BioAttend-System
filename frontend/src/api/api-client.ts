import axios, {
  type AxiosError,
  type AxiosInstance,
  type AxiosRequestConfig,
  type AxiosResponse,
} from "axios";
import { ApiError } from "./api-error";
import { useAuthStore } from "@/lib/authStore";

const API_BASE_URL = (import.meta.env.VITE_API_URL ?? "/api").replace(
  /\/$/,
  "",
);

const ROOT_URL = API_BASE_URL.replace(/\/api$/, "");
export const getCsrfCookie = () =>
  axios.get(`${ROOT_URL}/sanctum/csrf-cookie`, { withCredentials: true });

// Generic response wrapper structures based on your specification
export interface ApiSuccessResponse<T> {
  success: true;
  message: string;
  data: T;
}

export interface ApiPaginationMeta {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  from: number;
  to: number;
}

export interface ApiPaginatedResponse<T> {
  success: true;
  message: string;
  data: T[];
  meta: ApiPaginationMeta;
}

export interface ApiErrorResponse {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

export type ApiResponse<T> = ApiSuccessResponse<T> | ApiPaginatedResponse<T>;

export const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true, // send/receive cookies
  withXSRFToken: true,
  timeout: 30_000,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

// Request interceptor to attach Laravel Sanctum Bearer Token automatically
apiClient.interceptors.request.use(
  (config) => {
    const { token, isExpired, logout } = useAuthStore.getState();

    if (token && isExpired()) {
      logout();
      return Promise.reject(
        new ApiError(401, "Sesi telah berakhir. Silakan masuk kembali."),
      );
    }
    if (token) config.headers.Authorization = `Bearer ${token}`;

    if (typeof FormData !== "undefined" && config.data instanceof FormData) {
      config.headers.delete("Content-Type");
    }

    return config;
  },

  (error: unknown) => Promise.reject(error),
);

// Response interceptor to handle errors strictly without any
apiClient.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error: AxiosError<ApiErrorResponse>) => {
    if (axios.isCancel(error)) throw error
    if (!error.response) {
      const message =
        error.code === "ECONNABORTED"
          ? "Server terlalu lama merespons. Coba lagi."
          : "Tidak dapat terhubung ke server. Periksa koneksi Anda.";
      throw new ApiError(0, message);
    }

    const status = error.response.status;
    const data = error.response.data;

    // Only log out if this request actually carried a token.
    // A 401 from the login endpoint (wrong password) must not trigger it.
    const sent = error.config?.headers?.Authorization
    const current = useAuthStore.getState().token
    if (status === 401 && sent && sent === `Bearer ${current}`) {
      useAuthStore.getState().logout()
    }

    const firstFieldError =
      status === 422 && data?.errors
        ? Object.values(data.errors).flat()[0]
        : undefined;

    const message =
      firstFieldError ??
      (typeof data === "object" &&
      data !== null &&
      "message" in data &&
      typeof data.message === "string"
        ? data.message
        : error.message || `Permintaan API gagal (${status}).`);

    throw new ApiError(status, message, data);
  },
);

export type ApiRequestOptions = AxiosRequestConfig & {
  query?: Record<string, string | number | boolean | undefined>;
};

async function unwrap<T>(
  promise: Promise<AxiosResponse<ApiSuccessResponse<T>>>,
): Promise<T> {
  const response = await promise;
  return response.data.data;
}

async function unwrapPaginated<T>(
  promise: Promise<AxiosResponse<ApiPaginatedResponse<T>>>,
): Promise<ApiPaginatedResponse<T>> {
  const response = await promise;
  return response.data;
}

// Fully typed HTTP methods
export const apiGet = <T>(endpoint: string, options?: ApiRequestOptions) => {
  const { query, ...config } = options ?? {};
  return unwrap<T>(
    apiClient.get<ApiSuccessResponse<T>>(endpoint, {
      ...config,
      params: query,
    }),
  );
};

export const apiGetPaginated = <T>(
  endpoint: string,
  options?: ApiRequestOptions,
) => {
  const { query, ...config } = options ?? {};
  return unwrapPaginated<T>(
    apiClient.get<ApiPaginatedResponse<T>>(endpoint, {
      ...config,
      params: query,
    }),
  );
};

export const apiPost = <T, TBody = unknown>(
  endpoint: string,
  data?: TBody,
  options?: ApiRequestOptions,
) => {
  const { query, ...config } = options ?? {};
  return unwrap<T>(
    apiClient.post<ApiSuccessResponse<T>>(endpoint, data, {
      params: query,
      ...config,
    }),
  );
};

export const apiPut = <T, TBody = unknown>(
  endpoint: string,
  data?: TBody,
  options?: ApiRequestOptions,
) => {
  const { query, ...config } = options ?? {};
  return unwrap<T>(
    apiClient.put<ApiSuccessResponse<T>>(endpoint, data, {
      params: query,
      ...config,
    }),
  );
};

export const apiPatch = <T, TBody = unknown>(
  endpoint: string,
  data?: TBody,
  options?: ApiRequestOptions,
) => {
  const { query, ...config } = options ?? {};
  return unwrap<T>(
    apiClient.patch<ApiSuccessResponse<T>>(endpoint, data, {
      params: query,
      ...config,
    }),
  );
};

export const apiDelete = <T>(endpoint: string, options?: ApiRequestOptions) => {
  const { query, ...config } = options ?? {};
  return unwrap<T>(
    apiClient.delete<ApiSuccessResponse<T>>(endpoint, {
      params: query,
      ...config,
    }),
  );
};

export async function apiDownload(
  endpoint: string,
  options?: ApiRequestOptions,
): Promise<{ blob: Blob; filename: string }> {
  const { query, ...config } = options ?? {};
  try {
    const res = await apiClient.get<Blob>(endpoint, {
      params: query,
      responseType: "blob",
      ...config,
    });
    const disposition = String(res.headers["content-disposition"] ?? "");
    const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition);
    return {
      blob: res.data,
      filename: match ? decodeURIComponent(match[1]) : "download",
    };
  } catch (err) {
    if (err instanceof ApiError && err.details instanceof Blob) {
      try {
        const body = JSON.parse(await err.details.text()) as ApiErrorResponse;
        throw new ApiError(err.status, body.message ?? err.message, body);
      } catch (inner) {
        if (inner instanceof ApiError) throw inner;
      }
    }
    throw err;
  }
}

export function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}
