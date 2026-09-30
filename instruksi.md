
### Dashboard (`index.php`)
- List semua `frame_submissions` dengan status `pending`, urut terbaru dulu
- Tiap item tampilkan: thumbnail, judul frame, strip_type, submitter, tanggal submit
- Klik item → masuk ke `review.php`

### Halaman Review (`review.php`)
- Preview full gambar
- Info: ukuran asli (px) + estimasi cm, tipe strip yang dipilih user
- Tombol **Approve**:
  1. Insert row baru ke `frames` (copy data relevan dari `frame_submissions`)
  2. Copy/move file dari bucket `submissions/` ke bucket `frames/`
  3. Update `frame_submissions.status = 'approved'`, set `reviewed_at`
  4. Kirim email ke `submitter_email` via Resend API: "Frame kamu '[frame_title]' sudah di-approve dan sekarang bisa dipakai di Photopedia!"
- Tombol **Reject**:
  1. Textarea alasan penolakan (misal "ukuran tidak sesuai", "desain melanggar guideline")
  2. Update `frame_submissions.status = 'rejected'`, isi `rejection_reason`, set `reviewed_at`
  3. Kirim email ke `submitter_email` via Resend API: "Frame kamu '[frame_title]' belum lolos review. Alasan: [rejection_reason]"

### Keamanan
- Semua request ke Supabase (insert/update/storage) dilakukan dari backend PHP menggunakan service_role key — **jangan pernah** expose service_role key ke frontend/JS client
- Validasi ulang di backend admin (jangan percaya status dari client) sebelum eksekusi approve/reject

---

## 7. Retention Policy (Rejected Submissions)

- Submission dengan status `rejected` **dihapus otomatis setelah 12 jam** dari waktu `reviewed_at`
- Yang dihapus: row di `frame_submissions` + file terkait di bucket `submissions/`
- Implementasi:
  - **Vercel Cron Jobs** (`vercel.json` → `crons`) yang hit endpoint `api/cleanup-rejected.php` tiap 1 jam
  - Endpoint query submission `rejected` yang `reviewed_at` sudah lewat 12 jam, lalu hapus row + file storage terkait
  - Alternatif kalau mau lepas dari Vercel Cron: Supabase Edge Function + `pg_cron` langsung di database
- Approved submission **tidak** kena retention — sudah pindah ke `frames`; row `frame_submissions`-nya boleh dihapus langsung saat approve, atau dibiarkan sebagai arsip (pilih salah satu saat implementasi)