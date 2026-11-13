# Arah desain Kokoro Cakes

Sumber arah: proposal dan wireframe (hijau teh gelap, krem, pink lembut; pelanggan mobile-first, kasir desktop/tablet) dan permintaan pemilik: nuansa kafe Jepang yang terasa, bukan tempelan.

Design Read: aplikasi pesan dari meja untuk tamu kafe (ponsel) dan layar kasir, bahasa visual wa-kafe yang tenang. Dial ENERGY 2 / RHYTHM 2 / MOTION 2.

## Identitas

| Keputusan | Alasan (satu baris) |
|---|---|
| Warna inti: hijau matcha `#2F4A3A`, kertas kinari `#FAF5EA`, tinta sumi `#2A2925` | Dari proposal (hijau teh gelap + krem) dan warna dasar kafe teh Jepang. |
| Satu aksen: merah shu `#B83A26` | Warna cap/stempel Jepang; hanya untuk momen penting: stempel 売切, jumlah keranjang, tombol kirim pesanan, penanda menu aktif. |
| Pink sakura dari proposal hanya dipakai untuk status "Masuk/Terisi" | Agar pink punya arti (pesanan baru, meja terisi), bukan hiasan. |
| Judul dan nama menu: Shippori Mincho | Huruf mincho dipakai di papan menu dan noren kafe Jepang, dan memuat huruf kana/kanji untuk nama Jepang menu. |
| Teks isi: Zen Kaku Gothic New | Gothic Jepang yang huruf latinnya tetap mudah dibaca di ponsel dan serasi dengan kana. |
| Motif identitas: noren こころ digantung di atas foto beranda + stempel bulat merah untuk status nyata (売切 = habis) | Noren menandai "sudah masuk kafe": tamu melihat ke dalam lewat tirai pintu masuk; stempel hanya muncul jika ada keadaan yang ditandai. |
| Nama Jepang hanya pada isi yang memang punya nama Jepang (menu, kategori, judul お品書き) | Bahasa Jepang sebagai isi, bukan label hias di atas setiap judul. |
| Logo: ensō (lingkaran kuas zen) hijau matcha + stroberi berbentuk hati merah shu, dibuat atas permintaan pemilik | Ensō = hati/pikiran (arti *kokoro*), stroberi = kue (shortcake), bentuk hati = *kokoro*. Bentuk sederhana agar terbaca di 16–40 px. File: `public/assets/img/logo.svg`, `logo-light.svg` (latar gelap), `favicon.svg`; ekspor PNG di `docs/logo/`. |

## Komposisi

| Keputusan | Alasan |
|---|---|
| Beranda: teks rata kiri di samping foto bernoren (desktop) / di bawahnya (HP), bukan hero rata tengah; kalimat sesuai keadaan (sudah di meja / belum); pesanan aktif perangkat ini tampil langsung dengan statusnya; indeks kategori berbentuk daftar | Tugas beranda mengantar tamu ke menu dan ke pesanannya sendiri; tidak ada bagian template (langkah 1-2-3, rekomendasi palsu) atau kalimat janji yang tidak benar. |
| Foto beranda bisa digeser: suasana kafe dulu, lalu satu foto pajangan per kategori (`public/assets/img/beranda-{id kategori}.webp`, gaya sama dengan foto pertama; selama belum ada dipakai foto menu). Noren tetap di atas, label kecil nama kategori + jumlah menu + harga termurah menuju menu kategori itu, titik navigasi, selalu ganti otomatis tiap 5 detik tanpa tombol jeda (permintaan pemilik) | Beranda lebih menggoda dengan foto sungguhan yang bisa diklik. Ganti otomatis berhenti sementara saat kursor atau fokus keyboard ada di foto, tab tidak terlihat, atau foto di luar layar; bila "kurangi animasi" aktif foto tetap berganti tetapi langsung, tanpa efek geser. Tanpa JavaScript foto tetap bisa digeser. |
| Satu tombol keranjang per layar: tab Keranjang (HP) atau ikon header (desktop), angka disembunyikan saat 0 | Tombol keranjang di tiga tempat sekaligus hanya menambah keramaian; notifikasi setelah tambah sudah memuat tautan ke keranjang. |
| Halaman menu: kartu foto (pilihan pemilik); tanpa foto, kartu hanya teks | Tidak menampilkan gambar pengganti yang sama berulang-ulang. |
| Bayangan hanya pada elemen yang melayang (notifikasi keranjang, header lengket) | Kartu lain rata dengan border tipis agar halaman tidak "mengambang". |
| Radius: tombol & input 6px, kartu 10px, chip filter bulat penuh | Chip filter bulat membedakan "pilihan" dari "aksi". |
| Gerak halus dan pendek (0,12–0,35 detik): pindah halaman memudar (View Transitions) dengan header & tab bawah diam, foto memudar masuk, notifikasi keranjang meluncur naik, angka keranjang memantul, tombol mengecil saat ditekan, gulir halus. Tanpa animasi muncul-saat-scroll, parallax, atau layar loading | Dial MOTION 2: setiap gerak menjawab aksi tamu (pindah, menambah, menekan), bukan hiasan; semuanya mati bila "kurangi animasi" aktif. |
| Hanya tema terang | Kertas kinari adalah bagian identitas, dan halaman dipakai di kafe yang terang; tidak ada tombol tema. |
| Kasir: papan pesanan aktif sebagai fokus, ringkasan angka dalam satu baris kecil | Keputusan kasir adalah "pesanan mana yang diproses berikutnya". |

## Sentuhan untuk tamu

| Keputusan | Alasan |
|---|---|
| Deskripsi menu menggambarkan rasa dan tekstur, tanpa klaim bahan atau proses yang tidak bisa dibuktikan | Tamu memilih dari bayangan rasanya; klaim seperti "dibuat setiap pagi" hanya ditulis jika memang benar. |
| Tambah ke keranjang tanpa pindah halaman + notifikasi kecil di bawah (melayang, jadi berbayang) | Tamu tidak kehilangan posisi di daftar menu; notifikasi menyebut nama menu agar terasa ditanggapi. |
| Saran pasangan ditulis sendiri dengan alasan satu kalimat, maksimal dua di keranjang | Terasa seperti saran pelayan, bukan iklan acak; menu Habis tidak pernah disarankan. |
| Label "Sering dipesan" hanya dari pesanan Selesai (≥ 3 porsi, 30 hari), label teks hijau muda, bukan aksen merah | Bukti nyata, bukan klaim "best seller" palsu; merah shu tetap untuk momen penting. |
| Pilihan dapur hari ini (maks. 3) dipilih kasir, label hijau tua; kosong lagi tengah malam | Keputusan manusia dibedakan dari label data; batas 3 agar tetap terasa pilihan, dan reset harian agar tidak basi. |
| Indeks kategori di beranda diberi tiga foto kecil dari menu yang namanya disebut | Foto selalu cocok dengan teks di sebelahnya, jadi tamu melihat contoh nyata isi kategori. |
| Pesan お待たせしました saat siap diantar, ありがとうございました saat selesai, dengan romaji dan arti | Kalimat yang memang diucapkan pelayan kafe Jepang; romaji agar tamu bisa membacanya. |
| Tidak ada rating bintang, ulasan, angka pelanggan, popup promo, atau confetti | Tidak ada data aslinya, dan semuanya membuat halaman terasa palsu. |
