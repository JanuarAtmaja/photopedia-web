#!/usr/bin/env python3
"""
migrate_frames_to_db.py
=======================
Script migrasi sekali jalan: seed data frame & slot dari hardcode PHP
ke tabel `frames` di Supabase.

Cara pakai:
    python migrate_frames_to_db.py [--dry-run]

Opsi:
    --dry-run   Hanya tampilkan data yang akan diinsert, tidak benar-benar kirim ke DB.

Requirement:
    pip install requests pillow python-dotenv
"""

import os
import sys
import json
import requests
import io
from pathlib import Path

# Fix Windows console encoding for emojis
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# ── Load .env ────────────────────────────────────────────────
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    # Manual .env parser jika python-dotenv tidak terinstall
    env_file = Path(__file__).parent / '.env'
    if env_file.exists():
        for line in env_file.read_text(encoding='utf-8').splitlines():
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            key, _, value = line.partition('=')
            os.environ.setdefault(key.strip(), value.strip())

SUPABASE_URL         = os.environ.get('SUPABASE_URL', '').rstrip('/')
SUPABASE_SERVICE_KEY = os.environ.get('SUPABASE_SERVICE_ROLE_KEY', '')
DRY_RUN              = '--dry-run' in sys.argv

if not SUPABASE_URL or not SUPABASE_SERVICE_KEY:
    print("❌ ERROR: SUPABASE_URL dan SUPABASE_SERVICE_ROLE_KEY harus ada di .env")
    sys.exit(1)

# ── Data slot hardcode (dipindahkan dari helpers.php) ────────
SLOTS_DATA = {
    'arctic-monkeys-am': [
        {'x': 10.75, 'y': 10.86, 'width': 78.36, 'height': 22.11},
        {'x': 10.89, 'y': 35.02, 'width': 78.36, 'height': 22.11},
        {'x': 10.75, 'y': 59.13, 'width': 78.36, 'height': 22.11},
    ],
    'arctic-monkeys-fwn': [
        {'x': 5.52, 'y': 2.7,   'width': 88.97, 'height': 31.47},
        {'x': 5.52, 'y': 36.57, 'width': 88.97, 'height': 31.47},
    ],
    'arctic-monkeys-whatever': [
        {'x': 5.66, 'y': 2.3,   'width': 88.54, 'height': 23.51},
        {'x': 5.66, 'y': 28.11, 'width': 88.54, 'height': 23.51},
        {'x': 5.66, 'y': 53.93, 'width': 88.54, 'height': 23.51},
    ],
    'expo-stat-2026': [
        {'x': 15.37, 'y': 13.48, 'width': 69.26, 'height': 32.74},
        {'x': 15.37, 'y': 54.74, 'width': 69.26, 'height': 32.67},
    ],
    'expo-stat-special': [
        {'x': 10.04, 'y': 2.95,  'width': 79.92, 'height': 21.56},
        {'x': 10.04, 'y': 29.46, 'width': 79.77, 'height': 21.51},
        {'x': 10.04, 'y': 56.68, 'width': 79.77, 'height': 21.51},
    ],
    'happiness': [
        {'x': 7.92, 'y': 2.2,   'width': 84.3, 'height': 23.06},
        {'x': 7.92, 'y': 28.71, 'width': 84.3, 'height': 23.01},
        {'x': 7.92, 'y': 55.23, 'width': 84.3, 'height': 23.01},
    ],
    'hello': [
        {'x': 7.92, 'y': 2.2,   'width': 84.3, 'height': 23.06},
        {'x': 7.92, 'y': 28.71, 'width': 84.3, 'height': 23.01},
        {'x': 7.92, 'y': 55.23, 'width': 84.3, 'height': 23.01},
    ],
    'hindia-janji-palsu': [
        {'x': 13.89, 'y': 19.37, 'width': 72.22, 'height': 21.05},
        {'x': 13.89, 'y': 41.26, 'width': 72.22, 'height': 21.05},
        {'x': 13.89, 'y': 63.14, 'width': 72.22, 'height': 21.05},
    ],
    'hold-up': [
        {'x': 8.41, 'y': 2.32,  'width': 83.18, 'height': 23.54},
        {'x': 8.5,  'y': 25.96, 'width': 83.09, 'height': 27.24},
        {'x': 8.5,  'y': 55.46, 'width': 83.09, 'height': 22.37},
    ],
    'messsage-us': [
        {'x': 9.24, 'y': 10.17, 'width': 81.52, 'height': 21.78},
        {'x': 9.24, 'y': 36.79, 'width': 81.52, 'height': 21.81},
        {'x': 9.24, 'y': 63.6,  'width': 81.52, 'height': 21.78},
    ],
    'music-player': [
        {'x': 10.12, 'y': 3.8,   'width': 79.75, 'height': 21.8},
        {'x': 10.12, 'y': 28.85, 'width': 79.75, 'height': 21.8},
        {'x': 10.12, 'y': 53.9,  'width': 79.75, 'height': 21.8},
    ],
    'perunggu-dalam-dinamika': [
        {'x': 7.21, 'y': 2.5,   'width': 85.57, 'height': 30.27},
        {'x': 7.21, 'y': 34.17, 'width': 85.57, 'height': 30.27},
    ],
    'please-verify': [
        {'x': 2.49, 'y': 19.33, 'width': 94.92, 'height': 21.45},
        {'x': 2.49, 'y': 42.09, 'width': 94.92, 'height': 21.45},
        {'x': 2.49, 'y': 64.85, 'width': 94.92, 'height': 21.45},
    ],
    'reality-club-presents...': [
        {'x': 2.55, 'y': 7.25,  'width': 94.63, 'height': 29.16},
        {'x': 2.55, 'y': 51.28, 'width': 94.63, 'height': 29.16},
    ],
    'reality-club-who-knows-where-life-will-take-you': [
        {'x': 6.93, 'y': 2.4,   'width': 86.28, 'height': 19.96},
        {'x': 6.93, 'y': 25.21, 'width': 86.28, 'height': 19.96},
        {'x': 6.93, 'y': 47.92, 'width': 86.28, 'height': 19.96},
    ],
    'the-jeblogs-sambutlah': [
        {'x': 3.96, 'y': 4.15,  'width': 92.5, 'height': 22.16},
        {'x': 3.96, 'y': 27.86, 'width': 92.5, 'height': 22.16},
        {'x': 3.96, 'y': 51.58, 'width': 92.5, 'height': 22.16},
    ],
    'y2k-grid': [
        {'x': 10.12, 'y': 3.8,   'width': 80.06, 'height': 21.8},
        {'x': 10.12, 'y': 28.85, 'width': 79.75, 'height': 21.8},
        {'x': 10.12, 'y': 53.9,  'width': 79.75, 'height': 21.8},
    ],
}

# ── Bangun payload dari file PNG di folder assets/frames ─────
def get_image_size(filepath: Path) -> tuple[int, int]:
    """Baca dimensi gambar. Coba PIL dulu, fallback ke manual PNG header."""
    try:
        from PIL import Image
        with Image.open(filepath) as img:
            return img.size  # (width, height)
    except ImportError:
        pass

    # Fallback: baca PNG header langsung (bytes 16-24)
    with open(filepath, 'rb') as f:
        f.read(16)  # skip signature + IHDR length + type
        import struct
        width  = struct.unpack('>I', f.read(4))[0]
        height = struct.unpack('>I', f.read(4))[0]
        return width, height

def build_frames() -> list[dict]:
    frames_dir = Path(__file__).parent / 'assets' / 'frames'
    frames = []

    png_files = sorted(frames_dir.glob('frame-*.png'))
    if not png_files:
        print(f"⚠️  Tidak ada file frame ditemukan di: {frames_dir}")
        return frames

    for png_file in png_files:
        basename = png_file.stem               # "frame-film-strip"
        slug     = basename[6:]                # "film-strip"
        label    = slug.replace('-', ' ').title()  # "Film Strip"
        filename = png_file.name               # "frame-film-strip.png"

        slots = SLOTS_DATA.get(slug.lower(), [
            {'x': 0, 'y': 0, 'width': 100, 'height': 100}  # fallback 1 slot full
        ])

        try:
            w, h = get_image_size(png_file)
        except Exception:
            w, h = 1080, 1920

        frames.append({
            'id':         slug,
            'label':      label,
            'filename':   filename,
            'slots':      slots,
            'width':      w,
            'height':     h,
            'is_active':  True,
            'sort_order': 0,
        })

    return frames

# ── Upsert ke Supabase ───────────────────────────────────────
def upsert_frame(frame: dict) -> dict:
    """Upsert satu frame ke Supabase (insert or update by primary key)."""
    endpoint = f"{SUPABASE_URL}/rest/v1/frames"
    headers  = {
        'Authorization': f"Bearer {SUPABASE_SERVICE_KEY}",
        'apikey':        SUPABASE_SERVICE_KEY,
        'Content-Type':  'application/json',
        'Prefer':        'resolution=merge-duplicates,return=representation',
    }
    # Serialise slots sebagai JSON string untuk JSONB
    payload = {**frame, 'slots': frame['slots']}  # requests otomatis json.dumps
    resp = requests.post(endpoint, headers=headers, json=payload)
    return {'status': resp.status_code, 'body': resp.text}

# ── Main ─────────────────────────────────────────────────────
def main():
    print("🚀 Photopedia — Migrasi Frame ke Supabase")
    print(f"   URL  : {SUPABASE_URL}")
    print(f"   Mode : {'DRY RUN (tidak insert)' if DRY_RUN else 'LIVE (insert ke DB)'}")
    print()

    frames = build_frames()
    if not frames:
        print("❌ Tidak ada frame untuk dimigrasi.")
        return

    print(f"📋 Ditemukan {len(frames)} frame:\n")
    ok_count  = 0
    err_count = 0

    for f in frames:
        slot_count = len(f['slots'])
        print(f"  → [{f['id']}] {f['label']} — {slot_count} slot, {f['width']}x{f['height']}px")

        if not DRY_RUN:
            result = upsert_frame(f)
            status = result['status']
            if 200 <= status < 300:
                print(f"     ✅ Berhasil (HTTP {status})")
                ok_count += 1
            else:
                print(f"     ❌ Gagal (HTTP {status}): {result['body'][:200]}")
                err_count += 1
        else:
            print(f"     ℹ️  [dry-run] slots: {json.dumps(f['slots'])[:80]}...")
            ok_count += 1

    print()
    if DRY_RUN:
        print(f"✅ Dry run selesai — {ok_count} frame siap diinsert.")
        print("   Jalankan tanpa --dry-run untuk insert ke Supabase.")
    else:
        print(f"🏁 Selesai: {ok_count} berhasil, {err_count} gagal.")

if __name__ == '__main__':
    main()
