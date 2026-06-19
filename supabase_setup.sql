-- ============================================================
--  Photopedia — Supabase SQL Setup
--  Jalankan di: Supabase Dashboard > SQL Editor
-- ============================================================

-- 1. Buat tabel photos
CREATE TABLE IF NOT EXISTS public.photos (
    id          UUID         DEFAULT gen_random_uuid() PRIMARY KEY,
    url         TEXT         NOT NULL,
    path        TEXT         NOT NULL,
    session_id  TEXT,
    created_at  TIMESTAMPTZ  DEFAULT NOW()
);

-- 2. Enable Row Level Security
ALTER TABLE public.photos ENABLE ROW LEVEL SECURITY;

-- 3. Policy: semua orang boleh READ (untuk gallery publik)
CREATE POLICY "Public can read photos"
    ON public.photos
    FOR SELECT
    USING (true);

-- 4. Policy: hanya service role yang bisa INSERT
--    (service role otomatis bypass RLS, tapi tambahkan anon juga agar via REST bisa)
--    Catatan: upload.php pakai service_role key, jadi ini opsional.
-- CREATE POLICY "Service role can insert"
--     ON public.photos
--     FOR INSERT
--     WITH CHECK (true);
