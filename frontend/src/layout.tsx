import { Suspense } from "react"
import { AppSidebar } from "@/components/layout/app-sidebar"
import { GlobalNavbar } from "@/components/layout/global-navbar"
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar"
import { Outlet } from "react-router"

const Fallback = () => (
  <div className="flex min-h-screen flex-1 items-center justify-center text-sm text-muted-foreground">Memuat…</div>
)

export default function Layout() {
  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset className="min-w-0 bg-muted/30">
        <GlobalNavbar />
        <Suspense fallback={<Fallback />}>
          <main className="min-h-0 flex-1 overflow-y-auto p-3 md:p-4">
            <Outlet />
          </main>
        </Suspense>
      </SidebarInset>
    </SidebarProvider>
  )
}