-- Supabase SQL scheduler untuk cleanup rejected submissions
-- Jalankan di Supabase Dashboard > SQL Editor

create extension if not exists pg_cron;
create extension if not exists pg_net;

select cron.schedule(
  'cleanup-rejected-submissions',
  '0 * * * *',
  $$
  select net.http_post(
    url := 'https://<project-ref>.supabase.co/functions/v1/cleanup-rejected',
    headers := jsonb_build_object(
      'Authorization', 'Bearer <SERVICE_ROLE_KEY>',
      'Content-Type', 'application/json'
    ),
    body := '{}'
  );
  $$
);

-- Catatan:
-- 1. Ganti <project-ref> dengan project ref Supabase kamu
-- 2. Ganti <SERVICE_ROLE_KEY> dengan service role key project kamu
-- 3. Pastikan Edge Function sudah dibuat dengan nama: cleanup-rejected
-- 4. Job ini berjalan setiap jam: '0 * * * *'

-- Optional: cek job yang aktif
-- select * from cron.job;

-- Optional: hapus scheduler jika nanti mau di-disable
-- select cron.unschedule('cleanup-rejected-submissions');
