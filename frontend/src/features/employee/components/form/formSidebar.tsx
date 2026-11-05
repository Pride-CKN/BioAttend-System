import { useRef, useState, type DragEvent } from "react"
import { Button } from "@/components/ui/button"
import { useEmployeeDocuments } from "@/features/employee/hooks/useEmployeeDocuments"
import { usePhotoUpload } from "@/features/employee/hooks/usePhotoUpload"
import type { Employee } from "@/features/employee/employee.type"
import { Notice, Pill, SideCard } from "@/features/employee/components/form/formFields"

export function PhotoCard({ name }: { name: string }) {
  const { photo, error, accept } = usePhotoUpload()
  const [dragging, setDragging] = useState(false)
  const input = useRef<HTMLInputElement>(null)
  const initials = name.split(" ").filter(Boolean).map((n) => n[0]).join("").slice(0, 2).toUpperCase()

  const onDrop = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    setDragging(false)
    accept(e.dataTransfer.files[0])
  }

  return (
    <SideCard title="Foto Karyawan" hint="Maks 2MB · JPG/PNG">
      <div className="space-y-3 p-4">
        <div
          onDragOver={(e) => { e.preventDefault(); setDragging(true) }}
          onDragLeave={() => setDragging(false)}
          onDrop={onDrop}
          onClick={() => input.current?.click()}
          className={`flex h-40 cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-md border border-dashed text-xs text-muted-foreground ${dragging ? "border-red-400 bg-red-50" : "bg-slate-50"}`}
        >
          {photo ? (
            <img src={photo} alt="Foto karyawan" className="h-full w-full object-cover" />
          ) : (
            <>
              <span className="flex size-10 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-600">{initials || "👤"}</span>
              Klik atau drop foto di sini
            </>
          )}
        </div>
        {error && <p className="text-[11px] text-red-600">{error}</p>}
        <input ref={input} type="file" accept="image/jpeg,image/png" className="hidden"
          onChange={(e) => { accept(e.target.files?.[0]); e.target.value = "" }} />
        <Button type="button" variant="outline" size="sm" className="w-full" onClick={() => input.current?.click()}>⬆ Upload Foto</Button>
      </div>
    </SideCard>
  )
}

export function DocumentsCard({ isEdit }: { isEdit: boolean }) {
  const { docs, error, add } = useEmployeeDocuments(isEdit)
  const input = useRef<HTMLInputElement>(null)
  return (
    <SideCard id="sec-dokumen" title="Dokumen Karyawan" hint="PDF / JPG, maks 5MB">
      <div className="space-y-3 p-4">
        <ul className="divide-y text-xs">
          {docs.map((d, i) => (
            <li key={`${d.name}-${i}`} className="flex items-center justify-between gap-2 py-2.5">
              <span className="truncate">{d.name}</span>
              <Pill tone={d.status}>{d.status === "ok" ? "Tersimpan" : d.status === "new" ? "Baru" : "Kosong"}</Pill>
            </li>
          ))}
        </ul>
        {error && <p className="text-[11px] text-red-600">{error}</p>}
        <input ref={input} type="file" accept="application/pdf,image/jpeg" className="hidden"
          onChange={(e) => { add(e.target.files?.[0]); e.target.value = "" }} />
        <Button type="button" variant="outline" size="sm" className="w-full" onClick={() => input.current?.click()}>⬆ Upload Dokumen</Button>
      </div>
    </SideCard>
  )
}

export function ValidationCard({
  pin, email, pinOwner, placementValid,
}: { pin: string; email: string; pinOwner?: Employee; placementValid: boolean }) {
  const [verifySent, setVerifySent] = useState(false)
  return (
    <SideCard title="Validasi" hint="Wajib lolos sebelum simpan">
      <div className="space-y-2 p-4">
        {pinOwner ? (
          <Notice tone="error" title="PIN fingerprint sudah digunakan">PIN {pin} telah terdaftar pada karyawan lain ({pinOwner.name}).</Notice>
        ) : pin ? (
          <Notice tone="ok" title="PIN fingerprint tersedia">PIN {pin} belum dipakai karyawan lain.</Notice>
        ) : (
          <Notice tone="warn" title="PIN fingerprint belum diisi">Isi PIN agar karyawan dapat absen lewat fingerprint.</Notice>
        )}

        {email && (
          <Notice tone="warn" title="Email belum diverifikasi">
            {verifySent ? (
              "Email verifikasi sudah dikirim ke karyawan."
            ) : (
              <button type="button" className="underline" onClick={() => { console.log("Kirim verifikasi ke", email); setVerifySent(true) }}>
                Kirim email verifikasi ke karyawan.
              </button>
            )}
          </Notice>
        )}

        {placementValid ? (
          <Notice tone="ok" title="Data kepegawaian valid">Department, division, dan position sudah konsisten.</Notice>
        ) : (
          <Notice tone="error" title="Data kepegawaian tidak konsisten">Periksa kembali department, division, dan position.</Notice>
        )}
      </div>
    </SideCard>
  )
}
