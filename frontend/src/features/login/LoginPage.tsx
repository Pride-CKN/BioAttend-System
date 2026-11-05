import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Eye, EyeOff, AlertCircle } from "lucide-react"
import logoPride from "@/assets/logo-pride.png"

export default function LoginPage() {
  const [showPassword, setShowPassword] = useState(false)
  // State error diubah jadi false agar tidak muncul terus menerus
  const [error, setError] = useState(false) 
  const navigate = useNavigate()

  const handleLogin = (e: React.FormEvent) => {
    e.preventDefault()
    
    // Simulasi pengecekan login
    // (Nanti ganti ini dengan fetch API Laravel)
    const form = e.target as HTMLFormElement;
    const email = (form.elements[0] as HTMLInputElement).value;
    const pass = (form.elements[1] as HTMLInputElement).value;

    if (email === "stf.admin@pridechicken.co.id" && pass === "password123") {
      setError(false)
      navigate('/dashboard')
    } else {
      setError(true)
    }
  }

  return (
    <div className="w-full min-h-screen flex flex-col md:flex-row bg-white m-0 p-0 overflow-hidden">
      
      {/* PANEL KIRI: Branding (Merah Kustom) */}
      <div className="hidden md:flex md:w-1/2 bg-[#EA232A] relative flex-col justify-center p-8 lg:p-16 xl:p-24 text-white">
        <div className="absolute top-[-10%] left-[-10%] w-[400px] h-[400px] bg-white/10 rounded-full blur-3xl"></div>
        <div className="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-black/10 rounded-full blur-3xl"></div>
        
        <div className="relative z-10 max-w-lg">
          <div className="flex items-center gap-4 mb-8">
            <div className="bg-white p-2 rounded-xl w-20 h-20 flex items-center justify-center shadow-lg">
              <img src={logoPride} alt="Pride Chicken" className="w-full h-full object-contain" />
            </div>
          </div>
          
          <h1 className="text-3xl lg:text-4xl xl:text-5xl font-bold leading-tight mb-6">
            Pride Attendance <br/>& HR Management System
          </h1>
          
          <p className="text-white/80 text-base lg:text-lg mb-8">
            Sistem absensi otomatis berbasis fingerprint dengan Payroll Deduction Calculation, integrasi Google Workspace, dan AI HR Attendance Agent khusus untuk HRD.
          </p>

          <ul className="space-y-4 text-white/90 text-sm lg:text-base">
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Absensi real-time dari mesin ZKTeco</li>
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Perhitungan potongan payroll otomatis</li>
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> AI insight untuk keputusan HR</li>
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Integrasi Google Workspace</li>
          </ul>
        </div>
      </div>

      {/* PANEL KANAN: Form Login */}
      <div className="flex-1 flex flex-col justify-center items-center p-6 md:p-12 bg-white">
        <div className="w-full max-w-md space-y-6">
          
          <div className="text-center">
            <h2 className="text-2xl font-bold text-[#080808]">Masuk ke Akun Anda</h2>
            <p className="text-sm text-[#616161] mt-2">Gunakan username HRD yang diberikan administrator</p>
          </div>

          {/* Alert Error Hanya Muncul Jika Login Gagal */}
          {error && (
            <Alert variant="destructive" className="bg-[#EA232A]/10 text-[#EA232A] border-[#EA232A]/20">
              <AlertCircle className="h-4 w-4" />
              <AlertTitle>Username atau password salah</AlertTitle>
              <AlertDescription>Sisa percobaan: 4 kali. Percobaan login dibatasi.</AlertDescription>
            </Alert>
          )}

          <form className="space-y-5" onSubmit={handleLogin}>
            <div className="space-y-2 text-left">
              <label className="text-sm font-medium text-[#080808]">Username / Email</label>
              <Input 
                type="email" 
                placeholder="stf.admin@pridechicken.co.id" 
                defaultValue="stf.admin@pridechicken.co.id" 
                className="h-11 border-[#616161]/30 focus-visible:ring-[#EA232A]" 
                required
              />
            </div>

            <div className="space-y-2 text-left">
              <label className="text-sm font-medium text-[#080808]">Password</label>
              <div className="relative">
                <Input 
                  type={showPassword ? "text" : "password"} 
                  placeholder="••••••••" 
                  defaultValue="password123" 
                  className="h-11 pr-10 border-[#616161]/30 focus-visible:ring-[#EA232A]"
                  required
                />
                <button 
                  type="button" 
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-[#616161] hover:text-[#080808]"
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-2">
                <Checkbox id="remember" className="data-[state=checked]:bg-[#EA232A] data-[state=checked]:border-[#EA232A]" />
                <label htmlFor="remember" className="text-sm text-[#616161] font-medium leading-none">
                  Ingat saya
                </label>
              </div>
              <a href="#" className="text-sm text-[#EA232A] font-semibold hover:underline">
                Lupa password?
              </a>
            </div>

            <Button type="submit" className="w-full h-11 bg-[#EA232A] hover:bg-[#852622] text-white text-base">
              Login
            </Button>
          </form>

          <div className="relative">
            <div className="absolute inset-0 flex items-center">
              <span className="w-full border-t border-[#616161]/20" />
            </div>
            <div className="relative flex justify-center text-xs uppercase">
              <span className="bg-white px-2 text-[#616161]">Atau</span>
            </div>
          </div>

          <Button variant="outline" className="w-full h-11 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10" type="button">
            Login dengan Google Workspace SSO
          </Button>

          <div className="text-center text-xs text-[#616161] space-y-2 pt-4">
            <p></p>
            <p>© 2026 PT Pride Chicken • <a href="#" className="underline hover:text-[#EA232A]">Kebijakan Privasi</a> • <a href="#" className="underline hover:text-[#EA232A]">Hubungi HRD</a></p>
          </div>

        </div>
      </div>
    </div>
  )
}