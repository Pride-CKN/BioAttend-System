import { Bell, Search, ChevronDown, Menu } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"

interface NavbarProps {
  toggleSidebar: () => void;
}

export default function Navbar({ toggleSidebar }: NavbarProps) {
  return (
    <header className="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 md:px-8 sticky top-0 z-50">
      
      <div className="flex items-center gap-2 md:gap-4 flex-1">
        <Button variant="ghost" size="icon" className="lg:hidden text-gray-700 -ml-2" onClick={toggleSidebar}>
          <Menu size={24} />
        </Button>

        <div className="relative w-full max-w-sm hidden md:block">
          <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-500" />
          <Input
            type="search"
            placeholder="Cari karyawan, ID, atau departemen..."
            className="pl-9 bg-gray-50 border-gray-200 focus-visible:ring-[#EA232A]"
          />
        </div>
        
        <Button variant="ghost" size="icon" className="md:hidden text-gray-600">
          <Search size={20} />
        </Button>
      </div>

      <div className="flex items-center gap-2 md:gap-4">
        <Button variant="ghost" size="icon" className="text-gray-500 relative hover:bg-gray-100">
          <Bell size={20} />
          <span className="absolute top-2 right-2.5 w-2 h-2 bg-[#EA232A] rounded-full border-2 border-white"></span>
        </Button>

        <div className="flex items-center gap-2 md:gap-3 border-l border-gray-200 pl-2 md:pl-4">
          <div className="text-right hidden md:block">
            <p className="text-sm font-semibold text-gray-800">Staf Admin</p>
            <p className="text-xs text-gray-500">HRD Department</p>
          </div>
          
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" className="relative h-10 w-auto rounded-full px-1 md:px-2 gap-1 hover:bg-gray-100 focus-visible:ring-0">
                <Avatar className="h-8 w-8 md:h-9 md:w-9 border border-gray-200">
                  <AvatarFallback className="bg-[#EA232A] text-white font-medium text-xs md:text-sm">SA</AvatarFallback>
                </Avatar>
                <ChevronDown size={14} className="text-gray-500 hidden md:block" />
              </Button>
            </DropdownMenuTrigger>
            
            <DropdownMenuContent className="w-56 bg-white shadow-lg border border-gray-200 z-[100] mt-2" align="end" forceMount>
              <DropdownMenuLabel className="font-normal">
                <div className="flex flex-col space-y-1">
                  <p className="text-sm font-medium leading-none text-gray-800">Staf Admin</p>
                  <p className="text-xs leading-none text-gray-500">stf.admin@pridechicken.co.id</p>
                </div>
              </DropdownMenuLabel>
              <DropdownMenuSeparator className="bg-gray-100" />
              <DropdownMenuItem className="cursor-pointer text-gray-700 focus:bg-gray-100 focus:text-gray-900">Profil Saya</DropdownMenuItem>
              <DropdownMenuItem className="cursor-pointer text-gray-700 focus:bg-gray-100 focus:text-gray-900">Pengaturan Akun</DropdownMenuItem>
              <DropdownMenuSeparator className="bg-gray-100" />
              <DropdownMenuItem className="text-[#EA232A] font-medium cursor-pointer focus:bg-[#EA232A]/10 focus:text-[#EA232A]" onClick={() => window.location.href = '/'}>
                Logout
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  )
}