// assets/js/app.js — SPA router, shared state, Supabase upload, QR, export
'use strict';

// ── Global State ──────────────────────────────────────────────
const App = (() => {
  const state = {
    currentPage:  'landing',
    selectedFrame: null,   // { id, label, url }
    currentPhoto:  null,   // kept for legacy fallback
    cameraData:    null,   // { photos: [{img, filter}], frame: {} }
    editedDataUrl: null,   // final edited photo
    uploadedUrl:   null,   // Supabase public URL after upload
    sessionId:     null,
  };

  // ── Keys & Credentials ───────────────────────────────────────
  const IMGBB_API_KEY = '71bcc799562d110a1c759a354b83f891';
  const EMAILJS_SERVICE_ID = 'service_jrtwg0r';
  const EMAILJS_TEMPLATE_ID = 'template_phhf1fc';
  const EMAILJS_PUBLIC_KEY = 'bECI-fTXCod1jZak3';

  // ── Page navigation ────────────────────────────────────────
  function navigate(pageId) {
    document.querySelectorAll('.page-section').forEach(s => {
      s.classList.toggle('active', s.id === `page-${pageId}`);
      if (s.id === `page-${pageId}`) s.classList.add('page-enter');
    });
    updateProgressSteps(pageId);
    state.currentPage = pageId;

    // Lifecycle hooks
    if (pageId === 'camera' && state.selectedFrame) {
      Camera.start();
      Camera.loadFrame(state.selectedFrame);
    }
    if (pageId === 'editor' && state.cameraData) {
      const editCanvas = document.getElementById('edit-canvas');
      if (editCanvas) Editor.init(editCanvas, state.cameraData);
    }
    if (pageId === 'export') {
      loadExportPreview();
    }
  }

  function updateProgressSteps(pageId) {
    const order = ['landing', 'frames', 'camera', 'editor', 'export', 'gallery'];
    const idx   = order.indexOf(pageId);
    document.querySelectorAll('.step').forEach((el, i) => {
      el.classList.remove('active', 'done');
      // Hide steps on gallery page
      if (pageId === 'gallery' || pageId === 'landing') {
        return;
      }
      if (i < idx)  el.classList.add('done');
      if (i === idx) el.classList.add('active');
    });
  }

  // ── Frame selection ────────────────────────────────────────
  function selectFrame(frame) {
    state.selectedFrame = frame;
    document.querySelectorAll('.frame-card').forEach(c => {
      c.classList.toggle('selected', c.dataset.frameId === frame.id);
    });
    document.getElementById('frame-next-btn')?.removeAttribute('disabled');
  }

  // ── Set current photo (from Camera) ───────────────────────
  function setCurrentPhoto(dataUrl) {
    state.currentPhoto = dataUrl;
  }

  // ── Upload to ImgBB ─────────────────────────────────────────
  async function uploadToImgBB(blob) {
    const formData = new FormData();
    formData.append('image', blob);

    const endpoint = `https://api.imgbb.com/1/upload?key=${IMGBB_API_KEY}`;
    const resp = await fetch(endpoint, {
      method: 'POST',
      body: formData,
    });

    const data = await resp.json();
    if (!resp.ok || !data.success) {
      throw new Error(data.error?.message ?? `Upload failed: ${resp.status}`);
    }

    // Return the direct display URL from ImgBB
    const publicUrl = data.data.display_url;
    state.uploadedUrl = publicUrl;
    state.sessionId = generateId(); // Just for local reference/QR
    return publicUrl;
  }

  // ── Load export preview ────────────────────────────────────
  async function loadExportPreview() {
    const previewImg = document.getElementById('export-preview-img');
    const dataUrl    = Editor.exportDataUrl();
    state.editedDataUrl = dataUrl;
    if (previewImg) previewImg.src = dataUrl;

    // Upload to ImgBB in background
    try {
      showToast('⬆️ Mengunggah foto…', 'info');
      const blob = await Editor.exportBlob();
      const url  = await uploadToImgBB(blob);
      showToast('✅ Foto berhasil diunggah!', 'success');
      generateQR(url);
      // Enable download button
      const dlBtn = document.getElementById('download-btn');
      if (dlBtn) { dlBtn.href = url; dlBtn.download = `photopedia-${state.sessionId}.jpg`; }
    } catch (err) {
      console.error('Upload error:', err);
      showToast('⚠️ Gagal upload, pakai download lokal saja.', 'error');
      // Fallback: use local dataUrl for download
      const dlBtn = document.getElementById('download-btn');
      if (dlBtn) {
        dlBtn.href     = dataUrl;
        dlBtn.download = `photopedia-${Date.now()}.jpg`;
      }
    }
  }

  // ── QR Code (qrcodejs CDN) ────────────────────────────────
  function generateQR(url) {
    const container = document.getElementById('qr-canvas');
    if (!container || !url || typeof QRCode === 'undefined') return;
    container.innerHTML = '';
    new QRCode(container, {
      text:          url,
      width:         180,
      height:        180,
      colorDark:     '#4B3FA0',
      colorLight:    '#FFFFFF',
      correctLevel:  QRCode.CorrectLevel.M,
    });
  }

  // ── Send email via EmailJS API ────────────────────────────
  async function sendEmail(emailAddress) {
    if (!state.uploadedUrl) throw new Error('Foto belum diunggah');

    const name = emailAddress.split('@')[0];
    const photoUrl = state.uploadedUrl;

    const emailHtml = `
      <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;margin-top:32px;margin-bottom:32px;box-shadow:0 4px 24px rgba(75,63,160,0.12);font-family:'Segoe UI',Arial,sans-serif;">
        <div style="background:linear-gradient(135deg,#4B3FA0 0%,#6B5FD0 100%);padding:40px 32px;text-align:center;">
          <h1 style="color:#ffffff;font-size:28px;margin:0;font-weight:700;letter-spacing:-0.5px;">📸 Photopedia</h1>
          <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:15px;">Foto kamu sudah siap, ${name}!</p>
        </div>
        <div style="padding:40px 32px;">
          <p style="color:#1E1B4B;font-size:16px;line-height:1.6;margin:0 0 24px;">
            Hei <strong>${name}</strong>! 🎉<br><br>
            Terima kasih sudah pakai <strong>Photopedia</strong>. Foto kamu sudah siap diunduh!
          </p>
          <div style="text-align:center;margin-bottom:32px;">
            <img src="${photoUrl}" alt="Foto Photopedia" style="max-width:100%;border-radius:12px;border:3px solid #EDE8F5;box-shadow:0 4px 16px rgba(75,63,160,0.15);" />
          </div>
          <div style="text-align:center;margin-bottom:32px;">
            <a href="${photoUrl}" target="_blank" 
               style="display:inline-block;background:linear-gradient(135deg,#4B3FA0,#6B5FD0);color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:50px;font-weight:600;font-size:15px;letter-spacing:0.3px;">
              ⬇️ Unduh Foto HD
            </a>
          </div>
        </div>
      </div>
    `;

    const payload = {
      service_id: EMAILJS_SERVICE_ID,
      template_id: EMAILJS_TEMPLATE_ID,
      user_id: EMAILJS_PUBLIC_KEY,
      template_params: {
        subject: "Photopedia - Your Photo",
        message_html: emailHtml,
        to_email: emailAddress,
        to_name: name
      }
    };

    const resp = await fetch('https://api.emailjs.com/api/v1.0/email/send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!resp.ok) {
      const errorText = await resp.text();
      throw new Error('Gagal kirim email: ' + errorText);
    }
    return true;
  }

  // ── Toast notifications ────────────────────────────────────
  function showToast(message, type = 'info', durationMs = 3500) {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const icons = { success: '✅', error: '❌', info: '💜' };
    const toast  = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${icons[type] || '•'}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = 'toast-out 0.3s ease forwards';
      setTimeout(() => toast.remove(), 300);
    }, durationMs);
  }

  // ── Utilities ──────────────────────────────────────────────
  function generateId() {
    return 'xxxx-xxxx-xxxx'.replace(/x/g, () => Math.floor(Math.random() * 16).toString(16));
  }
  function dateFolder() {
    const d = new Date();
    return `${d.getFullYear()}/${String(d.getMonth()+1).padStart(2,'0')}/${String(d.getDate()).padStart(2,'0')}`;
  }

  // ── Editor tab switching ───────────────────────────────────
  function initEditorTabs() {
    document.querySelectorAll('.editor-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        document.querySelectorAll('.editor-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.editor-panel-body').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(`tab-${target}`)?.classList.add('active');
      });
    });
  }

  // ── Frame picker init ──────────────────────────────────────
  let allFrames = [];

  function renderFrames() {
    const grid = document.querySelector('.frames-grid');
    if (!grid) return;
    
    const searchVal = document.getElementById('frame-search')?.value.toLowerCase() || '';
    const sortVal   = document.getElementById('frame-sort')?.value || 'alpha';
    
    let filtered = allFrames.filter(f => f.label.toLowerCase().includes(searchVal));
    
    if (sortVal === 'newest') {
      filtered.sort((a, b) => (b.mtime || 0) - (a.mtime || 0));
    } else {
      // Default alpha
      filtered.sort((a, b) => a.label.localeCompare(b.label));
    }
    
    if (filtered.length === 0) {
      grid.innerHTML = '<p style="color:var(--text-muted);grid-column:1/-1;text-align:center;padding:40px">Tidak ada bingkai yang cocok.</p>';
      return;
    }
    
    grid.innerHTML = '';
    filtered.forEach(frame => {
      const card = document.createElement('div');
      card.className = 'frame-card';
      if (state.selectedFrame && state.selectedFrame.id === frame.id) {
         card.classList.add('selected');
      }
      card.dataset.frameId = frame.id;
      card.innerHTML = `
        <div class="frame-thumb">
          <img src="${frame.url}" alt="${frame.label}" loading="lazy">
          <div class="frame-check">✓</div>
        </div>
        <div class="frame-info">
          <div class="frame-name">${frame.label}</div>
          <div class="frame-slots">${frame.slots.length} Foto</div>
        </div>`;
      card.addEventListener('click', () => selectFrame(frame));
      grid.appendChild(card);
    });
  }

  async function initFramePicker() {
    const grid = document.querySelector('.frames-grid');
    if (!grid) return;

    try {
      const resp   = await fetch('/api/frames.php?v=' + Date.now());
      const data   = await resp.json();
      allFrames    = data.frames ?? [];

      if (allFrames.length === 0) {
        grid.innerHTML = '<p style="color:var(--text-muted);grid-column:1/-1;text-align:center;padding:40px">Belum ada frame tersedia.</p>';
        return;
      }

      renderFrames();

      document.getElementById('frame-search')?.addEventListener('input', renderFrames);
      document.getElementById('frame-sort')?.addEventListener('change', renderFrames);

    } catch (err) {
      grid.innerHTML = '<p style="color:var(--error);grid-column:1/-1;text-align:center;padding:40px">Gagal memuat frame. Coba refresh.</p>';
      console.error('Frame load error:', err);
    }
  }

  // ── Gallery Fetching ───────────────────────────────────────
  async function initGallery() {
    const container = document.getElementById('gallery-container');
    if (!container) return;
    
    // Check if already loaded
    if (container.dataset.loaded === 'true') return;

    try {
      const resp = await fetch('/api/gallery.php');
      const data = await resp.json();
      const images = data.images ?? [];

      if (images.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted);grid-column:1/-1;text-align:center;padding:40px">Belum ada foto di galeri.</p>';
        return;
      }

      container.innerHTML = '';
      images.forEach(img => {
        const item = document.createElement('div');
        item.className = 'gallery-item';
        // Add a small thumbnail URL if using transformation, but for now we use the main URL
        item.innerHTML = `
          <div class="gallery-thumb">
            <img src="${img.url}" alt="Photopedia Gallery Image" loading="lazy">
          </div>
        `;
        item.addEventListener('click', () => openLightbox(img.url));
        container.appendChild(item);
      });
      container.dataset.loaded = 'true';
    } catch (err) {
      container.innerHTML = '<p style="color:var(--error);grid-column:1/-1;text-align:center;padding:40px">Gagal memuat galeri. Coba refresh.</p>';
      console.error('Gallery load error:', err);
    }
  }

  // ── Lightbox Logic ─────────────────────────────────────────
  function openLightbox(imageUrl) {
    const modal = document.getElementById('lightbox-modal');
    const img = document.getElementById('lightbox-img');
    if (!modal || !img) return;

    img.src = imageUrl;
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
  }

  function closeLightbox() {
    const modal = document.getElementById('lightbox-modal');
    const img = document.getElementById('lightbox-img');
    if (!modal) return;
    
    modal.classList.remove('active');
    setTimeout(() => {
      modal.style.display = 'none';
      if (img) img.src = '';
    }, 300); // Wait for transition
  }

  // Setup Lightbox Listeners
  document.querySelector('.lightbox-close')?.addEventListener('click', closeLightbox);
  document.getElementById('lightbox-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'lightbox-modal') closeLightbox();
  });

  // ── Email form ─────────────────────────────────────────────
  function initEmailForm() {
    const form      = document.getElementById('email-form');
    const emailInput = document.getElementById('email-input');
    const sendBtn   = document.getElementById('send-email-btn');

    form?.addEventListener('submit', async e => {
      e.preventDefault();
      const email = emailInput?.value.trim();
      if (!email) return;

      sendBtn.classList.add('loading');
      sendBtn.disabled = true;

      try {
        await sendEmail(email);
        showToast('✅ Email berhasil dikirim', 'success');
        form.reset();
      } catch (err) {
        showToast('❌ ' + err.message, 'error');
      } finally {
        sendBtn.classList.remove('loading');
        sendBtn.disabled = false;
      }
    });
  }

  // ── Navigation button wiring ───────────────────────────────
  function initNavButtons() {
    document.getElementById('start-btn')?.addEventListener('click', () => navigate('frames'));
    document.getElementById('frame-next-btn')?.addEventListener('click', () => navigate('camera'));
    document.getElementById('camera-next-btn')?.addEventListener('click', () => {
      Camera.stop();
      state.cameraData = Camera.getPhotosData();
      navigate('editor');
    });
    document.getElementById('editor-next-btn')?.addEventListener('click', () => navigate('export'));
    document.getElementById('back-to-frames')?.addEventListener('click', () => navigate('frames'));
    document.getElementById('back-to-camera')?.addEventListener('click', () => navigate('camera'));
    document.getElementById('back-to-editor')?.addEventListener('click', () => navigate('editor'));
    document.getElementById('restart-btn')?.addEventListener('click', () => {
      state.currentPhoto  = null;
      state.editedDataUrl = null;
      state.uploadedUrl   = null;
      state.sessionId     = null;
      navigate('landing');
    });
    
    // Gallery Navigation
    const goGallery = () => { navigate('gallery'); initGallery(); };
    document.getElementById('nav-gallery-btn')?.addEventListener('click', goGallery);
    document.getElementById('landing-gallery-btn')?.addEventListener('click', goGallery);
    document.getElementById('back-to-landing-btn')?.addEventListener('click', () => navigate('landing'));
  }

  // ── Bootstrap ──────────────────────────────────────────────
  function boot() {
    initNavButtons();
    initEditorTabs();
    initFramePicker();
    initEmailForm();
    navigate('landing');
  }

  document.addEventListener('DOMContentLoaded', boot);

  return { navigate, selectFrame, setCurrentPhoto, showToast, openLightbox, closeLightbox };
})();
