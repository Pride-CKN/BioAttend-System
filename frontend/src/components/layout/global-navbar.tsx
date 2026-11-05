import { useState } from "react"
import { LogOut, Bell, RefreshCw, Search, Settings, User } from "lucide-react"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Separator } from "@/components/ui/separator"
import { SidebarTrigger } from "@/components/ui/sidebar"

// Menampilkan pencarian global, notifikasi, refresh, dan profil pengguna.
export function GlobalNavbar() {
  const [isRefreshing, setIsRefreshing] = useState(false)

  function refreshPage() {
    setIsRefreshing(true)
    window.setTimeout(() => setIsRefreshing(false), 600)
  }

  return (
    <header className="flex min-h-14 items-center gap-3 border-b border-slate-200 bg-white px-3 text-[#080808] md:px-5">
      <SidebarTrigger className="shrink-0 text-[#080808] hover:bg-slate-100 hover:text-[#852622]" />

      <div className="relative min-w-0 flex-1 md:max-w-md">
        <Search
          className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
        />
        <Input
          type="search"
          placeholder="Cari karyawan, menu, dokumen..."
          className="h-9 rounded-lg border-slate-200 bg-slate-50 pl-9 text-[#080808] placeholder:text-slate-400 focus-visible:ring-[#852622]"
          aria-label="Cari karyawan, menu, atau dokumen"
        />
      </div>

      <div className="ml-auto flex items-center gap-1">
        <Button
          variant="ghost"
          size="icon"
          className="rounded-full text-slate-600 hover:bg-slate-100 hover:text-[#852622]"
          onClick={refreshPage}
          aria-label="Refresh halaman"
        >
          <RefreshCw
            className={isRefreshing ? "animate-spin" : ""}
          />
        </Button>
        <Button
          variant="ghost"
          size="icon"
          className="relative rounded-full text-slate-600 hover:bg-slate-100 hover:text-[#852622]"
          aria-label="Notifikasi"
        >
          <Bell />
          <span className="absolute right-1.5 top-1.5 size-1.5 rounded-full bg-destructive" />
        </Button>

        <Separator orientation="vertical" className="mx-2 h-7 bg-slate-200" />

        <DropdownMenu>
          <DropdownMenuTrigger
            render={
              <Button variant="ghost" className="h-10 gap-2 rounded-xl px-2" />
            }
          >
            <Avatar size="sm">
              <AvatarFallback>RP</AvatarFallback>
            </Avatar>
            <span className="hidden text-left leading-tight text-[#080808] sm:grid">
              <span className="text-sm font-medium">Rio Pratama</span>
              <span className="text-xs text-slate-500">HR Manager</span>
            </span>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-52">
            <DropdownMenuItem>
              <User />
              Profil saya
            </DropdownMenuItem>
            <DropdownMenuItem>
              <Settings />
              Pengaturan
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive">
              <LogOut />
              Keluar
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  )
}
