import { apiGetPaginated } from "./api-client"

/** Ambil semua halaman (maks `maxPages`) dari endpoint paginasi Laravel. */
export async function fetchAllPages<T>(
  endpoint: string,
  query: Record<string, string | number | boolean | undefined> = {},
  { perPage = 200, maxPages = 10 } = {},
): Promise<T[]> {
  const items: T[] = []
  let page = 1
  let lastPage = 1
  do {
    const res = await apiGetPaginated<T>(endpoint, { query: { ...query, page, per_page: perPage } })
    items.push(...res.data)
    lastPage = res.meta?.last_page ?? 1
    page++
  } while (page <= lastPage && page <= maxPages)
  return items
}