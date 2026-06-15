// assets/js/editor.js — Compositing, Global Filters, Stickers, Text
'use strict';

const Editor = (() => {
  let canvas, ctx;
  let cameraData = null; // { photos: [{img, filter}], frame: {url, slots, label} }
  let frameImage = null;
  
  // State
  let globalFilter = 'none';
  let stickers = []; // Array of { type: 'emoji'|'text', content, x, y, size, color }
  
  // Drag state
  let isDragging = false;
  let dragSticker = null;
  let dragOffsetX = 0;
  let dragOffsetY = 0;

  // Photo drag state
  let isDraggingPhoto = false;
  let dragPhotoIndex = null;
  let dragLastX = 0;
  let dragLastY = 0;
  let dropTargetIndex = null;

  // DOM Elements
  const templateThumb = document.getElementById('selected-template-thumb');
  const templateName  = document.getElementById('selected-template-name');
  const templateSlots = document.getElementById('selected-template-slots');
  const filtersContainer = document.getElementById('post-filters-container');
  const emojiGrid     = document.querySelector('#tab-emoji .emoji-grid');
  
  // Available filters (matches camera)
  const FILTERS = [
    { name: 'None', value: 'none', bg: 'linear-gradient(45deg, #ccc, #eee)' },
    { name: 'Vintage', value: 'sepia(0.6) contrast(1.1)', bg: 'linear-gradient(45deg, #d4a373, #faedcd)' },
    { name: 'Neon', value: 'saturate(2) hue-rotate(45deg)', bg: 'linear-gradient(45deg, #ff9ff3, #feca57)' },
    { name: 'B&W', value: 'grayscale(1) contrast(1.2)', bg: 'linear-gradient(45deg, #555, #999)' }
  ];

  // Emojis
  const EMOJIS = ['✨','💖','🔥','😎','🎉','✌️','💯','🎀','👑','🍒','🦋','🌸','🌈','🌙','🪐','🍕','☕','👽'];
  const COLORS = ['#FFFFFF','#000000','#FF3B30','#FF9500','#FFCC00','#4CD964','#5AC8FA','#007AFF','#5856D6','#FF2D55'];
  let currentTextColor = '#FFFFFF';

  function init(canvasEl, data) {
    canvas = canvasEl;
    ctx = canvas.getContext('2d');
    cameraData = data;
    stickers = [];
    globalFilter = 'none';
    
    // Initialize offsets for photos
    if (cameraData && cameraData.photos) {
      cameraData.photos.forEach(p => {
        if (p && p.offsetX === undefined) {
          p.offsetX = 0;
          p.offsetY = 0;
        }
      });
    }
    
    // Setup Template Info
    if (templateThumb) templateThumb.src = data.frame.url;
    if (templateName) templateName.textContent = data.frame.label || 'Template';
    if (templateSlots) templateSlots.textContent = `${data.frame.slots.length} photo positions`;

    populateFilters();
    populateEmojis();
    populateColors();
    setupCanvasEvents();
    setupTextTool();

    // Load frame image
    frameImage = new Image();
    frameImage.crossOrigin = 'anonymous';
    frameImage.onload = () => {
      // Set high-res canvas size based on frame
      canvas.width = frameImage.naturalWidth || 1200;
      canvas.height = frameImage.naturalHeight || 1800;
      render();
    };
    frameImage.src = data.frame.url;
  }

  // ── Render composite ─────────────────────────────────────
  function render() {
    if (!ctx || !frameImage) return;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Draw photos in slots
    cameraData.frame.slots.forEach((slot, i) => {
      const photoData = cameraData.photos[i];
      if (photoData && photoData.img) {
        // Calculate slot coords in px
        const sx = (slot.x / 100) * canvas.width;
        const sy = (slot.y / 100) * canvas.height;
        const sw = (slot.width / 100) * canvas.width;
        const sh = (slot.height / 100) * canvas.height;

        ctx.save();
        ctx.beginPath();
        ctx.rect(sx, sy, sw, sh);
        ctx.clip();

        // Apply filter: If global is 'none', use photo's live filter, else use global
        const activeFilter = globalFilter !== 'none' ? globalFilter : photoData.filter;
        ctx.filter = activeFilter !== 'none' ? activeFilter : 'none';

        const offsetX = photoData.offsetX || 0;
        const offsetY = photoData.offsetY || 0;

        ctx.drawImage(photoData.img, sx + offsetX, sy + offsetY, sw, sh);
        ctx.restore();
        
        // Draw drop target highlight if dragging
        if (isDraggingPhoto && dropTargetIndex === i && dragPhotoIndex !== i) {
          ctx.save();
          ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
          ctx.fillRect(sx, sy, sw, sh);
          ctx.strokeStyle = '#4B3FA0';
          ctx.lineWidth = 4;
          ctx.strokeRect(sx, sy, sw, sh);
          ctx.restore();
        }
      }
    });

    // Draw frame overlay
    ctx.save();
    ctx.filter = 'none';
    ctx.drawImage(frameImage, 0, 0, canvas.width, canvas.height);
    ctx.restore();

    // Draw stickers
    stickers.forEach(st => {
      ctx.save();
      if (st.type === 'emoji' || st.type === 'text') {
        ctx.font = `${st.size}px "Inter", sans-serif`;
        ctx.fillStyle = st.color || '#fff';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        // Stroke for visibility
        if (st.type === 'text') {
          ctx.lineWidth = st.size * 0.08;
          ctx.strokeStyle = st.color === '#FFFFFF' ? '#000' : '#FFF';
          ctx.strokeText(st.content, st.x, st.y);
        }
        ctx.fillText(st.content, st.x, st.y);
      }
      ctx.restore();
    });
  }

  // ── Filters UI ───────────────────────────────────────────
  function populateFilters() {
    if (!filtersContainer) return;
    filtersContainer.innerHTML = '';
    FILTERS.forEach(f => {
      const btn = document.createElement('div');
      btn.className = `filter-item ${globalFilter === f.value ? 'active' : ''}`;
      btn.innerHTML = `
        <div class="filter-preview" style="background:${f.bg}"></div>
        <span>${f.name}</span>
      `;
      btn.onclick = () => {
        globalFilter = f.value;
        document.querySelectorAll('#post-filters-container .filter-item').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        render();
      };
      filtersContainer.appendChild(btn);
    });
  }

  // ── Stickers UI ──────────────────────────────────────────
  function populateEmojis() {
    if (!emojiGrid) return;
    emojiGrid.innerHTML = '';
    EMOJIS.forEach(e => {
      const btn = document.createElement('button');
      btn.className = 'emoji-btn';
      btn.textContent = e;
      btn.onclick = () => addSticker('emoji', e);
      emojiGrid.appendChild(btn);
    });
  }

  function populateColors() {
    const colorRow = document.querySelector('.color-row');
    if (!colorRow) return;
    colorRow.innerHTML = '';
    COLORS.forEach(c => {
      const btn = document.createElement('div');
      btn.className = `color-swatch ${c === currentTextColor ? 'active' : ''}`;
      btn.style.background = c;
      if (c === '#FFFFFF') btn.style.borderColor = '#ccc';
      btn.onclick = () => {
        currentTextColor = c;
        document.querySelectorAll('.color-swatch').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      };
      colorRow.appendChild(btn);
    });
  }

  function setupTextTool() {
    const addBtn = document.getElementById('add-text-btn');
    const input  = document.getElementById('text-input');
    const sizeIn = document.getElementById('text-size');
    
    if (addBtn) addBtn.onclick = () => {
      if (!input.value.trim()) return;
      // Map slider (24-120) to canvas scale.
      // Canvas is e.g. 1200px wide. Display might be 300px. Ratio is ~4.
      // So size 48 on screen is actually 48 * 4 on canvas.
      const displaySize = parseInt(sizeIn.value, 10);
      const canvasScale = canvas.width / canvas.clientWidth;
      const actualSize = displaySize * canvasScale;
      
      addSticker('text', input.value.trim(), actualSize, currentTextColor);
      input.value = '';
    };
  }

  function addSticker(type, content, size = null, color = null) {
    const canvasScale = canvas.width / canvas.clientWidth;
    const defaultSize = size || (60 * canvasScale); // 60px visual size

    stickers.push({
      type,
      content,
      x: canvas.width / 2,
      y: canvas.height / 2,
      size: defaultSize,
      color: color
    });
    render();
  }

  // ── Drag & Drop ──────────────────────────────────────────
  function setupCanvasEvents() {
    canvas.addEventListener('mousedown', onPointerDown);
    canvas.addEventListener('touchstart', onPointerDown, {passive:false});
    window.addEventListener('mousemove', onPointerMove);
    window.addEventListener('touchmove', onPointerMove, {passive:false});
    window.addEventListener('mouseup', onPointerUp);
    window.addEventListener('touchend', onPointerUp);
  }

  function getPointerPos(e) {
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
      x: (clientX - rect.left) * (canvas.width / rect.width),
      y: (clientY - rect.top) * (canvas.height / rect.height)
    };
  }

  function onPointerDown(e) {
    const pos = getPointerPos(e);
    // Find sticker (reverse order to pick top)
    for (let i = stickers.length - 1; i >= 0; i--) {
      const st = stickers[i];
      // Approximate hit box based on size
      ctx.font = `${st.size}px "Inter", sans-serif`;
      const metrics = ctx.measureText(st.content);
      const width = metrics.width;
      const height = st.size; 
      
      if (Math.abs(pos.x - st.x) <= width/2 && Math.abs(pos.y - st.y) <= height/2) {
        isDragging = true;
        dragSticker = st;
        dragOffsetX = pos.x - st.x;
        dragOffsetY = pos.y - st.y;
        e.preventDefault();
        return;
      }
    }

    // Check if clicked inside a photo slot
    if (cameraData && cameraData.frame && cameraData.frame.slots) {
      for (let i = 0; i < cameraData.frame.slots.length; i++) {
        const slot = cameraData.frame.slots[i];
        const sx = (slot.x / 100) * canvas.width;
        const sy = (slot.y / 100) * canvas.height;
        const sw = (slot.width / 100) * canvas.width;
        const sh = (slot.height / 100) * canvas.height;
        
        if (pos.x >= sx && pos.x <= sx + sw && pos.y >= sy && pos.y <= sy + sh) {
          isDraggingPhoto = true;
          dragPhotoIndex = i;
          dragLastX = pos.x;
          dragLastY = pos.y;
          dropTargetIndex = i;
          e.preventDefault();
          return;
        }
      }
    }
  }

  function onPointerMove(e) {
    if (!isDragging && !isDraggingPhoto) return;
    e.preventDefault();
    const pos = getPointerPos(e);
    
    if (isDragging && dragSticker) {
      dragSticker.x = pos.x - dragOffsetX;
      dragSticker.y = pos.y - dragOffsetY;
      render();
    } else if (isDraggingPhoto && dragPhotoIndex !== null) {
      const dx = pos.x - dragLastX;
      const dy = pos.y - dragLastY;
      
      const photo = cameraData.photos[dragPhotoIndex];
      if (photo) {
        photo.offsetX = (photo.offsetX || 0) + dx;
        photo.offsetY = (photo.offsetY || 0) + dy;
      }
      
      dragLastX = pos.x;
      dragLastY = pos.y;
      
      // Determine drop target
      dropTargetIndex = dragPhotoIndex; // default to self
      if (cameraData && cameraData.frame && cameraData.frame.slots) {
        for (let i = 0; i < cameraData.frame.slots.length; i++) {
          const slot = cameraData.frame.slots[i];
          const sx = (slot.x / 100) * canvas.width;
          const sy = (slot.y / 100) * canvas.height;
          const sw = (slot.width / 100) * canvas.width;
          const sh = (slot.height / 100) * canvas.height;
          
          if (pos.x >= sx && pos.x <= sx + sw && pos.y >= sy && pos.y <= sy + sh) {
            dropTargetIndex = i;
            break;
          }
        }
      }
      render();
    }
  }

  function onPointerUp() {
    isDragging = false;
    dragSticker = null;
    
    if (isDraggingPhoto) {
      if (dropTargetIndex !== null && dropTargetIndex !== dragPhotoIndex) {
        // Swap photos
        const temp = cameraData.photos[dragPhotoIndex];
        cameraData.photos[dragPhotoIndex] = cameraData.photos[dropTargetIndex];
        cameraData.photos[dropTargetIndex] = temp;
        
        // Reset offsets after swap to prevent confusion
        if (cameraData.photos[dragPhotoIndex]) {
          cameraData.photos[dragPhotoIndex].offsetX = 0;
          cameraData.photos[dragPhotoIndex].offsetY = 0;
        }
        if (cameraData.photos[dropTargetIndex]) {
          cameraData.photos[dropTargetIndex].offsetX = 0;
          cameraData.photos[dropTargetIndex].offsetY = 0;
        }
      }
      
      isDraggingPhoto = false;
      dragPhotoIndex = null;
      dropTargetIndex = null;
      render();
    }
  }

  // ── Export ───────────────────────────────────────────────
  function exportDataUrl() {
    // Ensure final render
    render();
    return canvas.toDataURL('image/jpeg', 0.95);
  }

  function exportBlob() {
    return new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.95));
  }

  // Basic editor tab switching (already in app.js, but we can hook if needed)
  document.querySelectorAll('.editor-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.editor-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.editor-panel-body').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(`tab-${tab.dataset.tab}`)?.classList.add('active');
    });
  });

  document.getElementById('change-template-btn')?.addEventListener('click', () => {
    App.navigate('frames');
  });

  return { init, exportDataUrl, exportBlob };
})();
