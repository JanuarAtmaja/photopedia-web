import cv2
import numpy as np
import glob
import os

frames = glob.glob('assets/frames/*.png')
for f in frames:
    img = cv2.imread(f, cv2.IMREAD_UNCHANGED)
    if img is None or img.shape[2] != 4:
        print(f'{f}: Not a valid PNG with alpha channel')
        continue
    
    alpha = img[:,:,3]
    _, thresh = cv2.threshold(alpha, 10, 255, cv2.THRESH_BINARY_INV)
    
    contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    
    h, w = img.shape[:2]
    print(f'Frame: {os.path.basename(f)} ({w}x{h})')
    
    slots = []
    for cnt in contours:
        x, y, cw, ch = cv2.boundingRect(cnt)
        if cw > 50 and ch > 50:
            px = round((x / w) * 100, 2)
            py = round((y / h) * 100, 2)
            pw = round((cw / w) * 100, 2)
            ph = round((ch / h) * 100, 2)
            slots.append({'x': px, 'y': py, 'w': pw, 'h': ph, 'area': cw*ch})
            
    slots.sort(key=lambda s: s['y'])
    for s in slots:
        print(f"  ['x' => {s['x']}, 'y' => {s['y']}, 'width' => {s['w']}, 'height' => {s['h']}],")
