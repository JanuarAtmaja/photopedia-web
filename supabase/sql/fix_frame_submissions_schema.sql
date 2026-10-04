-- Align frame_submissions with the data written by api/submit-frame.php.
ALTER TABLE public.frame_submissions
    ADD COLUMN IF NOT EXISTS filename TEXT,
    ADD COLUMN IF NOT EXISTS image_url TEXT,
    ADD COLUMN IF NOT EXISTS width_px INTEGER,
    ADD COLUMN IF NOT EXISTS height_px INTEGER,
    ADD COLUMN IF NOT EXISTS slots JSONB NOT NULL DEFAULT '[]',
    ALTER COLUMN submitter_name DROP NOT NULL;
