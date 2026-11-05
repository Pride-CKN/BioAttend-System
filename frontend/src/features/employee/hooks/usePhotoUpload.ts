import { useEffect, useRef, useState } from "react"

const MAX_BYTES = 2 * 1024 * 1024

export function usePhotoUpload() {
  const [photo, setPhoto] = useState<string | null>(null)
  const [error, setError] = useState("")
  const current = useRef<string | null>(null)

  useEffect(() => () => {
    if (current.current) URL.revokeObjectURL(current.current)
  }, [])

  const accept = (file?: File) => {
    if (!file) return
    if (!["image/jpeg", "image/png"].includes(file.type)) return setError("Format harus JPG atau PNG")
    if (file.size > MAX_BYTES) return setError("Ukuran foto maksimal 2MB")
    setError("")
    if (current.current) URL.revokeObjectURL(current.current)
    current.current = URL.createObjectURL(file)
    setPhoto(current.current)
  }

  return { photo, error, accept }
}
