import { useState } from "react"

export type DocItem = { name: string; status: "ok" | "empty" | "new" }
const MAX_BYTES = 5 * 1024 * 1024

export function useEmployeeDocuments(isEdit: boolean) {
  const [docs, setDocs] = useState<DocItem[]>([
    { name: "KTP.pdf", status: isEdit ? "ok" : "empty" },
    { name: "Kontrak Kerja.pdf", status: isEdit ? "ok" : "empty" },
    { name: "SK-internal.pdf", status: "empty" },
  ])
  const [error, setError] = useState("")

  const add = (file?: File) => {
    if (!file) return
    if (file.size > MAX_BYTES) return setError("Ukuran dokumen maksimal 5MB")
    setError("")
    setDocs((p) => [...p, { name: file.name, status: "new" }])
  }

  return { docs, error, add }
}
