import {
  Alert,
  AlertDescription,
  AlertTitle,
} from "@/components/ui/alert"
import { AlertCircle, CheckCircle2 } from "lucide-react"

type ValidationAlertProps = {
  errors?: string[]
  success?: string
}

export function ValidationAlert({
  errors = [],
  success,
}: ValidationAlertProps) {
  if (errors.length === 0 && !success) return null

  if (errors.length > 0) {
    return (
      <Alert variant="destructive">
        <AlertCircle />
        <AlertTitle>Validasi Gagal</AlertTitle>
        <AlertDescription>
          <ul className="list-disc space-y-1 pl-4">
            {errors.map((error, index) => (
              <li key={index}>{error}</li>
            ))}
          </ul>
        </AlertDescription>
      </Alert>
    )
  }

  return (
    <Alert>
      <CheckCircle2 />
      <AlertTitle>Berhasil</AlertTitle>
      <AlertDescription>
        {success}
      </AlertDescription>
    </Alert>
  )
}
