<?php
// api/admin/dashboard.php — Admin UI Dashboard
require_once dirname(__DIR__, 2) . '/config/helpers.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photopedia Admin - Submissions</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f3f4f6; margin: 0; padding: 20px; color: #111827; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { font-weight: 600; color: #4b5563; }
        .thumbnail { width: 60px; height: auto; border-radius: 4px; border: 1px solid #e5e7eb; }
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .btn-primary { background: #4f46e5; color: white; border: none; cursor: pointer; }
        .badge { padding: 4px 8px; border-radius: 999px; font-size: 12px; font-weight: 500; }
        .badge.pending { background: #fef3c7; color: #92400e; }
        .badge.approved { background: #d1fae5; color: #065f46; }
        .badge.rejected { background: #fee2e2; color: #991b1b; }
        
        /* Auth modal */
        #auth-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50;}
        .modal-content { background: white; padding: 24px; border-radius: 8px; width: 300px; text-align: center; }
        .input { width: 100%; padding: 8px; margin: 12px 0; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>

<div id="auth-modal" style="display: none;">
    <div class="modal-content">
        <h3>Admin Login</h3>
        <input type="password" id="secret-input" class="input" placeholder="Admin Secret">
        <button onclick="saveSecret()" class="btn btn-primary" style="width: 100%;">Masuk</button>
    </div>
</div>

<div class="container">
    <div class="header">
        <h1>📸 Photopedia Admin</h1>
        <div>
            <select id="status-filter" class="input" style="width:auto; display:inline-block;" onchange="loadSubmissions()">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="all">All</option>
            </select>
            <button onclick="logout()" class="btn" style="background:#e5e7eb; color:#374151; margin-left:8px;">Logout</button>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Frame</th>
                    <th>Judul & Tipe</th>
                    <th>Kreator</th>
                    <th>Status</th>
                    <th>Tgl Submit</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="table-body">
                <tr><td colspan="6" style="text-align:center;">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    const supabaseUrl = '<?= rtrim(env("SUPABASE_URL"), "/") ?>';
    
    function getSecret() { return localStorage.getItem('admin_secret'); }
    
    function checkAuth() {
        if (!getSecret()) {
            document.getElementById('auth-modal').style.display = 'flex';
            return false;
        }
        return true;
    }

    function saveSecret() {
        const val = document.getElementById('secret-input').value;
        if (val) {
            localStorage.setItem('admin_secret', val);
            document.getElementById('auth-modal').style.display = 'none';
            loadSubmissions();
        }
    }

    function logout() {
        localStorage.removeItem('admin_secret');
        location.reload();
    }

    async function loadSubmissions() {
        if (!checkAuth()) return;
        
        const status = document.getElementById('status-filter').value;
        const tbody = document.getElementById('table-body');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Memuat data...</td></tr>';
        
        try {
            const res = await fetch(`/api/admin/submissions?status=${status}`, {
                headers: { 'X-Admin-Secret': getSecret() }
            });
            
            if (res.status === 401) {
                alert("Admin secret salah!");
                logout();
                return;
            }
            
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Gagal memuat');
            
            if (data.submissions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Tidak ada data</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.submissions.map(sub => `
                <tr>
                    <td><img src="${supabaseUrl}/storage/v1/object/public/submissions/${sub.filename}" class="thumbnail" onerror="this.src='https://placehold.co/60x80'"></td>
                    <td>
                        <strong>${sub.frame_title}</strong><br>
                        <span style="color:#6b7280; font-size:12px;">${sub.strip_type}</span>
                    </td>
                    <td>
                        ${sub.submitter_name}<br>
                        <span style="color:#6b7280; font-size:12px;">${sub.submitter_email}</span>
                    </td>
                    <td><span class="badge ${sub.status}">${sub.status}</span></td>
                    <td>${new Date(sub.submitted_at).toLocaleDateString('id-ID')}</td>
                    <td>
                        <a href="/admin/review?id=${sub.id}" class="btn btn-primary" style="background:#4b5563;">Review</a>
                    </td>
                </tr>
            `).join('');
            
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:red;">Error: ${err.message}</td></tr>`;
        }
    }

    if (checkAuth()) {
        loadSubmissions();
    }
</script>
</body>
</html>
