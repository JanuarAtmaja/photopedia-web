import docx
from docx.shared import Pt, RGBColor, Inches, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH

doc = docx.Document()

# Page margins (A4, sesuai standar akademik Indonesia)
section = doc.sections[0]
section.top_margin = Cm(3)
section.bottom_margin = Cm(3)
section.left_margin = Cm(4)
section.right_margin = Cm(3)

# ---- Helper functions ----
def set_font(run, name='Times New Roman', size=12, bold=False, italic=False, color=None):
    run.font.name = name
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic
    if color:
        run.font.color.rgb = RGBColor(*color)

def add_heading(doc, text, level=1, align=WD_ALIGN_PARAGRAPH.LEFT, size=None):
    p = doc.add_paragraph()
    p.alignment = align
    run = p.add_run(text)
    s = size if size else (14 if level == 1 else 12)
    set_font(run, size=s, bold=True)
    p.paragraph_format.space_before = Pt(12)
    p.paragraph_format.space_after = Pt(6)
    return p

def add_body(doc, text, align=WD_ALIGN_PARAGRAPH.JUSTIFY, indent=True):
    p = doc.add_paragraph()
    p.alignment = align
    run = p.add_run(text)
    set_font(run, size=12)
    if indent:
        p.paragraph_format.first_line_indent = Cm(1.25)
    p.paragraph_format.line_spacing = Pt(24)
    p.paragraph_format.space_after = Pt(6)
    return p

def add_step(doc, number, text):
    p = doc.add_paragraph(style='List Number')
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    run = p.add_run(text)
    set_font(run, size=12)
    p.paragraph_format.line_spacing = Pt(24)
    p.paragraph_format.space_after = Pt(4)
    return p

def add_bullet(doc, text):
    p = doc.add_paragraph(style='List Bullet')
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    run = p.add_run(text)
    set_font(run, size=12)
    p.paragraph_format.line_spacing = Pt(24)
    return p

def add_note(doc, text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    run = p.add_run('Catatan: ')
    set_font(run, size=12, bold=True, italic=True)
    run2 = p.add_run(text)
    set_font(run2, size=12, italic=True)
    p.paragraph_format.left_indent = Cm(1.25)
    p.paragraph_format.line_spacing = Pt(24)
    p.paragraph_format.space_after = Pt(8)
    return p

def page_break(doc):
    doc.add_page_break()

def add_sub_heading(doc, text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    run = p.add_run(text)
    set_font(run, size=12, bold=True)
    p.paragraph_format.space_before = Pt(8)
    p.paragraph_format.space_after = Pt(4)
    return p

# ============================================================
# COVER PAGE
# ============================================================
p_cover_title = doc.add_paragraph()
p_cover_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
run = p_cover_title.add_run('Buku Petunjuk Penggunaan Website (User Manual)')
set_font(run, size=14, bold=True)
p_cover_title.paragraph_format.space_after = Pt(24)

p_app_name = doc.add_paragraph()
p_app_name.alignment = WD_ALIGN_PARAGRAPH.CENTER
run2 = p_app_name.add_run('PHOTOPEDIA')
set_font(run2, size=22, bold=True)
p_app_name.paragraph_format.space_after = Pt(6)

p_tagline = doc.add_paragraph()
p_tagline.alignment = WD_ALIGN_PARAGRAPH.CENTER
run3 = p_tagline.add_run('Website Photobooth Digital untuk Generasi Z')
set_font(run3, size=14)
p_tagline.paragraph_format.space_after = Pt(60)

for _ in range(3):
    doc.add_paragraph()

p_by = doc.add_paragraph()
p_by.alignment = WD_ALIGN_PARAGRAPH.CENTER
run_by = p_by.add_run('Disusun oleh:')
set_font(run_by, size=12, bold=True)
p_by.paragraph_format.space_after = Pt(6)

doc.add_paragraph()

p_inst = doc.add_paragraph()
p_inst.alignment = WD_ALIGN_PARAGRAPH.CENTER
run_inst = p_inst.add_run('POLITEKNIK NEGERI MEDIA KREATIF\nJAKARTA\n2026')
set_font(run_inst, size=14, bold=True)

page_break(doc)

# ============================================================
# DAFTAR ISI
# ============================================================
p_toc_title = doc.add_paragraph()
p_toc_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
run_toc = p_toc_title.add_run('DAFTAR ISI')
set_font(run_toc, size=14, bold=True)
p_toc_title.paragraph_format.space_after = Pt(12)

toc_entries = [
    ('I.', 'PENDAHULUAN'),
    ('1.1', 'Deskripsi Website'),
    ('1.2', 'Tujuan Pembuatan Dokumen'),
    ('1.3', 'Pengguna Website'),
    ('II.', 'PERANGKAT YANG DIBUTUHKAN'),
    ('2.1', 'Perangkat Lunak'),
    ('2.2', 'Perangkat Keras'),
    ('III.', 'PETUNJUK PENGGUNAAN FITUR BERDASARKAN PENGGUNA'),
    ('3.1', 'Pengguna Umum (Tamu / Anonim)'),
    ('3.1.1', 'Mengakses Website Photopedia'),
    ('3.1.2', 'Halaman Beranda (Landing Page)'),
    ('3.1.3', 'Halaman Galeri'),
    ('3.2', 'Pengguna Aktif (Pengguna yang Berfoto)'),
    ('3.2.1', 'Langkah 1 - Memilih Bingkai (Frame)'),
    ('3.2.2', 'Langkah 2 - Mengambil Foto (Kamera)'),
    ('3.2.3', 'Langkah 3 - Mengedit Foto'),
    ('3.2.4', 'Langkah 4 - Mengekspor Foto'),
]

for num, title in toc_entries:
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
    is_bold = (num in ('I.', 'II.', 'III.') or len(num.split('.')) <= 2)
    run = p.add_run(f'{num}    {title}')
    set_font(run, size=12, bold=is_bold)
    p.paragraph_format.space_after = Pt(3)
    p.paragraph_format.line_spacing = Pt(24)

page_break(doc)

# ============================================================
# BAB I - PENDAHULUAN
# ============================================================
add_heading(doc, 'I.  PENDAHULUAN', level=1)

add_heading(doc, '1.1  Deskripsi Website', level=2)
add_body(doc, 'Photopedia adalah sebuah website photobooth digital yang dirancang khusus untuk kalangan Generasi Z Indonesia. Website ini memungkinkan pengguna untuk mengambil foto secara langsung melalui kamera perangkat (webcam atau kamera smartphone), memilih berbagai template bingkai (frame) yang menarik, mengedit hasil foto dengan filter, stiker emoji, dan teks, serta mengekspor hasil foto dalam berbagai cara seperti mengunduh langsung, berbagi melalui tautan (link), atau mengirimkan via email.')
add_body(doc, 'Photopedia dibangun menggunakan teknologi web modern berbasis PHP Native, HTML, CSS, dan JavaScript dengan dukungan WebRTC untuk akses kamera secara real-time. Penyimpanan foto dilakukan secara otomatis ke layanan cloud Supabase Storage, sehingga pengguna dapat mengakses kembali fotonya melalui tautan permanen. Website ini dapat diakses tanpa perlu mengunduh atau menginstal aplikasi apapun, cukup melalui browser modern seperti Google Chrome, Mozilla Firefox, atau Microsoft Edge.')
add_body(doc, 'Website Photopedia tersedia secara daring dan dapat diakses pada alamat URL berikut:')

p_url = doc.add_paragraph()
p_url.alignment = WD_ALIGN_PARAGRAPH.CENTER
run_url = p_url.add_run('https://photopedia.vercel.app')
set_font(run_url, size=12, bold=True)
p_url.paragraph_format.space_after = Pt(12)

add_heading(doc, '1.2  Tujuan Pembuatan Dokumen', level=2)
add_body(doc, 'Dokumen Buku Petunjuk Penggunaan Website (User Manual) Photopedia ini dibuat dengan tujuan sebagai berikut:')
add_step(doc, 1, 'Menggambarkan dan menjelaskan secara rinci cara penggunaan website Photopedia kepada seluruh pengguna.')
add_step(doc, 2, 'Menyediakan panduan langkah demi langkah (step-by-step) yang mudah dipahami bagi pengguna baru maupun pengguna yang sudah pernah menggunakan website Photopedia.')
add_step(doc, 3, 'Menjadi dokumen referensi resmi yang mendukung proses pendaftaran Hak Kekayaan Intelektual (HAKI) atas program komputer/website Photopedia.')
add_step(doc, 4, 'Membantu pengguna memahami seluruh fitur yang tersedia pada website Photopedia, termasuk fitur kamera, editor foto, galeri, dan ekspor foto.')

add_heading(doc, '1.3  Pengguna Website', level=2)
add_body(doc, 'Website Photopedia dirancang untuk dapat digunakan oleh berbagai kalangan tanpa memerlukan akun atau proses registrasi. Pengguna website Photopedia dibagi menjadi dua kategori sebagai berikut:')

add_bullet(doc, 'Pengguna Umum (Tamu / Anonim)')
p_u1 = doc.add_paragraph()
p_u1.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
run_u1 = p_u1.add_run('Pengguna umum adalah seluruh pengunjung yang membuka website Photopedia melalui browser. Pengguna umum dapat mengakses halaman beranda, melihat galeri foto yang telah dibuat oleh pengguna lain, serta memulai sesi berfoto tanpa perlu melakukan pendaftaran atau login terlebih dahulu.')
set_font(run_u1, size=12)
p_u1.paragraph_format.left_indent = Cm(1.25)
p_u1.paragraph_format.line_spacing = Pt(24)

add_bullet(doc, 'Pengguna Aktif (Pengguna yang Berfoto)')
p_u2 = doc.add_paragraph()
p_u2.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
run_u2 = p_u2.add_run('Pengguna aktif adalah pengguna yang memanfaatkan fitur utama website Photopedia secara penuh, mulai dari memilih bingkai foto, mengambil foto menggunakan kamera, mengedit hasil foto, hingga mengekspor dan membagikan foto. Tidak diperlukan akun khusus; pengguna langsung dapat menggunakan semua fitur yang tersedia.')
set_font(run_u2, size=12)
p_u2.paragraph_format.left_indent = Cm(1.25)
p_u2.paragraph_format.line_spacing = Pt(24)

page_break(doc)

# ============================================================
# BAB II - PERANGKAT YANG DIBUTUHKAN
# ============================================================
add_heading(doc, 'II.  PERANGKAT YANG DIBUTUHKAN', level=1)

add_heading(doc, '2.1  Perangkat Lunak', level=2)
add_body(doc, 'Perangkat lunak yang diperlukan untuk mengakses dan menggunakan website Photopedia adalah sebagai berikut:')
add_step(doc, 1, 'Sistem operasi: Windows 10/11, macOS, Android 8.0+, atau iOS 14+.')
add_step(doc, 2, 'Web browser modern yang mendukung WebRTC dan HTML5 Canvas, antara lain:')
add_bullet(doc, 'Google Chrome versi 90 atau lebih baru (direkomendasikan)')
add_bullet(doc, 'Mozilla Firefox versi 88 atau lebih baru')
add_bullet(doc, 'Microsoft Edge versi 90 atau lebih baru')
add_bullet(doc, 'Safari versi 14 atau lebih baru (untuk pengguna iOS/macOS)')
add_step(doc, 3, 'Koneksi internet aktif untuk mengakses website dan mengunggah hasil foto ke cloud.')

add_heading(doc, '2.2  Perangkat Keras', level=2)
add_body(doc, 'Perangkat keras yang diperlukan untuk menggunakan website Photopedia secara optimal adalah sebagai berikut:')
add_step(doc, 1, 'Komputer desktop, laptop, tablet, atau smartphone yang dilengkapi dengan kamera (webcam atau kamera built-in).')
add_step(doc, 2, 'Kamera/webcam dengan resolusi minimal 720p (HD) untuk hasil foto yang baik.')
add_step(doc, 3, 'Monitor atau layar dengan resolusi minimal 768 x 1024 piksel.')
add_step(doc, 4, 'Mouse atau touchpad sebagai perangkat antarmuka (opsional; dapat menggunakan layar sentuh pada perangkat mobile).')
add_step(doc, 5, 'Koneksi internet dengan kecepatan minimal 5 Mbps untuk pengalaman penggunaan yang lancar.')

add_note(doc, 'Fitur kamera (WebRTC) hanya dapat berfungsi apabila website diakses melalui protokol HTTPS atau melalui localhost. Jika browser menampilkan permintaan izin akses kamera, pastikan pengguna mengklik "Izinkan" (Allow) untuk mengaktifkan kamera.')

page_break(doc)

# ============================================================
# BAB III - PETUNJUK PENGGUNAAN FITUR
# ============================================================
add_heading(doc, 'III.  PETUNJUK PENGGUNAAN FITUR BERDASARKAN PENGGUNA', level=1)

# ---- 3.1 Pengguna Umum ----
add_heading(doc, '3.1  Pengguna Umum (Tamu / Anonim)', level=2)
add_body(doc, 'Bagian ini menjelaskan fitur-fitur yang dapat diakses oleh pengguna umum tanpa memerlukan akun atau login terlebih dahulu.')

add_heading(doc, '3.1.1  Mengakses Website Photopedia', level=2)
add_body(doc, 'Untuk memulai akses terhadap website Photopedia, ikuti langkah-langkah berikut:')
add_step(doc, 1, 'Buka aplikasi web browser (Google Chrome, Mozilla Firefox, atau lainnya) pada perangkat Anda.')
add_step(doc, 2, 'Ketikkan alamat URL berikut pada kolom address bar browser: https://photopedia.vercel.app')
add_step(doc, 3, 'Tekan tombol Enter pada keyboard atau klik tombol Go pada browser.')
add_step(doc, 4, 'Tunggu beberapa saat hingga halaman beranda (landing page) website Photopedia selesai dimuat.')
add_note(doc, 'Pastikan koneksi internet Anda aktif dan stabil. Jika halaman tidak dapat dimuat, coba refresh browser dengan menekan tombol F5 atau Ctrl+R.')

add_heading(doc, '3.1.2  Halaman Beranda (Landing Page)', level=2)
add_body(doc, 'Setelah berhasil mengakses website Photopedia, pengguna akan diarahkan ke halaman beranda. Halaman beranda menampilkan informasi utama tentang website Photopedia beserta navigasi untuk memulai sesi berfoto.')
add_body(doc, 'Pada halaman beranda, terdapat beberapa elemen utama yang dapat dilihat dan digunakan oleh pengguna:')

add_bullet(doc, 'Ticker berjalan di bagian atas halaman yang menampilkan informasi singkat seperti "Photopedia", "Photobooth Digital", "Frame Keren", dan lain-lain.')
add_bullet(doc, 'Header berisi logo Photopedia, progress bar langkah-langkah penggunaan (Mulai > Frame > Foto > Edit > Ekspor), dan tombol navigasi "Gallery".')
add_bullet(doc, 'Bagian Hero yang berisi judul "Abadikan Momen Paling Aesthetic", deskripsi singkat website, serta tombol-tombol utama: "Mulai Foto Sekarang", "Lihat Gallery", dan "Cara Pakai".')
add_bullet(doc, 'Feature Pills yang menampilkan fitur-fitur unggulan: Filter Instagram, Bingkai Keren, Stiker dan Teks, Kirim via Email, dan Share Link.')
add_bullet(doc, 'Bagian "Cara Pakai" yang menjelaskan 4 langkah mudah penggunaan website.')

add_body(doc, 'Untuk memulai sesi berfoto, pengguna cukup mengklik tombol "Mulai Foto Sekarang" pada bagian hero halaman beranda. Sistem secara otomatis akan mengarahkan pengguna ke halaman pemilihan bingkai (frame).')

add_heading(doc, '3.1.3  Halaman Galeri', level=2)
add_body(doc, 'Halaman Galeri menampilkan koleksi foto-foto yang telah dibuat dan diunggah oleh para pengguna website Photopedia sebelumnya. Pengguna umum dapat mengakses halaman galeri untuk melihat inspirasi foto sebelum mulai berfoto sendiri.')
add_body(doc, 'Untuk mengakses halaman galeri, ikuti langkah berikut:')
add_step(doc, 1, 'Dari halaman beranda, klik tombol "Lihat Gallery" atau klik tombol "Gallery" yang terdapat di bagian header website.')
add_step(doc, 2, 'Halaman galeri akan menampilkan grid foto-foto dari pengguna lain yang telah berhasil diunggah ke Photopedia.')
add_step(doc, 3, 'Klik pada thumbnail foto untuk melihat foto dalam ukuran yang lebih besar melalui tampilan lightbox (preview penuh).')
add_step(doc, 4, 'Pada setiap foto dalam galeri, tersedia dua tombol aksi:')
add_bullet(doc, 'Tombol Hapus/Sembunyikan: Digunakan untuk menyembunyikan foto dari tampilan galeri.')
add_bullet(doc, 'Tombol Email: Digunakan untuk mengirimkan foto tersebut ke alamat email tertentu.')
add_step(doc, 5, 'Untuk kembali ke halaman beranda, klik tombol "Kembali ke Beranda" yang terdapat di bagian bawah halaman galeri.')

page_break(doc)

# ---- 3.2 Pengguna Aktif ----
add_heading(doc, '3.2  Pengguna Aktif (Pengguna yang Berfoto)', level=2)
add_body(doc, 'Bagian ini menjelaskan alur penggunaan fitur utama website Photopedia secara lengkap, mulai dari pemilihan bingkai hingga ekspor foto. Terdapat 4 langkah utama yang harus dilakukan secara berurutan oleh pengguna aktif.')

# -- 3.2.1 Frame
add_heading(doc, '3.2.1  Langkah 1 - Memilih Bingkai (Frame)', level=2)
add_body(doc, 'Halaman pemilihan bingkai (frame) adalah langkah pertama dalam proses berfoto di Photopedia. Pada halaman ini, pengguna dapat memilih template bingkai foto yang akan digunakan sebagai overlay pada hasil foto.')
add_body(doc, 'Untuk memilih bingkai foto, ikuti langkah-langkah berikut:')
add_step(doc, 1, 'Dari halaman beranda, klik tombol "Mulai Foto Sekarang". Pengguna akan diarahkan ke halaman Pilih Bingkai.')
add_step(doc, 2, 'Pada halaman Pilih Bingkai, akan tampil daftar bingkai yang tersedia dalam bentuk grid kartu (card). Setiap kartu bingkai menampilkan: gambar preview bingkai, nama bingkai, dan jumlah slot foto yang tersedia.')
add_step(doc, 3, 'Gunakan fitur pencarian dengan mengetikkan nama bingkai pada kolom "Cari bingkai..." untuk menyaring pilihan.')
add_step(doc, 4, 'Gunakan dropdown "Abjad (A-Z)" atau "Terbaru" untuk mengurutkan tampilan bingkai sesuai preferensi.')
add_step(doc, 5, 'Klik pada kartu bingkai yang diinginkan. Bingkai yang dipilih akan ditandai dengan ikon centang berwarna ungu.')
add_step(doc, 6, 'Setelah memilih bingkai, tombol "Lanjut ke Kamera" di bagian bawah halaman akan aktif.')
add_step(doc, 7, 'Klik tombol "Lanjut ke Kamera" untuk melanjutkan ke langkah berikutnya.')
add_note(doc, 'Pengguna wajib memilih salah satu bingkai sebelum dapat melanjutkan ke langkah pengambilan foto. Tombol "Lanjut ke Kamera" tidak akan aktif selama belum ada bingkai yang dipilih.')

# -- 3.2.2 Kamera
add_heading(doc, '3.2.2  Langkah 2 - Mengambil Foto (Kamera)', level=2)
add_body(doc, 'Halaman kamera adalah langkah kedua dalam alur penggunaan Photopedia. Pada halaman ini, pengguna dapat mengaktifkan kamera perangkat dan mengambil foto yang akan mengisi slot-slot pada bingkai yang telah dipilih sebelumnya.')

add_sub_heading(doc, 'a.  Mengizinkan Akses Kamera')
add_step(doc, 1, 'Saat pertama kali membuka halaman kamera, browser akan menampilkan notifikasi permintaan izin akses kamera.')
add_step(doc, 2, 'Klik tombol "Izinkan" (Allow) pada notifikasi tersebut untuk mengaktifkan kamera.')
add_step(doc, 3, 'Gambar dari kamera akan tampil secara langsung (live preview) pada area layar kamera di tengah halaman.')
add_note(doc, 'Jika kamera tidak muncul, pastikan website diakses melalui HTTPS dan browser tidak dalam mode blokir kamera. Untuk mengubah izin kamera di Chrome: klik ikon kunci di address bar > Site Settings > Camera > Allow.')

add_sub_heading(doc, 'b.  Pengaturan Kamera')
add_body(doc, 'Sebelum mengambil foto, pengguna dapat menyesuaikan beberapa pengaturan kamera yang tersedia di panel kanan:', indent=False)
add_bullet(doc, 'Filter Kamera: Pilih filter live yang akan diterapkan pada tampilan kamera secara real-time. Filter yang tersedia antara lain: None (tanpa filter), Vintage (warna sepia hangat), Neon (warna jenuh cerah), dan B&W (hitam putih).')
add_bullet(doc, 'Timer: Atur jeda waktu sebelum foto diambil secara otomatis. Pilihan yang tersedia adalah Off (langsung), 3 detik, atau 5 detik.')
add_bullet(doc, 'Mirror Kamera: Aktifkan atau nonaktifkan tampilan cermin (mirror) pada kamera. Dalam mode mirror aktif, tampilan kamera akan dibalik secara horizontal seperti kamera depan smartphone.')
add_bullet(doc, 'Ganti Kamera: Jika perangkat memiliki lebih dari satu kamera (misalnya kamera depan dan belakang pada smartphone), gunakan tombol ganti kamera untuk berpindah antar kamera.')

add_sub_heading(doc, 'c.  Mengambil Foto dengan Kamera')
add_step(doc, 1, 'Posisikan diri atau objek di depan kamera sesuai keinginan.')
add_step(doc, 2, 'Klik tombol shutter (tombol kamera bulat besar) di bagian bawah tengah area kamera untuk mengambil foto.')
add_step(doc, 3, 'Jika timer diaktifkan, akan muncul hitungan mundur di layar. Setelah hitungan selesai, foto akan diambil secara otomatis.')
add_step(doc, 4, 'Setelah foto diambil, hasil foto akan muncul di panel antrean foto (kanan) dengan thumbnail kecil.')
add_step(doc, 5, 'Ulangi proses pengambilan foto untuk mengisi semua slot yang tersedia pada bingkai yang dipilih. Jumlah slot ditampilkan pada indikator foto di panel kanan.')
add_step(doc, 6, 'Untuk menghapus semua foto yang telah diambil dan memulai ulang, klik tombol "Hapus Semua".')

add_sub_heading(doc, 'd.  Upload Foto dari Galeri Perangkat')
add_body(doc, 'Selain mengambil foto langsung dari kamera, pengguna juga dapat mengunggah foto dari galeri perangkat (file lokal) sebagai alternatif:', indent=False)
add_step(doc, 1, 'Klik kotak "Upload / Drag and Drop" yang terdapat di bagian atas panel kanan.')
add_step(doc, 2, 'Pilih file foto dari perangkat Anda melalui dialog pemilihan file yang muncul.')
add_step(doc, 3, 'Foto yang dipilih akan secara otomatis mengisi slot foto yang tersedia.')
add_step(doc, 4, 'Alternatifnya, seret (drag) file foto dari File Explorer/Finder langsung ke area dropzone pada tampilan kamera.')
add_step(doc, 5, 'Setelah semua slot foto terisi, klik tombol "Lanjut Edit" di bagian bawah panel kanan untuk melanjutkan ke tahap editing.')

page_break(doc)

# -- 3.2.3 Editor
add_heading(doc, '3.2.3  Langkah 3 - Mengedit Foto', level=2)
add_body(doc, 'Halaman editor adalah langkah ketiga dalam alur penggunaan Photopedia. Pada halaman ini, pengguna dapat melakukan berbagai penyesuaian estetika pada foto yang telah diambil sebelumnya, termasuk memilih filter, mengatur posisi foto, menyesuaikan warna, serta menambahkan stiker emoji dan teks.')
add_body(doc, 'Tampilan halaman editor terbagi menjadi dua bagian utama:')
add_bullet(doc, 'Panel Kiri: Menampilkan canvas (kanvas) preview foto yang sedang diedit secara real-time.')
add_bullet(doc, 'Panel Kanan: Berisi berbagai opsi dan alat editing yang dapat digunakan.')

add_sub_heading(doc, 'a.  Filter Playground (Filter Foto)')
add_step(doc, 1, 'Pada panel kanan, temukan bagian "Filter Playground".')
add_step(doc, 2, 'Klik salah satu filter yang tersedia. Filter akan langsung diterapkan pada semua foto di kanvas.')
add_step(doc, 3, 'Filter yang tersedia antara lain: None, Vintage, Neon, B&W, dan lainnya sesuai yang tersedia.')
add_note(doc, 'Filter ini berbeda dengan filter kamera pada langkah sebelumnya. Filter pada editor diterapkan secara permanen pada hasil akhir foto yang akan diekspor.')

add_sub_heading(doc, 'b.  Atur Posisi Foto (Photo Transform)')
add_step(doc, 1, 'Klik pada area foto di kanvas untuk memilih slot foto yang ingin diatur.')
add_step(doc, 2, 'Gunakan slider "Zoom / Skala" untuk memperbesar atau memperkecil foto dalam slot. Nilai zoom berkisar dari 50% hingga 300%.')
add_step(doc, 3, 'Gunakan slider "Rotasi" untuk memutar foto. Nilai rotasi berkisar dari -180 derajat hingga 180 derajat.')
add_step(doc, 4, 'Klik tombol "Reset Foto Terpilih" untuk mengembalikan posisi foto ke pengaturan semula.')

add_sub_heading(doc, 'c.  Penyesuaian Warna (Color Adjustments)')
add_step(doc, 1, 'Klik foto di kanvas untuk memilih slot foto yang ingin disesuaikan.')
add_step(doc, 2, 'Gunakan slider "Kecerahan" untuk mengatur tingkat kecerahan foto.')
add_step(doc, 3, 'Gunakan slider "Kontras" untuk mengatur tingkat kontras foto.')
add_step(doc, 4, 'Gunakan slider "Saturasi" untuk mengatur tingkat kejenuhan warna foto.')
add_step(doc, 5, 'Gunakan slider "Rona (Hue)" untuk menggeser warna keseluruhan foto.')
add_step(doc, 6, 'Klik tombol "Reset Foto Terpilih" untuk mengembalikan semua pengaturan warna ke nilai awal.')

add_sub_heading(doc, 'd.  Stiker Corner (Stiker Emoji)')
add_step(doc, 1, 'Pada panel kanan, temukan bagian "Stiker Corner".')
add_step(doc, 2, 'Pilih tab "Emoji" untuk menampilkan koleksi emoji yang tersedia.')
add_step(doc, 3, 'Klik emoji yang diinginkan. Emoji akan secara otomatis ditambahkan ke kanvas foto.')
add_step(doc, 4, 'Geser (drag) emoji pada kanvas untuk memindahkan posisinya sesuai keinginan.')
add_step(doc, 5, 'Untuk menghapus emoji/stiker, klik pada stiker yang ingin dihapus hingga tombol "Hapus Sticker" muncul di pojok kanan atas kanvas, kemudian klik tombol tersebut.')

add_sub_heading(doc, 'e.  Menambahkan Teks')
add_step(doc, 1, 'Pada bagian "Stiker Corner", klik tab "Text" untuk beralih ke mode teks.')
add_step(doc, 2, 'Ketikkan teks yang diinginkan pada kolom input "Ketik teksmu di sini..." (maksimal 60 karakter).')
add_step(doc, 3, 'Atur ukuran teks menggunakan slider "Ukuran" yang tersedia.')
add_step(doc, 4, 'Pilih warna teks yang diinginkan dari pilihan warna yang tersedia.')
add_step(doc, 5, 'Klik tombol "Tambah Teks" untuk menambahkan teks ke kanvas foto.')
add_step(doc, 6, 'Geser teks pada kanvas untuk memindahkan posisinya.')
add_step(doc, 7, 'Setelah selesai mengedit, klik tombol "Selesai dan Ekspor" untuk melanjutkan ke langkah ekspor.')

page_break(doc)

# -- 3.2.4 Ekspor
add_heading(doc, '3.2.4  Langkah 4 - Mengekspor Foto', level=2)
add_body(doc, 'Halaman ekspor adalah langkah terakhir dalam alur penggunaan Photopedia. Pada halaman ini, pengguna dapat melihat hasil akhir foto dan melakukan berbagai aksi ekspor sesuai kebutuhan.')
add_body(doc, 'Setelah foto berhasil diproses dan diunggah ke cloud, tampilan akan memberikan notifikasi "Foto berhasil diunggah!" yang menandakan foto siap untuk dibagikan. Terdapat tiga cara untuk mengekspor foto:')

add_sub_heading(doc, 'a.  Berbagi via Tautan dan QR Code (Share Link)')
add_step(doc, 1, 'Pada halaman ekspor, temukan kartu "Share Link" di bagian kanan halaman.')
add_step(doc, 2, 'Setelah foto berhasil diunggah, QR Code akan muncul secara otomatis pada kartu tersebut.')
add_step(doc, 3, 'Tautan (link) foto juga akan tampil pada kolom di bawah QR Code.')
add_step(doc, 4, 'Salin tautan tersebut untuk dibagikan melalui pesan, media sosial, atau platform lainnya.')
add_step(doc, 5, 'Orang lain dapat memindai QR Code menggunakan aplikasi kamera smartphone untuk langsung membuka foto.')

add_sub_heading(doc, 'b.  Mengirim via Email')
add_step(doc, 1, 'Pada kartu "Kirim via Email", masukkan satu atau beberapa alamat email tujuan pada kolom input yang tersedia. Untuk mengirim ke beberapa alamat sekaligus, pisahkan dengan tanda koma (contoh: email1@contoh.com, email2@contoh.com).')
add_step(doc, 2, 'Klik tombol "Kirim Email" untuk memulai pengiriman.')
add_step(doc, 3, 'Tunggu beberapa saat hingga muncul notifikasi "Email berhasil dikirim" yang menandakan foto telah terkirim.')
add_step(doc, 4, 'Penerima email akan mendapatkan pesan berisi tautan untuk mengunduh foto.')
add_note(doc, 'Pastikan alamat email yang dimasukkan sudah benar sebelum mengklik tombol kirim. Sistem tidak akan menampilkan peringatan jika alamat email salah format.')

add_sub_heading(doc, 'c.  Mengunduh Foto')
add_step(doc, 1, 'Pada kartu "Download Foto", klik tombol "Download JPG".')
add_step(doc, 2, 'Browser akan secara otomatis mengunduh file foto dalam format JPEG ke folder unduhan (Downloads) perangkat.')
add_step(doc, 3, 'File foto akan tersimpan dengan nama yang mencantumkan ID sesi pengguna.')

add_sub_heading(doc, 'd.  Mengakhiri Sesi atau Mulai Foto Baru')
add_body(doc, 'Setelah selesai mengekspor foto, pengguna memiliki beberapa pilihan untuk melanjutkan:', indent=False)
add_bullet(doc, 'Klik tombol "Edit Lagi" untuk kembali ke halaman editor dan melakukan perubahan pada foto.')
add_bullet(doc, 'Klik tombol "Foto Baru" untuk memulai sesi berfoto baru dari awal. Semua data sesi sebelumnya akan dihapus.')
add_bullet(doc, 'Klik tombol "Gallery" di header untuk melihat galeri foto publik Photopedia.')

# Save
doc.save(r'c:\laragon\www\photopedia\User_Manual_Photopedia.docx')
print('SELESAI: User Manual berhasil dibuat!')
print('File tersimpan di: c:\\laragon\\www\\photopedia\\User_Manual_Photopedia.docx')
