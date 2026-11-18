import { apiDelete, apiGet, apiPost, apiPut } from "./api-client";
import { apiEndpoints } from "./endpoints";

export type MasterResource =
  | "offices"
  | "divisions"
  | "units"
  | "positions"
  | "shifts"
  | "holidays"
  | "leave-types"
  | "devices";

type Query = Record<string, string | number | boolean | undefined>;

/** GET /master/{resource}?all=true  -> semua data tanpa pagination (sesuai dokumen §9.1) */
export const masterList = <T>(resource: MasterResource, query: Query = {}) =>
  apiGet<T[]>(apiEndpoints.master(resource), {
    query: { all: true, ...query },
  });

export const masterGet = <T>(resource: MasterResource, id: string | number) =>
  apiGet<T>(apiEndpoints.masterItem(resource, id));

export const masterCreate = <T>(
  resource: MasterResource,
  body: Record<string, unknown>,
) => apiPost<T>(apiEndpoints.master(resource), body);

export const masterUpdate = <T>(
  resource: MasterResource,
  id: string | number,
  body: Record<string, unknown>,
) => apiPut<T>(apiEndpoints.masterItem(resource, id), body);

/** DELETE di master = nonaktifkan (is_active=false), bukan hapus permanen. */
export const masterRemove = (resource: MasterResource, id: string | number) =>
  apiDelete<unknown>(apiEndpoints.masterItem(resource, id));

/**
 * Aktif/nonaktifkan.
 * - nonaktif -> DELETE
 * - aktif    -> GET data lama lalu PUT penuh dengan is_active=true (DELETE tidak bisa mengaktifkan lagi)
 * Hasil akhir selalu diambil ulang dari server.
 */
export async function masterSetActive<T extends { is_active: boolean }>(
  resource: MasterResource,
  id: string | number,
  active: boolean,
  toBody: (current: T) => Record<string, unknown>,
): Promise<T> {
  if (active) {
    const current = await masterGet<T>(resource, id);
    await masterUpdate(resource, id, { ...toBody(current), is_active: true });
  } else {
    await masterRemove(resource, id);
  }
  return masterGet<T>(resource, id);
}
