
# RULE DESAINER UI/UX
## Learning Management System (LMS)

Dokumen ini berisi aturan dan standar penulisan kode untuk divisi Desainer UI/UX pada proyek LMS.

Tujuan utama:
- Menjaga kualitas dan kerapian kode frontend.
- Membuat kode mudah dibaca dan dipahami oleh seluruh anggota tim.
- Menjaga konsistensi struktur HTML, CSS, dan JavaScript.
- Memudahkan pemeliharaan dan pengembangan fitur di masa mendatang.

---

## 1. RUANG LINGKUP PEKERJAAN

Desainer UI/UX bertanggung jawab atas:

1. Struktur halaman HTML.
2. Tampilan dan layout menggunakan CSS.
3. Interaksi pengguna menggunakan JavaScript.
4. Responsivitas tampilan.
5. Konsistensi desain antarmuka.
6. Kerapian dan keterbacaan kode frontend.

Desainer tidak bertanggung jawab atas:
- Query SQL.
- Struktur database.
- Logika backend PHP.
- Perubahan file backend tanpa koordinasi.
- Perubahan folder database.

---

## 2. STANDAR UMUM PENULISAN KODE

### 2.1 Keterbacaan

Kode harus ditulis agar dapat dipahami oleh anggota tim lain tanpa penjelasan tambahan yang berlebihan.

Ketentuan:
- Gunakan indentasi 4 spasi.
- Gunakan nama variabel, fungsi, dan class yang jelas.
- Hindari penamaan yang tidak menjelaskan tujuan kode.
- Hindari kode yang terlalu panjang dan sulit dipahami.
- Pisahkan kode berdasarkan tanggung jawabnya.

Contoh yang benar:

```javascript
const studentName = "Mahfudzz";
const classList = [];
```

Contoh yang tidak dianjurkan:

```javascript
const x = "Mahfudzz";
const a = [];
```

### 2.2 Konsistensi

Seluruh kode frontend harus mengikuti pola penulisan yang konsisten.

Ketentuan:
- Gunakan gaya indentasi yang sama.
- Gunakan pola penamaan yang sama.
- Gunakan format penulisan fungsi yang konsisten.
- Jangan mencampur berbagai gaya penulisan tanpa alasan.
- Ikuti struktur folder yang telah disepakati.

---

## 3. STANDAR PENULISAN HTML

### 3.1 Struktur HTML

Gunakan HTML5 semantik jika sesuai dengan kebutuhan halaman.

Contoh:

```html
<main class="dashboard">
    <section class="class-list">
        <h1 class="class-list__title">
            Daftar Kelas
        </h1>

        <div class="class-list__items">
            <!-- Konten daftar kelas -->
        </div>
    </section>
</main>
```

Ketentuan:
- Gunakan tag HTML sesuai fungsinya.
- Gunakan huruf kecil untuk nama tag dan atribut.
- Gunakan indentasi yang konsisten.
- Pastikan elemen HTML ditutup dengan benar.
- Hindari struktur HTML yang tidak diperlukan.
- Gunakan atribut `alt` pada gambar yang bermakna.
- Gunakan elemen heading secara berurutan dan sesuai struktur halaman.

### 3.2 Penamaan Class dan ID

Gunakan nama yang menjelaskan fungsi elemen.

Dianjurkan:

```html
<div class="class-card">
    <h2 class="class-card__title">
        Pemrograman Web
    </h2>
</div>
```

Tidak dianjurkan:

```html
<div class="kotak1">
    <h2 class="biru">
        Pemrograman Web
    </h2>
</div>
```

Ketentuan:
- Gunakan huruf kecil.
- Gunakan tanda hubung (-) untuk nama class.
- Gunakan nama class berdasarkan fungsi atau komponen.
- Hindari nama berdasarkan warna atau posisi visual.
- Gunakan ID hanya untuk identitas unik atau kebutuhan JavaScript tertentu.
- Jangan menggunakan ID yang sama lebih dari satu kali dalam satu halaman.

### 3.3 Larangan Inline Code

Hindari penulisan CSS dan JavaScript langsung di dalam HTML.

Tidak dianjurkan:

```html
<button style="color: red;" onclick="hapusKelas()">
    Hapus
</button>
```

Dianjurkan:

```html
<button class="button button--danger" id="deleteClassButton">
    Hapus
</button>
```

CSS dan JavaScript ditulis pada file terpisah.

---

## 4. STANDAR PENULISAN CSS

### 4.1 Struktur CSS

CSS harus ditulis secara teratur dan mudah ditemukan.

Ketentuan:
- Kelompokkan aturan berdasarkan komponen.
- Gunakan indentasi 4 spasi.
- Gunakan nama class yang deskriptif.
- Hindari selector yang terlalu umum.
- Hindari duplikasi aturan CSS yang tidak diperlukan.
- Hapus aturan CSS yang sudah tidak digunakan.
- Gunakan komentar untuk memisahkan bagian besar jika diperlukan.

Contoh:

```css
.class-card {
    padding: 16px;
    border-radius: 8px;
    background-color: white;
}

.class-card__title {
    font-size: 20px;
    font-weight: 600;
}

.class-card__button {
    padding: 8px 16px;
}
```

### 4.2 Penamaan Class CSS

Gunakan pola penamaan BEM jika sesuai dengan kebutuhan proyek.

Format:

```text
block
block__element
block--modifier
```

Contoh:

```css
.class-card {
    padding: 16px;
}

.class-card__title {
    font-size: 20px;
}

.class-card--featured {
    border: 2px solid black;
}
```

Ketentuan:
- Block adalah komponen utama.
- Element adalah bagian dari block.
- Modifier adalah variasi dari block atau element.
- Jangan membuat nama class yang membingungkan.
- Hindari penggunaan nama class yang terlalu umum seperti `.container` jika berpotensi bertabrakan dengan komponen lain.

### 4.3 CSS Variable

Gunakan CSS variable untuk nilai desain yang digunakan berulang kali.

Contoh:

```css
:root {
    --color-primary: #2563eb;
    --color-background: #f8fafc;
    --color-text: #1e293b;
    --spacing-md: 16px;
    --radius-md: 8px;
}

.button {
    padding: var(--spacing-md);
    border-radius: var(--radius-md);
    background-color: var(--color-primary);
    color: white;
}
```

Tujuannya agar perubahan warna, jarak, dan ukuran dapat dilakukan secara konsisten.

### 4.4 Responsivitas

Setiap halaman harus dapat digunakan pada berbagai ukuran layar.

Ketentuan:
- Tampilan harus dapat menyesuaikan desktop dan mobile.
- Hindari layout yang menyebabkan horizontal scrolling tanpa alasan.
- Gunakan media query jika diperlukan.
- Pastikan teks dan tombol tetap terbaca.
- Pastikan komponen tidak saling bertumpuk.
- Uji tampilan pada ukuran layar yang berbeda.

---

## 5. STANDAR PENULISAN JAVASCRIPT

### 5.1 Penamaan Variabel dan Fungsi

Gunakan camelCase untuk variabel dan fungsi.

Contoh:

```javascript
const className = "Pemrograman Web";

function showClassList() {
    // Menampilkan daftar kelas
}
```

Ketentuan:
- Gunakan `const` jika nilai tidak perlu diubah.
- Gunakan `let` jika nilai perlu diubah.
- Hindari penggunaan `var` untuk kode baru.
- Gunakan nama yang menjelaskan tujuan variabel atau fungsi.
- Hindari nama satu huruf kecuali untuk kebutuhan loop yang sederhana.

### 5.2 Pemisahan Tanggung Jawab

Setiap fungsi sebaiknya memiliki satu tanggung jawab utama.

Tidak dianjurkan:

```javascript
function processClass() {
    // Mengambil data API
    // Mengolah data
    // Membuat HTML
    // Mengatur seluruh tampilan
    // Menangani semua error
}
```

Dianjurkan:

```javascript
async function loadClasses() {
    // Mengambil data dari API
}

function renderClasses(classList) {
    // Menampilkan data kelas
}

function showError(message) {
    // Menampilkan pesan error
}
```

### 5.3 Penanganan Error

Operasi yang dapat gagal harus memiliki penanganan error yang sesuai.

Contoh:

```javascript
async function loadClasses() {
    try {
        const response = await fetch(
            "../backend/api/kelas/list.php"
        );

        const result = await response.json();

        if (!result.success) {
            showError("Gagal memuat kelas.");
            return;
        }

        renderClasses(result.data);
    } catch (error) {
        console.error(error);
        showError("Terjadi kesalahan koneksi.");
    }
}
```

Ketentuan:
- Jangan membiarkan error tanpa penanganan.
- Tampilkan pesan yang mudah dipahami pengguna.
- Gunakan `console.error()` untuk membantu proses debugging.
- Jangan menampilkan informasi sensitif kepada pengguna.

### 5.4 Kode Duplikat

Hindari menulis kode yang sama berulang kali.

Jika suatu logika digunakan beberapa kali, pertimbangkan untuk membuat fungsi yang dapat digunakan kembali.

Contoh:

```javascript
function showMessage(message) {
    const messageElement = document.querySelector(
        ".message"
    );

    messageElement.textContent = message;
}
```

---

## 6. PEMISAHAN FILE

Kode frontend harus dipisahkan berdasarkan jenis dan tanggung jawabnya.

Struktur yang dianjurkan:

```text
frontend/
├── pages/
│   └── siswa/
│       └── kelas.html
│
├── assets/
│   ├── css/
│   │   └── kelas.css
│   │
│   └── js/
│       └── kelas.js
│
└── config/
    └── api.js
```

Ketentuan:
- HTML berisi struktur halaman.
- CSS berisi tampilan.
- JavaScript berisi interaksi dan logika frontend.
- Jangan mencampur seluruh kode dalam satu file jika dapat dipisahkan.
- Gunakan nama file yang menjelaskan fungsinya.
- Jangan membuat file duplikat dengan nama yang membingungkan.

---

## 7. STANDAR KOMENTAR

Komentar digunakan untuk menjelaskan alasan, tujuan, atau logika yang tidak langsung terlihat.

Tidak dianjurkan:

```javascript
// Menambahkan 1 ke i
i++;
```

Dianjurkan:

```javascript
// Berpindah ke halaman berikutnya setelah data selesai diproses.
currentPage++;
```

Ketentuan:
- Gunakan komentar yang singkat dan relevan.
- Jangan memberikan komentar pada setiap baris kode jika tidak diperlukan.
- Hapus komentar yang sudah tidak sesuai dengan kode.
- Jangan menggunakan komentar untuk menyembunyikan kode yang tidak digunakan.

---

## 8. KERAPIAN DAN PEMELIHARAAN KODE

Setiap anggota divisi wajib menjaga kode agar mudah dipelihara.

Ketentuan:
- Jangan meninggalkan kode yang tidak digunakan.
- Jangan meninggalkan file percobaan tanpa alasan.
- Jangan menyalin kode dari halaman lain tanpa menyesuaikannya.
- Jangan menggunakan solusi yang rumit jika solusi sederhana sudah cukup.
- Jangan mengubah kode anggota lain tanpa memahami dampaknya.
- Jika melakukan perubahan besar, komunikasikan kepada anggota yang terkait.
- Pastikan perubahan tidak merusak fitur yang sudah ada.

---

## 9. STANDAR AKSESIBILITAS

Tampilan harus dapat digunakan oleh pengguna dengan kebutuhan yang beragam.

Ketentuan:
- Gunakan teks yang jelas pada tombol.
- Pastikan kontras teks dan background cukup.
- Jangan mengandalkan warna saja untuk menyampaikan informasi.
- Gunakan label pada input form.
- Pastikan elemen interaktif dapat digunakan dengan keyboard jika memungkinkan.
- Gunakan atribut ARIA jika memang diperlukan dan sesuai.

Contoh:

```html
<label for="className">
    Nama Kelas
</label>

<input
    type="text"
    id="className"
    name="className"
    placeholder="Masukkan nama kelas"
>
```

---

## 10. CHECKLIST SEBELUM KODE DIANGGAP SELESAI

Sebelum mengirimkan hasil pekerjaan, desainer wajib memeriksa:

- [ ] HTML memiliki struktur yang valid dan rapi.
- [ ] Indentasi kode konsisten.
- [ ] Nama file, class, ID, variabel, dan fungsi jelas.
- [ ] CSS tidak memiliki aturan yang tidak diperlukan.
- [ ] JavaScript tidak memiliki error yang belum ditangani.
- [ ] Tidak ada kode duplikat yang tidak perlu.
- [ ] Tampilan responsif pada desktop dan mobile.
- [ ] Tombol dan form memiliki perilaku yang sesuai.
- [ ] Tidak ada inline CSS atau inline JavaScript tanpa alasan.
- [ ] Tidak ada data sensitif yang ditulis secara sembarangan di frontend.
- [ ] Perubahan tidak merusak fitur yang sudah ada.
- [ ] Kode dapat dipahami oleh anggota tim lain.

---

## 11. ATURAN PENUTUP

Setiap kode yang dibuat oleh divisi Desainer UI/UX harus mengutamakan:

1. Keterbacaan.
2. Konsistensi.
3. Kerapian.
4. Pemisahan tanggung jawab.
5. Responsivitas.
6. Kemudahan pemeliharaan.
7. Keamanan dan aksesibilitas.

Kode yang berfungsi bukan berarti kode sudah berkualitas. Setiap anggota wajib berusaha menghasilkan kode yang dapat dipahami, dirawat, dan dikembangkan oleh anggota tim lainnya.
