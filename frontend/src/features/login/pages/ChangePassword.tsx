import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Eye, EyeOff, AlertCircle, CheckCircle2, ArrowLeft } from "lucide-react"
import logoPride from "@/assets/logo-pride.png"
import { changePassword } from "@/features/login/auth.service"
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'

export default function ChangePasswordPage() {
  const [showCurrentPassword, setShowCurrentPassword] = useState(false)
  const [showNewPassword, setShowNewPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const navigate = useNavigate()

  const handleChangePassword = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setError(null)
    setIsSubmitting(true)

    const formData = new FormData(e.currentTarget)
    const current_password = String(formData.get("current_password") ?? "")
    const password = String(formData.get("password") ?? "")
    const password_confirmation = String(formData.get("password_confirmation") ?? "")

    if (password !== password_confirmation) {
      setError("Konfirmasi password tidak cocok dengan password baru.")
      setIsSubmitting(false)
      return
    }

    if (current_password === password) {
      setError("Password baru tidak boleh sama dengan password saat ini.")
      setIsSubmitting(false)
      return
    }

    try {
      await changePassword({
        current_password,
        password,
        password_confirmation,
      })
      setSuccess(true)
      setTimeout(() => navigate('/dashboard', { replace: true }), 1500)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : "Gagal mengubah password. Silakan coba lagi.")
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
            Keamanan Akun <br/>& Perlindungan Data
          </h1>
          
          <p className="text-white/80 text-base lg:text-lg mb-8">
            Perbarui password Anda secara berkala untuk menjaga keamanan data karyawan, laporan absensi, dan perhitungan payroll perusahaan.
          </p>

          <ul className="space-y-4 text-white/90 text-sm lg:text-base">
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Gunakan minimal 8 karakter kombinasi</li>
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Hindari penggunaan ulang password lama</li>
            <li className="flex items-center gap-3"><div className="w-2 h-2 bg-white rounded-full"></div> Perubahan tercatat otomatis di Audit Log</li>
          </ul>
        </div>
      </div>

      {/* PANEL KANAN: Form Ubah Password */}
      <div className="flex-1 flex flex-col justify-center items-center p-6 md:p-12 bg-white">
        <div className="w-full max-w-md space-y-6">
          
          <div className="text-center">
            <h2 className="text-2xl font-bold text-[#080808]">Ubah Password</h2>
            <p className="text-sm text-[#616161] mt-2">Masukkan password saat ini dan tentukan password baru Anda</p>
          </div>

          {error && (
            <Alert variant="destructive" className="bg-[#EA232A]/10 text-[#EA232A] border-[#EA232A]/20">
              <AlertCircle className="h-4 w-4" />
              <AlertTitle>Gagal mengubah password</AlertTitle>
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}

          {success && (
            <Alert className="bg-emerald-50 text-emerald-700 border-emerald-200">
              <CheckCircle2 className="h-4 w-4 text-emerald-600" />
              <AlertTitle>Password berhasil diperbarui</AlertTitle>
              <AlertDescription>Mengalihkan kembali ke dashboard...</AlertDescription>
            </Alert>
          )}

          <form className="space-y-5" onSubmit={handleChangePassword}>
            <div className="space-y-2 text-left">
              <label className="text-sm font-medium text-[#080808]">Password Saat Ini</label>
              <div className="relative">
                <Input 
                  type={showCurrentPassword ? "text" : "password"} 
                  name="current_password"
                  placeholder="••••••••" 
                  className="h-11 pr-10 border-[#616161]/30 focus-visible:ring-[#EA232A]"
                  required
                />
                <button 
                  type="button" 
                  onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-[#616161] hover:text-[#080808]"
                >
                  {showCurrentPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <div className="space-y-2 text-left">
              <label className="text-sm font-medium text-[#080808]">Password Baru</label>
              <div className="relative">
                <Input 
                  type={showNewPassword ? "text" : "password"} 
                  name="password"
                  placeholder="••••••••" 
                  className="h-11 pr-10 border-[#616161]/30 focus-visible:ring-[#EA232A]"
                  required
                />
                <button 
                  type="button" 
                  onClick={() => setShowNewPassword(!showNewPassword)}
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-[#616161] hover:text-[#080808]"
                >
                  {showNewPassword ? <EyeOff size={18} /> : <Eye size={18} />}
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

            <Button type="submit" disabled={isSubmitting || success} className="w-full h-11 bg-[#EA232A] hover:bg-[#852622] text-white text-base">
              {isSubmitting ? "Menyimpan..." : "Simpan Password Baru"}
            </Button>
          </form>

          <Button 
            variant="outline" 
            type="button"
            onClick={() => navigate(-1)}
            className="w-full h-11 border-[#616161]/30 text-[#080808] hover:bg-[#616161]/10 flex items-center justify-center gap-2"
          >
            <ArrowLeft size={16} />
            Kembali
          </Button>

          <div className="text-center text-xs text-[#616161] space-y-2 pt-2">
            <p>🔒 Terenkripsi AES-256 • HTTPS/TLS 1.3 • Aktivitas tercatat Audit Log</p>
            <p>© 2026 PT Pride Chicken • <a href="#" className="underline hover:text-[#EA232A]">Kebijakan Privasi</a> • <a href="#" className="underline hover:text-[#EA232A]">Hubungi HRD</a></p>
          </div>

        </div>
      </div>
    </div>
  )
}