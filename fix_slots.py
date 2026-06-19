import cv2
import numpy as np
import glob
import os

frames_dir = 'assets/frames'
files = glob.glob(os.path.join(frames_dir, 'frame-*.png'))

php_code = ""

for file in files:
    img = cv2.imread(file, cv2.IMREAD_UNCHANGED)
    if img is None or img.shape[2] != 4:
        continue
        
    alpha = img[:,:,3]
    _, thresh = cv2.threshold(alpha, 10, 255, cv2.THRESH_BINARY_INV)
    
    contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    
    slots = []
    h_img, w_img = img.shape[:2]
    
    for cnt in contours:
        x, y, w, h = cv2.boundingRect(cnt)
        
        # Filter noise
        if w < 50 or h < 50:
            continue
            
        px = round((x / w_img) * 100, 2)
        py = round((y / h_img) * 100, 2)
        pw = round((w / w_img) * 100, 2)
        ph = round((h / h_img) * 100, 2)
        
        slots.append({'x': px, 'y': py, 'w': pw, 'h': ph})
        
    # Sort by Y
    slots = sorted(slots, key=lambda s: s['y'])
    
    # Fix overlaps
    for i in range(len(slots) - 1):
        bottom = slots[i]['y'] + slots[i]['h']
        next_top = slots[i+1]['y']
        if bottom >= next_top:
            # Overlaps! Reduce height of current slot
            slots[i]['h'] = round(next_top - slots[i]['y'] - 0.1, 2)
            
    # Output PHP
    basename = os.path.basename(file)
    slug = basename[6:-4]
    
    php_code += f"            '{slug}' => [\n"
    for s in slots:
        php_code += f"                ['x' => {s['x']}, 'y' => {s['y']}, 'width' => {s['w']}, 'height' => {s['h']}],\n"
    php_code += "            ],\n"

print(php_code)
