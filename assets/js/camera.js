// assets/js/camera.js — WebRTC camera, Jepreto-style UI, raw photo capture
'use strict';

const Camera = (() => {
  let stream       = null;
  let frameData    = null; // {url, slots: [...]}
  let capturedPhotos = []; // Array of { img: Image, filter: string } or null
  let currentSlotIndex = 0;

  // Settings
  let captureDelay = 0;
  let isMirrored   = true;
  let currentFilter = 'none';

  const video      = document.getElementById('video-stream');
  const countdown  = document.getElementById('countdown-overlay');
  const flashEl    = document.getElementById('camera-flash');
  const errOverlay = document.querySelector('.camera-permission-error');
  const captureBtn = document.getElementById('capture-btn');
  const switchBtn  = document.getElementById('switch-camera-btn');
  const retakeBtn  = document.getElementById('retake-btn');
  const nextBtn    = document.getElementById('camera-next-btn');

  let currentDeviceId = null;
  let videoDevices = [];
  const stripContainer = document.getElementById('captured-strip');
  const photoCounter = document.getElementById('photo-counter');

  // Upload Elements
  const dropzone = document.getElementById('camera-dropzone');
  const uploadInput = document.getElementById('camera-upload-input');
  const uploadBtn = document.getElementById('sidebar-upload-box');
  const cameraCenter = document.querySelector('.camera-center');

  // DOM Elements for settings
  const delayBtns    = document.querySelectorAll('#delay-toggles .toggle-btn');
  const mirrorToggle = document.getElementById('mirror-toggle');
  const filterBtns   = document.querySelectorAll('.filter-item');

  // We show the video element directly for live preview
  if (video) video.style.display = 'block';

  // ── Start camera stream ──────────────────────────────────
  async function start() {
    if (!navigator.mediaDevices?.getUserMedia) {
      showError('Browser kamu tidak mendukung WebRTC. Coba Chrome atau Safari terbaru.');
      return;
    }
    try {
      const constraints = {
        audio: false,
        video: currentDeviceId 
          ? { deviceId: { exact: currentDeviceId }, width: { ideal: 1280 }, height: { ideal: 960 } } 
          : { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }
      };
      stream = await navigator.mediaDevices.getUserMedia(constraints);
      video.srcObject = stream;
      await video.play();
      applySettings();

      const devices = await navigator.mediaDevices.enumerateDevices();
      videoDevices = devices.filter(d => d.kind === 'videoinput');
      
      if (videoDevices.length > 1 && switchBtn) {
          switchBtn.style.display = 'flex';
      } else if (switchBtn) {
          switchBtn.style.display = 'none';
      }

      if (!currentDeviceId && videoDevices.length > 0) {
          const track = stream.getVideoTracks()[0];
          const currentDevice = videoDevices.find(d => d.label === track.label);
          if (currentDevice) currentDeviceId = currentDevice.deviceId;
      }
    } catch (err) {
      const msgs = {
        NotAllowedError:  '🚫 Akses kamera ditolak. Izinkan kamera di pengaturan browser kamu ya.',
        NotFoundError:    '📷 Kamera tidak ditemukan. Pastikan kamera terhubung.',
        NotReadableError: '⚠️ Kamera sedang dipakai aplikasi lain.',
      };
      showError(msgs[err.name] ?? `Error: ${err.message}`);
    }
  }

  function stop() {
    stream?.getTracks().forEach(t => t.stop());
    stream = null;
  }

  // ── Load frame overlay & setup slots ─────────────────────
  function loadFrame(frame) {
    frameData = frame;
    const oldPhotos = capturedPhotos || [];
    capturedPhotos = new Array(frame.slots.length).fill(null);
    
    // Preserve existing photos up to the new slot count
    for(let i = 0; i < frame.slots.length; i++) {
        if (oldPhotos[i]) {
            capturedPhotos[i] = oldPhotos[i];
        }
    }
    
    // Find next empty slot
    currentSlotIndex = capturedPhotos.findIndex(p => p === null);
    if (currentSlotIndex === -1) currentSlotIndex = frame.slots.length;
    
    renderStrip();
    checkCompletion();
  }

  // ── Settings Handlers ────────────────────────────────────
  delayBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      delayBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      captureDelay = parseInt(btn.dataset.delay, 10);
    });
  });

  mirrorToggle?.addEventListener('change', (e) => {
    isMirrored = e.target.checked;
    applySettings();
  });

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.filter;
      applySettings();
    });
  });

  function applySettings() {
    if (!video) return;
    video.style.transform = isMirrored ? 'scaleX(-1)' : 'scaleX(1)';
    video.style.filter = currentFilter !== 'none' ? currentFilter : '';
  }

  // ── Countdown then capture ───────────────────────────────
  let isContinuousShooting = false;

  async function triggerCapture() {
    if (!stream || currentSlotIndex >= frameData.slots.length) return;
    if (isContinuousShooting) return; // Prevent double-trigger

    // If timer is set, run continuous shutter for all remaining slots
    if (captureDelay > 0) {
      isContinuousShooting = true;
      captureBtn.disabled = true;

      while (currentSlotIndex < frameData.slots.length) {
        // Countdown for this shot
        countdown.classList.add('active');
        for (let i = captureDelay; i > 0; i--) {
          countdown.textContent = i;
          await sleep(1000);
        }
        // Show "📸" briefly before shooting
        countdown.textContent = '📸';
        await sleep(300);
        countdown.classList.remove('active');

        // Flash + capture
        flashEl?.classList.add('active');
        captureSlot(currentSlotIndex);
        await sleep(150);
        flashEl?.classList.remove('active');

        // Advance slot
        currentSlotIndex++;
        if (currentSlotIndex >= frameData.slots.length) {
          currentSlotIndex = frameData.slots.length;
        }

        renderStrip();
        checkCompletion();

        // Stop if all slots done
        if (currentSlotIndex >= frameData.slots.length) break;

        // Brief pause between shots (feel natural)
        await sleep(400);
      }

      isContinuousShooting = false;
      captureBtn.disabled = false;
    } else {
      // No timer — single shot as usual
      captureBtn.disabled = true;

      // Flash + capture immediately
      flashEl?.classList.add('active');
      captureSlot(currentSlotIndex);
      await sleep(100);
      flashEl?.classList.remove('active');

      // Advance slot
      currentSlotIndex++;
      if (currentSlotIndex >= frameData.slots.length) {
        currentSlotIndex = frameData.slots.length;
      }

      renderStrip();
      checkCompletion();
      captureBtn.disabled = false;
    }
  }

  // ── Manual Upload & Drag Drop ────────────────────────────
  function handleImageUpload(file) {
    if (!file || !file.type.startsWith('image/')) {
      if(App && App.showToast) App.showToast('Hanya menerima file gambar', 'error');
      return;
    }
    
    if (currentSlotIndex >= frameData.slots.length) {
      if(App && App.showToast) App.showToast('Semua slot sudah penuh', 'info');
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        captureUploadedSlot(img, currentSlotIndex);
        currentSlotIndex++;
        if (currentSlotIndex >= frameData.slots.length) {
          currentSlotIndex = frameData.slots.length;
        }
        renderStrip();
        checkCompletion();
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }

  function setupUploadListeners() {
    if (uploadBtn) {
      uploadBtn.onclick = () => uploadInput.click();
      uploadBtn.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadBtn.style.background = 'rgba(75, 63, 160, 0.15)';
      });
      uploadBtn.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadBtn.style.background = 'rgba(75, 63, 160, 0.05)';
      });
      uploadBtn.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadBtn.style.background = 'rgba(75, 63, 160, 0.05)';
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
          handleImageUpload(e.dataTransfer.files[0]);
        }
      });
    }

    if (uploadInput) uploadInput.onchange = (e) => {
      if (e.target.files.length > 0) handleImageUpload(e.target.files[0]);
    };

    if (cameraCenter) {
      cameraCenter.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone?.classList.add('active');
      });
      cameraCenter.addEventListener('dragleave', (e) => {
        e.preventDefault();
        // Prevent flickering when dragging over children
        if (e.relatedTarget && !cameraCenter.contains(e.relatedTarget)) {
          dropzone?.classList.remove('active');
        }
      });
      cameraCenter.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone?.classList.remove('active');
        if (e.dataTransfer.files.length > 0) {
          handleImageUpload(e.dataTransfer.files[0]);
        }
      });
    }
  }

  // Process uploaded image (preserve full image, no crop, just resize if too large)
  function captureUploadedSlot(sourceImg, index) {
    const MAX_DIM = 2000;
    let w = sourceImg.width;
    let h = sourceImg.height;
    
    if (w > MAX_DIM || h > MAX_DIM) {
      const scale = Math.min(MAX_DIM / w, MAX_DIM / h);
      w *= scale;
      h *= scale;
    }

    const offscreen = document.createElement('canvas');
    offscreen.width = w;
    offscreen.height = h;
    const offCtx = offscreen.getContext('2d');
    
    offCtx.drawImage(sourceImg, 0, 0, w, h);
    
    const dataUrl = offscreen.toDataURL('image/jpeg', 0.95);
    const img = new Image();
    img.src = dataUrl;
    
    capturedPhotos[index] = { img: img, filter: currentFilter };
  }

  // ── Capture specific slot (No stretch logic, preserve full frame) ─────────────
  function captureSlot(index) {
    const cropW = video.videoWidth;
    const cropH = video.videoHeight;

    const offscreen = document.createElement('canvas');
    offscreen.width = cropW;
    offscreen.height = cropH;
    const offCtx = offscreen.getContext('2d');
    
    if (isMirrored) {
      offCtx.translate(cropW, 0);
      offCtx.scale(-1, 1);
    }
    
    offCtx.drawImage(video, 0, 0, cropW, cropH);
    
    const dataUrl = offscreen.toDataURL('image/jpeg', 0.95);
    const img = new Image();
    img.src = dataUrl;
    
    // Save raw image and current live filter string
    capturedPhotos[index] = { img: img, filter: currentFilter };
  }

  // ── Pass data to App state ───────────────────────────────
  function getPhotosData() {
    return {
      photos: capturedPhotos, // array of {img, filter}
      frame: frameData
    };
  }

  // ── Sidebar Strip UI ─────────────────────────────────────
  function renderStrip() {
    if (!stripContainer || !frameData) return;
    stripContainer.innerHTML = '';
    
    let takenCount = 0;
    
    frameData.slots.forEach((slot, i) => {
      const thumb = document.createElement('div');
      thumb.className = `captured-thumb ${i === currentSlotIndex ? 'active' : ''}`;
      thumb.dataset.index = i;
      
      if (capturedPhotos[i]) {
        takenCount++;
        const imgEl = document.createElement('img');
        imgEl.src = capturedPhotos[i].img.src;
        // Apply filter to thumbnail preview
        imgEl.style.filter = capturedPhotos[i].filter !== 'none' ? capturedPhotos[i].filter : '';
        
        // Open lightbox on click
        imgEl.style.cursor = 'pointer';
        imgEl.onclick = () => {
          if (App && App.openLightbox) App.openLightbox(capturedPhotos[i].img.src);
        };
        thumb.appendChild(imgEl);
        
        // Add delete button
        const delBtn = document.createElement('div');
        delBtn.innerHTML = '×';
        delBtn.style.cssText = 'position:absolute;top:4px;right:4px;background:#e53e3e;color:white;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;cursor:pointer;line-height:1;z-index:2;box-shadow:0 2px 6px rgba(229,62,62,0.5);transition:background 0.15s;';
        delBtn.title = 'Hapus foto ini';
        delBtn.onmouseenter = () => delBtn.style.background = '#c53030';
        delBtn.onmouseleave = () => delBtn.style.background = '#e53e3e';
        delBtn.onclick = (e) => {
          e.stopPropagation();
          deletePhoto(i);
        };
        thumb.appendChild(delBtn);
      } else {
        const placeholder = document.createElement('div');
        placeholder.style.cssText = 'width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:12px;font-weight:600;';
        placeholder.textContent = `${i + 1}`;
        thumb.appendChild(placeholder);
      }
      
      stripContainer.appendChild(thumb);
    });
    
    if (photoCounter) photoCounter.textContent = `${takenCount}/${frameData.slots.length}`;
  }

  function deletePhoto(index) {
    capturedPhotos[index] = null;
    // Auto find earliest empty slot
    currentSlotIndex = capturedPhotos.findIndex(p => p === null);
    if (currentSlotIndex === -1) currentSlotIndex = frameData.slots.length;
    renderStrip();
    checkCompletion();
  }

  function checkCompletion() {
    const isComplete = capturedPhotos.every(p => p !== null);
    if (nextBtn) {
      nextBtn.disabled = !isComplete;
      // also ensure it is visible, just in case
      nextBtn.style.display = 'block';
    }
    if (captureBtn) {
      captureBtn.style.display = isComplete ? 'none' : 'flex';
    }
  }

  // ── Error overlay ────────────────────────────────────────
  function showError(msg) {
    if (errOverlay) {
      errOverlay.querySelector('p').textContent = msg;
      errOverlay.classList.add('active');
    }
  }

  const sleep = ms => new Promise(r => setTimeout(r, ms));

  // ── Event Listeners ──────────────────────────────────────
  captureBtn?.addEventListener('click', () => triggerCapture());
  switchBtn?.addEventListener('click', async () => {
    if (videoDevices.length < 2) return;
    const currentIndex = videoDevices.findIndex(d => d.deviceId === currentDeviceId);
    const nextIndex = (currentIndex + 1) % videoDevices.length;
    currentDeviceId = videoDevices[nextIndex].deviceId;
    stop();
    await start();
  });
  retakeBtn?.addEventListener('click', () => {
    // Retake all
    capturedPhotos.fill(null);
    currentSlotIndex = 0;
    renderStrip();
    checkCompletion();
  });
  
  setupUploadListeners();
  
  return { start, stop, loadFrame, getPhotosData };
})();
