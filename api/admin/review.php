<?php
// api/admin/review.php — Admin UI Review
require_once dirname(__DIR__, 2) . '/config/helpers.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Frame - Photopedia Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; margin: 0; padding: 20px; color: #111827; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 24px; display: flex; gap: 32px; }
        .preview-col { flex: 0 0 300px; }
        .info-col { flex: 1; }
        .preview-img { width: 100%; border-radius: 8px; border: 1px solid #e5e7eb; background: #f9fafb; }
        h1, h2 { margin-top: 0; }
        .detail-row { margin-bottom: 12px; }
        .label { color: #6b7280; font-size: 14px; margin-bottom: 4px; display: block; }
        .value { font-weight: 500; }
        .btn { padding: 10px 16px; border-radius: 6px; text-decoration: none; font-size: 15px; font-weight: 500; border: none; cursor: pointer; display: inline-block; }
        .btn-approve { background: #10b981; color: white; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-back { background: #e5e7eb; color: #374151; margin-bottom: 16px; }
        textarea { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-family: inherit; margin-bottom: 12px; box-sizing: border-box;}
        #reject-form { display: none; margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb; }
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 999px; font-size: 14px; font-weight: 500; margin-bottom: 24px; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

<div class="container">
    <a href="/admin" class="btn btn-back">← Kembali ke Dashboard</a>
    
    <div class="card" id="content">
        <div style="width: 100%; text-align: center;">Memuat...</div>
    </div>
</div>

<script>
    const supabaseUrl = '<?= rtrim(env("SUPABASE_URL"), "/") ?>';
    const secret = localStorage.getItem('admin_secret');
    
    if (!secret) {
        window.location.href = '/admin';
    }

    const urlParams = new URLSearchParams(window.location.search);
    const subId = urlParams.get('id');

    if (!subId) {
        document.getElementById('content').innerHTML = 'ID tidak ditemukan.';
    } else {
        loadData();
    }

    async function loadData() {
        try {
            // Kita bisa panggil supabase langsung atau fetch all then filter, tapi lebih aman lewat API jika ada endpoint specific GET by id. 
            // Karena belum ada, kita fetch /submissions?status=all lalu cari by id. (Atau idealnya buat GET by id)
            const res = await fetch(`/api/admin/submissions?status=all`, {
                headers: { 'X-Admin-Secret': secret }
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error);
            
            const sub = data.submissions.find(s => s.id === subId);
            if (!sub) throw new Error('Submission tidak ditemukan');

            render(sub);
        } catch (err) {
            document.getElementById('content').innerHTML = `<div style="color:red">Error: ${err.message}</div>`;
        }
    }

    function render(sub) {
        const imgUrl = `${supabaseUrl}/storage/v1/object/public/submissions/${sub.filename}`;
        
        let actionsHtml = '';
        if (sub.status === 'pending') {
            actionsHtml = `
                <div style="display: flex; gap: 12px; margin-top: 32px;">
                    <button onclick="approve()" id="btn-approve" class="btn btn-approve">✅ Approve & Publikasikan</button>
                    <button onclick="document.getElementById('reject-form').style.display='block'" class="btn btn-reject">❌ Reject</button>
                </div>
                
                <div id="reject-form">
                    <span class="label">Alasan Penolakan</span>
                    <textarea id="reject-reason" rows="3" placeholder="Misal: Resolusi terlalu rendah, desain melanggar aturan..."></textarea>
                    <button onclick="reject()" id="btn-submit-reject" class="btn btn-reject">Kirim Penolakan</button>
                    <button onclick="document.getElementById('reject-form').style.display='none'" class="btn btn-back" style="margin-bottom:0;">Batal</button>
                </div>
            `;
        } else {
            actionsHtml = `
                <div style="margin-top: 24px; padding: 16px; background: #f9fafb; border-radius: 6px;">
                    <span class="label">Status:</span>
                    <strong>${sub.status.toUpperCase()}</strong> pada ${new Date(sub.reviewed_at).toLocaleString('id-ID')}
                    ${sub.rejection_reason ? `<br><br><span class="label">Alasan:</span>${sub.rejection_reason}` : ''}
                </div>
            `;
        }

        const html = `
            <div class="preview-col">
                <img src="${imgUrl}" class="preview-img" alt="Preview">
                <div style="margin-top: 12px; text-align: center; color: #6b7280; font-size: 13px;">
                    Estimasi cetak: ${Math.round(sub.width / 118)} x ${Math.round(sub.height / 118)} cm<br>
                    (${sub.width} x ${sub.height} px)
                </div>
            </div>
            <div class="info-col">
                <div class="status-badge badge-${sub.status}">${sub.status.toUpperCase()}</div>
                <h2>${sub.frame_title}</h2>
                
                <div class="detail-row">
                    <span class="label">Kreator</span>
                    <span class="value">${sub.submitter_name} (${sub.submitter_email})</span>
                </div>
                
                <div class="detail-row">
                    <span class="label">Tipe Strip</span>
                    <span class="value">${sub.strip_type}</span>
                </div>
                
                <div class="detail-row">
                    <span class="label">Slot Foto (${sub.slots.length})</span>
                    <pre style="background:#f9fafb; padding:12px; border-radius:6px; font-size:12px; overflow:auto; max-height:150px;">${JSON.stringify(sub.slots, null, 2)}</pre>
                </div>

                ${actionsHtml}
            </div>
        `;
        document.getElementById('content').innerHTML = html;
    }

    async function approve() {
        if (!confirm('Approve frame ini? File akan dipindah ke bucket frames dan siap digunakan publik.')) return;
        
        const btn = document.getElementById('btn-approve');
        btn.disabled = true;
        btn.innerText = 'Processing...';

        try {
            const res = await fetch('/api/admin/approve', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Admin-Secret': secret
                },
                body: JSON.stringify({ id: subId })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error);
            
            alert(data.message);
            window.location.reload();
        } catch (err) {
            alert('Gagal: ' + err.message);
            btn.disabled = false;
            btn.innerText = '✅ Approve & Publikasikan';
        }
    }

    async function reject() {
        const reason = document.getElementById('reject-reason').value.trim();
        if (!reason) {
            alert('Alasan penolakan harus diisi');
            return;
        }

        const btn = document.getElementById('btn-submit-reject');
        btn.disabled = true;
        btn.innerText = 'Processing...';

        try {
            const res = await fetch('/api/admin/reject', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Admin-Secret': secret
                },
                body: JSON.stringify({ id: subId, reason: reason })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error);
            
            alert(data.message);
            window.location.reload();
        } catch (err) {
            alert('Gagal: ' + err.message);
            btn.disabled = false;
            btn.innerText = 'Kirim Penolakan';
        }
    }
</script>
</body>
</html>
