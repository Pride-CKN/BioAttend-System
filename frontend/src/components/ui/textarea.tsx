import * as React from "react"
import { cn } from "cn"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "flex min-h-24 w-full resize-y rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-[#080808] outline-none placeholder:text-slate-400 focus-visible:border-[#852622] focus-visible:ring-3 focus-visible:ring-[#852622]/20 disabled:cursor-not-allowed disabled:opacity-50",
        className,
      )}
      {...props}
    />
  )
}

export { Textarea }
