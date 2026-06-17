import tkinter

root = tkinter.Tk()
img = tkinter.PhotoImage(file='assets/frames/frame-Reality-Club-Presents....png')

width = img.width()
height = img.height()
print(f"Image Size: {width}x{height}")

transparent_pixels = []

for y in range(0, height, 5):
    for x in range(0, width, 5):
        if img.transparency_get(x, y):
            transparent_pixels.append((x, y))

boxes = []
for x, y in transparent_pixels:
    matched = False
    for b in boxes:
        if x >= b['minX'] - 50 and x <= b['maxX'] + 50 and y >= b['minY'] - 50 and y <= b['maxY'] + 50:
            b['minX'] = min(b['minX'], x)
            b['maxX'] = max(b['maxX'], x)
            b['minY'] = min(b['minY'], y)
            b['maxY'] = max(b['maxY'], y)
            matched = True
            break
    if not matched:
        boxes.append({'minX': x, 'maxX': x, 'minY': y, 'maxY': y})

merged = True
while merged:
    merged = False
    for i in range(len(boxes)):
        for j in range(i+1, len(boxes)):
            b1 = boxes[i]
            b2 = boxes[j]
            if not (b1['maxX'] < b2['minX'] - 50 or b1['minX'] > b2['maxX'] + 50 or b1['maxY'] < b2['minY'] - 50 or b1['minY'] > b2['maxY'] + 50):
                boxes[i]['minX'] = min(b1['minX'], b2['minX'])
                boxes[i]['maxX'] = max(b1['maxX'], b2['maxX'])
                boxes[i]['minY'] = min(b1['minY'], b2['minY'])
                boxes[i]['maxY'] = max(b1['maxY'], b2['maxY'])
                del boxes[j]
                merged = True
                break
        if merged:
            break

boxes.sort(key=lambda b: b['minY'])
for i, b in enumerate(boxes):
    if b['maxX'] - b['minX'] < 50 or b['maxY'] - b['minY'] < 50:
        continue
    x_pct = round((b['minX'] / width) * 100, 2)
    y_pct = round((b['minY'] / height) * 100, 2)
    w_pct = round(((b['maxX'] - b['minX']) / width) * 100, 2)
    h_pct = round(((b['maxY'] - b['minY']) / height) * 100, 2)
    print(f"Slot {i}: ['x' => {x_pct}, 'y' => {y_pct}, 'width' => {w_pct}, 'height' => {h_pct}],")

root.destroy()
