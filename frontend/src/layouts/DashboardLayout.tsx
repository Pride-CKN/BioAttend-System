import { useState } from 'react'
import { Outlet } from 'react-router'
import Navbar from '@/features/dashboard/components/Navbar'
import Sidebar from '@/features/dashboard/components/Sidebar'

export default function DashboardLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)

  return (
    <div className="h-screen w-full bg-gray-50 flex overflow-hidden">
      <Sidebar isOpen={isSidebarOpen} setIsOpen={setIsSidebarOpen} />

      <div className="flex-1 flex flex-col h-full min-w-0 lg:ml-64 transition-all duration-300">
        <Navbar toggleSidebar={() => setIsSidebarOpen(true)} />
        <main className="flex-1 overflow-y-auto p-4 sm:p-6 md:p-8 custom-scrollbar">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
