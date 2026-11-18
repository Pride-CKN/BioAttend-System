/* eslint-disable react-refresh/only-export-components */
import type { ReactNode } from "react"
import { AlertCircle, CheckCircle2, Info, AlertTriangle } from "lucide-react"

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"

import {
  Field as UIField,
  FieldContent,
  FieldDescription,
  FieldError,
  FieldLabel,
} from "@/components/ui/field"

import { Input } from "@/components/ui/input"

import {
  Select as UISelect,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

import { Switch as UISwitch } from "@/components/ui/switch"
import { Badge } from "@/components/ui/badge"
import {
  Alert,
  AlertDescription,
  AlertTitle,
} from "@/components/ui/alert"

const baseInput =
  "h-9 w-full rounded-md border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-muted-foreground"
export const inputCls = (err?: string) => `${baseInput} ${err ? "border-red-400" : "border-input"}`

export function FormInput({
  error,
  className,
  ...props
}: React.ComponentProps<typeof Input> & { error?: string }) {
  return (
    <Input
      {...props}
      aria-invalid={!!error}
      className={className}
    />
  )
}

export function Section({
  no,
  title,
  id,
  children,
}: {
  no: number
  title: string
  id: string
  children: ReactNode
}) {
  return (
    <Card id={id} className="scroll-mt-4 overflow-hidden shadow-none">
      <CardHeader className="flex flex-row items-center gap-2 border-b bg-muted/50 px-4 py-2.5">
        <Badge className="flex size-5 items-center justify-center rounded-full bg-red-700 p-0 text-[11px] text-white hover:bg-red-700">
          {no}
        </Badge>

        <CardTitle className="text-sm font-bold">
          {title}
        </CardTitle>
      </CardHeader>

      <CardContent className="grid gap-x-4 gap-y-3 p-4 @xl:grid-cols-2">
        {children}
      </CardContent>
    </Card>
  )
}

export function FormField({
  label,
  required,
  hint,
  error,
  full,
  children,
}: {
  label: string
  required?: boolean
  hint?: string
  error?: string
  full?: boolean
  children: ReactNode
}) {
  return (
    <UIField
      data-invalid={!!error}
      className={full ? "@xl:col-span-2" : ""}
    >
      <FieldLabel>
        {label}
        {required && (
          <span className="text-red-600">*</span>
        )}
      </FieldLabel>

      <FieldContent>
        {children}

        {error ? (
          <FieldError>{error}</FieldError>
        ) : (
          hint && (
            <FieldDescription>{hint}</FieldDescription>
          )
        )}
      </FieldContent>
    </UIField>
  )
}

export function FormSelect({
  value,
  options = [],
  onChange,
  error,
}: {
  value: string
  options?: string[]
  onChange: (v: string) => void
  error?: string
}) {
  return (
    <UISelect
      value={value}
      onValueChange={onChange}
    >
      <SelectTrigger
        aria-invalid={!!error}
        className="w-full"
      >
        <SelectValue placeholder="Pilih opsi" />
      </SelectTrigger>

      <SelectContent>
        {options.map((option) => (
          <SelectItem key={option} value={option}>
            {option}
          </SelectItem>
        ))}
      </SelectContent>
    </UISelect>
  )
}


export function FormSwitch({
  checked,
  onChange,
  label,
}: {
  checked: boolean
  onChange: (v: boolean) => void
  label: string
}) {
  return (
    <div className="flex items-center gap-2">
      <UISwitch
        checked={checked}
        onCheckedChange={onChange}
        aria-label={label}
      />

      <span className="text-xs">{label}</span>
    </div>
  )
}

export function Pill({
  tone,
  children,
}: {
  tone: "ok" | "empty" | "new"
  children: ReactNode
}) {
  const variants = {
    ok: "border-emerald-200 bg-emerald-50 text-emerald-700",
    empty: "border-slate-200 bg-slate-100 text-slate-600",
    new: "border-blue-200 bg-blue-50 text-blue-700",
  }

  return (
    <Badge
      variant="outline"
      className={`gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium ${variants[tone]}`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {children}
    </Badge>
  )
}


export function Notice({
  tone,
  title,
  children,
}: {
  tone: "error" | "warn" | "ok"
  title: string
  children: ReactNode
}) {
  const config = {
    error: {
      icon: AlertCircle,
      className: "border-red-200 bg-red-50 text-red-800",
    },
    warn: {
      icon: AlertTriangle,
      className: "border-amber-200 bg-amber-50 text-amber-800",
    },
    ok: {
      icon: CheckCircle2,
      className: "border-emerald-200 bg-emerald-50 text-emerald-800",
    },
  }

  const { icon: Icon, className } = config[tone]

  return (
    <Alert className={className}>
      <Icon />
      <AlertTitle>{title}</AlertTitle>
      <AlertDescription>{children}</AlertDescription>
    </Alert>
  )
}


export function SideCard({
  title,
  hint,
  id,
  children,
}: {
  title: string
  hint: string
  id?: string
  children: ReactNode
}) {
  return (
    <Card
      id={id}
      className="scroll-mt-4 overflow-hidden shadow-none"
    >
      <CardHeader className="flex flex-row items-baseline gap-2 border-b px-4 py-2.5">
        <CardTitle className="text-sm font-bold">
          {title}
        </CardTitle>

        <span className="text-[11px] text-muted-foreground">
          {hint}
        </span>
      </CardHeader>

      <CardContent className="p-4">
        {children}
      </CardContent>
    </Card>
  )
}
