import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Eye, EyeOff, AlertCircle, CheckCircle2 } from "lucide-react"
import logoPride from "@/assets/logo-pride.png"
import { activateAccount } from "@/features/login/auth.service"
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'

export default function ActivateAccount() {
  const [searchParams] = useSearchParams()
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const navigate = useNavigate()

  const defaultEmail = searchParams.get("email") ?? ""
  const defaultToken = searchParams.get("token") ?? ""

  const handleActivate = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setError(null)
    setIsSubmitting(true)

    const formData = new FormData(e.currentTarget)
    const email = String(formData.get("email") ?? "")
    const token = String(formData.get("token") ?? "")
    const password = String(formData.get("password") ?? "")
    const password_confirmation = String(formData.get("password_confirmation") ?? "")

    if (password !== password_confirmation) {
      setError("Konfirmasi password tidak cocok dengan password baru.")
      setIsSubmitting(false)
      return
    }

    try {
      await activateAccount({
        email,
        token,
        password,
        password_confirmation,
      })
      setSuccess(true)
      setTimeout(() => navigate('/login', { replace: true }), 2000)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Gagal mengaktivasi akun. Periksa kembali token Anda.")
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="w-full min-h-screen flex flex-col md:flex-row bg-white m-0 p-0 overflow-hidden">
      
      {/* PANEL KIRI: Branding (Merah Kustom) */}
      <div className="hidden md:flex md:w-1/2 bg-[#EA232A] relative flex-col justify-center p-8 lg:p-16 xl:p-24 text-white">
        <div className="absolute top-[-10%] left-[-10%] w-100 h-100 bg-white/10 rounded-full blur-3xl"></div>
        <div className="absolute bottom-[-10%] right-[-10%] w-125 h-125 bg-black/10 rounded-full blur-3xl"></div>
        
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
            Aktivasi akun HRD Anda untuk mulai mengelola absensi karyawan, potongan payroll otomatis, dan analitik SDM terintegrasi.
          </p>

          <ul className="space-y-4 text-white/90 text-sm lg:text-base">
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Verifikasi token keamanan satu kali pakai</li>
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Enkripsi kredensial standar perusahaan</li>
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Akses penuh ke dashboard HR & Payroll</li>
          </ul>
        </div>
      </div>

      {/* PANEL KANAN: Form Aktivasi */}
      <div className="flex-1 flex flex-col justify-center items-center p-6 md:p-12 bg-white">
        <div className="w-full max-w-md space-y-6">
          
          <div className="text-center">
            <h2 className="text-2xl font-bold text-[#080808]">Aktivasi Akun HRD</h2>
            <p className="text-sm text-[#616161] mt-2">Masukkan token aktivasi dan buat password baru Anda</p>
          </div>

          {error && (
            <Alert variant="destructive" className="bg-[#EA232A]/10 text-[#EA232A] border-[#EA232A]/20">
              <AlertCircle className="h-4 w-4" />
              <AlertTitle>Aktivasi gagal</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          {success && (
            <Alert className="bg-emerald-50 text-emerald-700 border-emerald-200">
              <CheckCircle2 className="h-4 w-4 text-emerald-600" />
              <AlertTitle>Akun berhasil diaktivasi</AlertTitle>
              <AlertDescription>Mengalihkan ke halaman login...</AlertDescription>
            </Alert>
          )}

          <form className="space-y-4" onSubmit={handleActivate}>
            <div className="space-y-2 text-left">
              <label className="text-sm font-medium text-[#080808]">Email</label>
              <Input 
                type="email" 
                name="email"
                defaultValue={defaultEmail}
                placeholder="nama@perusahaan.co.id" 
                className="h-11 border-[#616161]/30 focus-visible:ring-[#EA232A]" 
                required
              />
            </div>

            <div className="space-y-2 text-left">
              <label className="text-sm font-medium text-[#080808]">Token Aktivasi</label>
              <Input 
                type="text" 
                name="token"
                defaultValue={defaultToken}
                placeholder="Masukkan kode token aktivasi" 
                className="h-11 border-[#616161]/30 focus-visible:ring-[#EA232A]" 
                required
              />
            </div>

            <div className="space-y-2 text-left">
              <label className="text-sm font-medium text-[#080808]">Password Baru</label>
              <div className="relative">
                <Input 
                  type={showPassword ? "text" : "password"} 
                  name="password"
                  placeholder="••••••••" 
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

            <div className="space-y-2 text-left">
              <label className="text-sm font-medium text-[#080808]">Konfirmasi Password Baru</label>
              <div className="relative">
                <Input 
                  type={showConfirmPassword ? "text" : "password"} 
                  name="password_confirmation"
                  placeholder="••••••••" 
                  className="h-11 pr-10 border-[#616161]/30 focus-visible:ring-[#EA232A]"
                  required
                />
                <button 
                  type="button" 
                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-[#616161] hover:text-[#080808]"
                >
                  {showConfirmPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <Button type="submit" disabled={isSubmitting || success} className="w-full h-11 bg-[#EA232A] hover:bg-[#852622] text-white text-base mt-2">
              {isSubmitting ? "Mengaktivasi..." : "Aktivasi Akun"}
            </Button>
          </form>

          <div className="text-center text-sm">
            <span className="text-[#616161]">Sudah memiliki akun aktif? </span>
            <a href="/login" className="text-[#EA232A] font-semibold hover:underline">
              Kembali ke Login
            </a>
          </div>

          <div className="text-center text-xs text-[#616161] space-y-2 pt-2">
            <p>🔒 Terenkripsi AES-256 • HTTPS/TLS 1.3 • Aktivitas tercatat Audit Log</p>
            <p>© 2026 PT Pride Chicken • <a href="#" className="underline hover:text-[#EA232A]">Kebijakan Privasi</a> • <a href="#" className="underline hover:text-[#EA232A]">Hubungi HRD</a></p>
          </div>

        </div>
      </div>
    </div>
  )
}