#!/usr/bin/env python3
"""
detect_slots.py
===============
Deteksi slot transparan dari file frame PNG, lalu output ke terminal
atau langsung insert ke Supabase.

Cara pakai:
    # Mode lama: print ke terminal
    python detect_slots.py

    # Mode insert ke Supabase (untuk frame baru)
    python detect_slots.py --insert [--frame frame-nama.png]

    # Dry run insert (lihat payload tanpa kirim)
    python detect_slots.py --insert --dry-run [--frame frame-nama.png]

Requirement:
    pip install opencv-python-headless numpy requests python-dotenv
"""

import cv2
import numpy as np
import glob
import os
import sys
import json
import argparse
from pathlib import Path

# ── Load .env ─────────────────────────────────────────────────
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
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

# ── Argparse ──────────────────────────────────────────────────
def parse_args():
    p = argparse.ArgumentParser(description='Detect transparent slots in frame PNGs')
    p.add_argument('--insert',   action='store_true', help='Insert/upsert hasil ke Supabase')
    p.add_argument('--dry-run',  action='store_true', help='Tampilkan payload tanpa benar-benar insert')
    p.add_argument('--frame',    type=str, default=None,
                   help='Proses hanya file ini (e.g. frame-film-strip.png). Default: semua frame.')
    p.add_argument('--min-size', type=int, default=50,
                   help='Ukuran minimum slot (px) untuk diakui sebagai slot. Default: 50')
    return p.parse_args()

# ── Deteksi slot dari satu file PNG ───────────────────────────
def detect_slots(filepath: str, min_size: int = 50) -> dict:
    """
    Kembalikan dict:
    {
        'filename': 'frame-xxx.png',
        'slug': 'xxx',
        'label': 'Xxx',
        'width': int,
        'height': int,
        'slots': [{'x': float, 'y': float, 'width': float, 'height': float}, ...]
    }
    """
    img = cv2.imread(filepath, cv2.IMREAD_UNCHANGED)

    if img is None:
        print(f'  ⚠️  Tidak bisa membaca: {filepath}')
        return None

    if img.shape[2] != 4:
        print(f'  ⚠️  Bukan PNG dengan alpha channel: {filepath}')
        return None

    alpha = img[:, :, 3]
    h_img, w_img = img.shape[:2]

    # Threshold: piksel dengan alpha < 10 dianggap transparan (area foto)
    _, thresh = cv2.threshold(alpha, 10, 255, cv2.THRESH_BINARY_INV)

    contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    basename = os.path.basename(filepath)      # "frame-film-strip.png"
    slug     = basename[6:-4]                  # "film-strip"
    label    = slug.replace('-', ' ').title()  # "Film Strip"

    slots = []
    for cnt in contours:
        x, y, cw, ch = cv2.boundingRect(cnt)
        if cw < min_size or ch < min_size:
            continue  # skip noise
        px = round((x  / w_img) * 100, 2)
        py = round((y  / h_img) * 100, 2)
        pw = round((cw / w_img) * 100, 2)
        ph = round((ch / h_img) * 100, 2)
        slots.append({'x': px, 'y': py, 'width': pw, 'height': ph, '_area': cw * ch})

    # Sort by Y (atas ke bawah), lalu X
    slots.sort(key=lambda s: (s['y'], s['x']))

    # Fix overlap: pastikan slot tidak overlap vertikal
    for i in range(len(slots) - 1):
        bottom   = slots[i]['y'] + slots[i]['height']
        next_top = slots[i + 1]['y']
        if bottom > next_top:
            slots[i]['height'] = round(next_top - slots[i]['y'] - 0.1, 2)

    # Buang field internal '_area'
    clean_slots = [{k: v for k, v in s.items() if k != '_area'} for s in slots]

    return {
        'filename': basename,
        'slug':     slug,
        'label':    label,
        'width':    w_img,
        'height':   h_img,
        'slots':    clean_slots,
    }

# ── Upsert ke Supabase ────────────────────────────────────────
def upsert_to_supabase(frame_data: dict) -> dict:
    import requests  # hanya import saat diperlukan

    if not SUPABASE_URL or not SUPABASE_SERVICE_KEY:
        print("  ❌ SUPABASE_URL / SUPABASE_SERVICE_ROLE_KEY tidak ditemukan di .env")
        return {'ok': False}

    endpoint = f"{SUPABASE_URL}/rest/v1/frames"
    headers  = {
        'Authorization': f"Bearer {SUPABASE_SERVICE_KEY}",
        'apikey':        SUPABASE_SERVICE_KEY,
        'Content-Type':  'application/json',
        'Prefer':        'resolution=merge-duplicates,return=representation',
    }
    payload = {
        'id':        frame_data['slug'],
        'label':     frame_data['label'],
        'filename':  frame_data['filename'],
        'slots':     frame_data['slots'],
        'width':     frame_data['width'],
        'height':    frame_data['height'],
        'is_active': True,
    }

    resp = requests.post(endpoint, headers=headers, json=payload, timeout=15)
    return {'ok': resp.ok, 'status': resp.status_code, 'body': resp.text}

# ── Main ──────────────────────────────────────────────────────
def main():
    args = parse_args()

    frames_dir = Path(__file__).parent / 'assets' / 'frames'

    if args.frame:
        # Proses satu file spesifik
        target = frames_dir / args.frame
        if not target.exists():
            print(f"❌ File tidak ditemukan: {target}")
            sys.exit(1)
        files = [str(target)]
    else:
        files = sorted(glob.glob(str(frames_dir / 'frame-*.png')))

    if not files:
        print(f"⚠️  Tidak ada file frame ditemukan di: {frames_dir}")
        sys.exit(0)

    mode_label = '🔍 Deteksi Slot'
    if args.insert:
        mode_label += ' + Insert ke Supabase'
        if args.dry_run:
            mode_label += ' (DRY RUN)'

    print(f"\n{mode_label}")
    print(f"Min slot size: {args.min_size}px | File: {len(files)} frame\n")
    print("─" * 60)

    ok_count  = 0
    err_count = 0

    for filepath in files:
        result = detect_slots(filepath, min_size=args.min_size)
        if result is None:
            err_count += 1
            continue

        slot_count = len(result['slots'])
        print(f"\n📐 {result['filename']}  ({result['width']}x{result['height']}px)  —  {slot_count} slot")

        if args.insert or not args.insert:
            # Selalu tampilkan slot yang terdeteksi
            for s in result['slots']:
                print(f"   x={s['x']:6.2f}%  y={s['y']:6.2f}%  w={s['width']:6.2f}%  h={s['height']:6.2f}%")

        if args.insert:
            if args.dry_run:
                print(f"   ℹ️  [dry-run] Payload:")
                print(f"   {json.dumps({'id': result['slug'], 'slots': result['slots']}, indent=4)}")
                ok_count += 1
            else:
                insert_result = upsert_to_supabase(result)
                if insert_result['ok']:
                    print(f"   ✅ Berhasil di-upsert ke Supabase (HTTP {insert_result['status']})")
                    ok_count += 1
                else:
                    print(f"   ❌ Gagal (HTTP {insert_result['status']}): {insert_result['body'][:200]}")
                    err_count += 1
        else:
            ok_count += 1

    print("\n" + "─" * 60)
    if args.insert and not args.dry_run:
        print(f"🏁 Selesai: {ok_count} berhasil, {err_count} gagal.")
    else:
        print(f"✅ Selesai mendeteksi {ok_count} frame.")
        if not args.insert:
            print("   Tambahkan --insert untuk langsung upsert ke Supabase.")

if __name__ == '__main__':
    main()
