import type { AttendanceCorrection } from "@/features/attendance-correction/types"

const headers = [
  "ID",
  "Karyawan",
  "Tanggal",
  "Perubahan",
  "Nilai Lama",
  "Nilai Baru",
  "Alasan",
  "Status",
]

// Mengubah data koreksi menjadi baris yang dipakai semua format export.
function getRows(corrections: AttendanceCorrection[]) {
  return corrections.map((item) => [
    item.id,
    item.employee,
    item.date,
    item.requestedType,
    item.originalValue,
    item.correctedValue,
    item.reason,
    item.status,
  ])
}

// Mengunduh konten hasil export sebagai file dari browser.
function download(content: string, filename: string, type: string) {
  const url = URL.createObjectURL(new Blob([content], { type }))
  const link = document.createElement("a")
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

export function exportCorrectionsCsv(corrections: AttendanceCorrection[]) {
  const csv = [headers, ...getRows(corrections)]
    .map((row) =>
      row.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(","),
    )
    .join("\n")

  download(
    `\ufeff${csv}`,
    "attendance-corrections.csv",
    "text/csv;charset=utf-8",
  )
}

export function exportCorrectionsExcel(corrections: AttendanceCorrection[]) {
  const rows = getRows(corrections)
  const table = [headers, ...rows]
    .map(
      (row) =>
        `<tr>${row.map((value) => `<td>${escapeHtml(String(value))}</td>`).join("")}</tr>`,
    )
    .join("")
  const workbook = `<html><head><meta charset="utf-8"></head><body><table>${table}</table></body></html>`

  download(
    `\ufeff${workbook}`,
    "attendance-corrections.xls",
    "application/vnd.ms-excel",
  )
}

// Membuka dialog print browser agar pengguna dapat menyimpan sebagai PDF.
export function printCorrectionsPdf(corrections: AttendanceCorrection[]) {
  const rows = getRows(corrections)
    .map(
      (row) =>
        `<tr>${row.map((value) => `<td>${escapeHtml(String(value))}</td>`).join("")}</tr>`,
    )
    .join("")
  const printWindow = window.open("", "_blank", "noopener,noreferrer")

  if (!printWindow) {
    throw new Error("Jendela print diblokir browser.")
  }

  printWindow.document.write(`
    <!doctype html>
    <html>
      <head>
        <title>Attendance Corrections</title>
        <style>
          body { font-family: Arial, sans-serif; color: #080808; padding: 24px; }
          h1 { color: #2e0000; font-size: 20px; }
          p { color: #616161; font-size: 12px; }
          table { border-collapse: collapse; width: 100%; font-size: 11px; }
          th { background: #852622; color: #fff; text-align: left; }
          th, td { border: 1px solid #d5d5d5; padding: 7px; }
          tr:nth-child(even) { background: #f7f7f7; }
        </style>
      </head>
      <body>
        <h1>Attendance Correction</h1>
        <p>Daftar pengajuan koreksi absensi</p>
        <table><thead><tr>${headers.map((header) => `<th>${header}</th>`).join("")}</tr></thead><tbody>${rows}</tbody></table>
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
}

// Mengamankan nilai teks sebelum dimasukkan ke dokumen HTML export.
function escapeHtml(value: string) {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;")
}
