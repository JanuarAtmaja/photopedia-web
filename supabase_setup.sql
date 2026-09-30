-- ============================================================
--  Photopedia — Supabase SQL Setup
--  Jalankan di: Supabase Dashboard > SQL Editor
-- ============================================================

-- ============================================================
--  TABEL: photos
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

-- ============================================================
--  TABEL: frames
--  Menyimpan metadata frame beserta data slot (posisi foto)
--  secara dinamis tanpa perlu hardcode di kode PHP.
-- ============================================================

-- 5. Buat tabel frames
CREATE TABLE IF NOT EXISTS public.frames (
    id          TEXT         PRIMARY KEY,              -- slug, e.g. "film-strip"
    label       TEXT         NOT NULL,                 -- "Film Strip"
    filename    TEXT         NOT NULL,                 -- "frame-film-strip.png"
    slots       JSONB        NOT NULL DEFAULT '[]',    -- [{x, y, width, height}, ...]
    width       INTEGER      DEFAULT 1080,
    height      INTEGER      DEFAULT 1920,
    is_active   BOOLEAN      DEFAULT true,
    sort_order  INTEGER      DEFAULT 0,
    created_at  TIMESTAMPTZ  DEFAULT NOW(),
    updated_at  TIMESTAMPTZ  DEFAULT NOW()
);

-- 6. Enable RLS untuk tabel frames
ALTER TABLE public.frames ENABLE ROW LEVEL SECURITY;

-- 7. Policy: semua orang boleh READ frame yang aktif
CREATE POLICY "Public can read active frames"
    ON public.frames
    FOR SELECT
    USING (is_active = true);

-- 8. Trigger: auto-update kolom updated_at saat row diupdate
CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER frames_updated_at
    BEFORE UPDATE ON public.frames
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- 9. Index untuk query umum
CREATE INDEX IF NOT EXISTS idx_frames_is_active    ON public.frames (is_active);
CREATE INDEX IF NOT EXISTS idx_frames_sort_order   ON public.frames (sort_order, label);

-- ============================================================
--  TABEL: frame_submissions
--  Menyimpan antrean submission frame dari user
-- ============================================================
CREATE TABLE IF NOT EXISTS public.frame_submissions (
    id                UUID         DEFAULT gen_random_uuid() PRIMARY KEY,
    frame_title       TEXT         NOT NULL,
    filename          TEXT         NOT NULL,
    submitter_email   TEXT         NOT NULL,
    submitter_name    TEXT         NOT NULL,
    strip_type        TEXT         NOT NULL,
    slots             JSONB        NOT NULL DEFAULT '[]',
    width             INTEGER      DEFAULT 1080,
    height            INTEGER      DEFAULT 1920,
    status            TEXT         DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    rejection_reason  TEXT,
    submitted_at      TIMESTAMPTZ  DEFAULT NOW(),
    reviewed_at       TIMESTAMPTZ
);

-- Enable RLS
ALTER TABLE public.frame_submissions ENABLE ROW LEVEL SECURITY;

-- Policy: Publik hanya bisa insert (submit)
CREATE POLICY "Public can insert frame_submissions"
    ON public.frame_submissions
    FOR INSERT
    WITH CHECK (true);

-- Policy: Hanya service_role yang bisa SELECT/UPDATE (via API admin)
-- (service_role bypasses RLS automatically)
