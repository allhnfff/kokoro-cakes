<?php
/**
 * DATA MASTER MENU DEMO (kategori + menu) untuk seeder.
 * Dibaca oleh scripts/seed-demo.php. Website membaca menu dari Redis, bukan dari file ini.
 *
 * Lima harga mengikuti contoh wireframe: Strawberry Cake 28.000, Matcha Cake 32.000,
 * Tiramisu 28.000, Iced Matcha Latte 24.000, Chocolate Latte 26.000. Harga lain adalah contoh.
 * Status hanya dua nilai sesuai prompt: Tersedia / Habis.
 *
 * Menambah/mengubah menu: ubah file ini lalu jalankan lagi php scripts/seed-demo.php.
 *   - Menu/kategori yang BELUM ada ditambahkan.
 *   - Menu yang sudah ada: teks (nama Jepang, deskripsi, pasangan) diperbarui dari file ini;
 *     harga dan status Tersedia/Habis TIDAK diubah (itu wewenang kasir/data berjalan).
 *
 * Foto: simpan di public/assets/img/menu/foto/{id_menu}.webp (atau .jpg/.png).
 * Jika belum ada, kartu menu tampil tanpa gambar. Prompt gambar: docs/PROMPT-GAMBAR-MENU.md
 */

return [
    'kategori' => [
        'k1' => ['nama_kategori' => 'Cake & Dessert', 'nama_jp' => 'ケーキ', 'urutan' => '1'],
        'k2' => ['nama_kategori' => 'Pastry', 'nama_jp' => 'パン', 'urutan' => '2'],
        'k3' => ['nama_kategori' => 'Drink', 'nama_jp' => 'お飲み物', 'urutan' => '3'],
        'k4' => ['nama_kategori' => 'Seasonal', 'nama_jp' => '季節限定', 'urutan' => '4'],
        'k5' => ['nama_kategori' => 'Wagashi', 'nama_jp' => '和菓子', 'urutan' => '5'],
    ],

    // [id_menu, id_kategori, nama_menu, nama_jp, harga, deskripsi, status]
    'menu' => [
        // ---- Cake & Dessert ----
        ['m1', 'k1', 'Strawberry Cake', '苺のショートケーキ', 28000, 'Bolu putih selembut kapas, krim kocok vanila yang tidak bikin enek, dan stroberi segar di setiap lapisnya.', 'Tersedia'],
        ['m2', 'k1', 'Matcha Cake', '抹茶ケーキ', 32000, 'Bolu matcha hijau tua dengan krim ringan. Pahit teh hijaunya terasa dulu, manisnya menyusul pelan.', 'Tersedia'],
        ['m3', 'k1', 'Tiramisu', 'ティラミス', 28000, 'Savoiardi yang direndam kopi, krim mascarpone yang dingin dan lembut, ditutup taburan kakao pahit.', 'Habis'],
        ['m4', 'k1', 'Chocolate Cake', 'チョコレートケーキ', 30000, 'Bolu cokelat yang lembap berlapis ganache pekat. Untuk yang ingin cokelatnya benar-benar terasa.', 'Tersedia'],
        ['m5', 'k1', 'Japanese Cheesecake', 'スフレチーズケーキ', 35000, 'Cheesecake soufflé yang bergoyang saat disendok, ringan seperti awan, gurih-manisnya pas dan tidak berat.', 'Tersedia'],
        ['m16', 'k1', 'Hokkaido Chiffon Cake', '北海道シフォン', 30000, 'Chiffon tinggi yang empuk dan berongga, wangi susu Hokkaido, dengan krim kocok tawar di sampingnya.', 'Tersedia'],
        ['m17', 'k1', 'Matcha Mille Crêpe', '抹茶ミルクレープ', 38000, 'Dua puluh lapis crêpe tipis berselang krim matcha. Potong pelan-pelan dan lihat lapisannya.', 'Tersedia'],
        ['m18', 'k1', 'Japanese Purin', 'プリン', 24000, 'Puding karamel ala kissaten yang padat dan kenyal, dengan karamel yang sedikit pahit di atasnya.', 'Tersedia'],
        ['m19', 'k1', 'Hojicha Pudding', 'ほうじ茶プリン', 26000, 'Puding lembut beraroma teh panggang yang hangat, disiram kuromitsu (sirup gula hitam).', 'Tersedia'],
        ['m20', 'k1', 'Yuzu Cheesecake', '柚子チーズケーキ', 36000, 'Cheesecake panggang yang padat dan creamy, dicerahkan aroma jeruk yuzu yang segar dan sedikit asam.', 'Tersedia'],

        // ---- Pastry ----
        ['m6', 'k2', 'Butter Croissant', 'バタークロワッサン', 22000, 'Kulitnya renyah berderai saat digigit, bagian dalamnya berlapis-lapis dan wangi mentega.', 'Tersedia'],
        ['m7', 'k2', 'Cream Puff', 'シュークリーム', 18000, 'Kulit choux yang ringan, penuh krim vanila dingin. Gigit pelan supaya krimnya tidak lari.', 'Tersedia'],
        ['m8', 'k2', 'Japanese Roll Cake', 'ロールケーキ', 25000, 'Bolu gulung yang lembut dan basah dengan krim susu yang tebal di tengahnya.', 'Tersedia'],
        ['m21', 'k2', 'Melon Pan', 'メロンパン', 18000, 'Roti manis yang empuk berbalut kulit kue kering renyah bermotif kotak-kotak.', 'Tersedia'],
        ['m22', 'k2', 'Matcha Melon Pan', '抹茶メロンパン', 20000, 'Melon pan berkulit renyah rasa matcha, dengan isian krim matcha di dalamnya.', 'Tersedia'],
        ['m23', 'k2', 'Anpan', 'あんパン', 16000, 'Roti lembut berisi pasta kacang merah yang manis legit, ditabur wijen hitam.', 'Tersedia'],
        ['m24', 'k2', 'Curry Pan', 'カレーパン', 22000, 'Roti goreng berbalut panko yang renyah, berisi kari Jepang yang gurih. Pilihan asin di antara yang manis.', 'Tersedia'],
        ['m25', 'k2', 'Matcha Croissant', '抹茶クロワッサン', 26000, 'Croissant mentega yang renyah dengan isian krim matcha dan glasir matcha di atasnya.', 'Tersedia'],

        // ---- Drink ----
        ['m9', 'k3', 'Iced Matcha Latte', 'アイス抹茶ラテ', 24000, 'Matcha yang dikocok lalu dituang ke susu dingin dan es. Segar, creamy, dengan pahit teh yang lembut.', 'Tersedia'],
        ['m10', 'k3', 'Chocolate Latte', 'チョコレートラテ', 26000, 'Cokelat pekat yang dilarutkan dalam susu. Kental dan hangat, enak untuk sore hari.', 'Tersedia'],
        ['m11', 'k3', 'Hojicha Latte', 'ほうじ茶ラテ', 26000, 'Teh hijau panggang dengan susu. Aromanya seperti kacang sangrai dan karamel, kafeinnya rendah.', 'Tersedia'],
        ['m12', 'k3', 'Strawberry Milk', 'いちごミルク', 22000, 'Susu dingin dengan saus stroberi yang masih ada potongan buahnya. Manis dan segar.', 'Tersedia'],
        ['m13', 'k3', 'Cafe Latte', 'カフェラテ', 24000, 'Espresso dengan susu yang dikukus halus. Teman yang aman untuk kue apa pun.', 'Tersedia'],
        ['m26', 'k3', 'Hot Genmaicha', '玄米茶', 18000, 'Teh hijau dengan beras panggang. Hangat, gurih, dan wangi seperti nasi yang baru disangrai.', 'Tersedia'],
        ['m27', 'k3', 'Sakura Latte', '桜ラテ', 28000, 'Susu beraroma bunga sakura, manis lembut dengan sedikit rasa asin, berwarna merah muda pucat.', 'Tersedia'],
        ['m28', 'k3', 'Yuzu Honey Soda', '柚子はちみつソーダ', 25000, 'Soda dingin dengan sirup yuzu dan madu. Asam segar di awal, manis madu di akhir.', 'Tersedia'],
        ['m29', 'k3', 'Usucha Matcha', '薄茶', 30000, 'Matcha upacara yang dikocok dengan chasen sampai berbusa halus, disajikan dalam chawan.', 'Tersedia'],
        ['m30', 'k3', 'Matcha Espresso Fusion', '抹茶エスプレッソ', 30000, 'Tiga lapis dalam satu gelas: susu, matcha, dan espresso. Aduk sendiri sebelum diminum.', 'Tersedia'],

        // ---- Seasonal ----
        ['m14', 'k4', 'Sakura Dessert', '桜デザート', 34000, 'Dessert musim semi dengan krim beraroma sakura dan mochi merah muda.', 'Habis'],
        ['m15', 'k4', 'Matcha Strawberry Special', '抹茶いちごスペシャル', 36000, 'Krim matcha, stroberi segar, dan bolu lembut berlapis dalam satu gelas. Pahit teh bertemu asam manis buah.', 'Tersedia'],
        ['m31', 'k4', 'Kakigori Ichigo Milk', 'いちごミルクかき氷', 32000, 'Es serut sehalus salju, disiram saus stroberi dan susu kental manis. Langsung meleleh di lidah.', 'Tersedia'],
        ['m32', 'k4', 'Kakigori Uji Kintoki', '宇治金時かき氷', 34000, 'Es serut dengan sirup matcha Uji, kacang merah manis, dan shiratama yang kenyal.', 'Tersedia'],
        ['m33', 'k4', 'Waguri Mont Blanc', '和栗モンブラン', 38000, 'Untaian krim kastanye Jepang di atas meringue renyah dan krim kocok. Manisnya dalam dan hangat.', 'Tersedia'],

        // ---- Wagashi ----
        ['m34', 'k5', 'Matcha Warabi Mochi', '抹茶わらび餅', 24000, 'Mochi dingin yang kenyal dan bergoyang, berbalut bubuk matcha dan kinako (kedelai sangrai).', 'Tersedia'],
        ['m35', 'k5', 'Ichigo Daifuku', 'いちご大福', 22000, 'Mochi tipis berisi pasta kacang merah dan satu stroberi utuh. Belah dua untuk melihat isinya.', 'Tersedia'],
        ['m36', 'k5', 'Dorayaki', 'どら焼き', 18000, 'Dua pancake madu yang empuk mengapit pasta kacang merah.', 'Tersedia'],
        ['m37', 'k5', 'Mitarashi Dango', 'みたらし団子', 20000, 'Tiga tusuk dango bakar yang kenyal, dilumuri saus kecap manis yang gurih dan sedikit gosong.', 'Tersedia'],
        ['m38', 'k5', 'Taiyaki Custard', 'たい焼き', 20000, 'Kue berbentuk ikan yang renyah di pinggir, berisi custard hangat sampai ke ekornya.', 'Tersedia'],
        ['m39', 'k5', 'Sakura Mochi', '桜餅', 22000, 'Mochi merah muda berisi kacang merah, dibungkus daun sakura asin. Daunnya boleh ikut dimakan.', 'Habis'],
    ],

    /*
     * Saran pasangan (kue ↔ minuman) seperti saran pelayan: id_menu => [id_menu pasangan, alasan singkat].
     * Ditampilkan di halaman detail menu dan di keranjang, hanya jika menu pasangannya sedang Tersedia.
     */
    'pasangan' => [
        'm1'  => ['m26', 'Teh yang gurih membuat krimnya terasa lebih ringan.'],
        'm2'  => ['m12', 'Manis stroberi melembutkan pahit matcha.'],
        'm3'  => ['m11', 'Aroma teh panggang serasi dengan kopi dan kakao.'],
        'm4'  => ['m13', 'Pahit kopi menahan manis cokelatnya.'],
        'm5'  => ['m28', 'Asam segar yuzu membuat cheesecake terasa makin ringan.'],
        'm16' => ['m27', 'Kue yang ringan tidak menutupi aroma sakura.'],
        'm17' => ['m26', 'Teh hangat membersihkan lidah di antara lapisan krim.'],
        'm18' => ['m13', 'Puding karamel dan kopi susu, pasangan khas kissaten.'],
        'm19' => ['m29', 'Dua rasa teh Jepang: satu panggang, satu segar.'],
        'm20' => ['m9', 'Matcha dingin menyegarkan setelah cheesecake yang padat.'],
        'm6'  => ['m13', 'Croissant mentega dan kopi susu, sarapan kafe yang sederhana.'],
        'm7'  => ['m11', 'Aroma teh panggang menyeimbangkan krim vanila.'],
        'm8'  => ['m29', 'Krim susu yang manis cocok dengan matcha yang pahit bersih.'],
        'm21' => ['m12', 'Roti manis dan susu dingin, camilan sore ala Jepang.'],
        'm22' => ['m11', 'Teh panggang yang hangat menemani rasa matcha.'],
        'm23' => ['m26', 'Teh gurih yang pas dengan kacang merah manis.'],
        'm24' => ['m28', 'Soda yuzu yang dingin meredakan hangatnya kari.'],
        'm25' => ['m13', 'Kopi susu menahan manis glasir matcha.'],
        'm9'  => ['m35', 'Mochi stroberi yang asam manis cocok dengan matcha susu.'],
        'm10' => ['m6', 'Celupkan ujung croissant ke cokelat hangat.'],
        'm11' => ['m36', 'Pancake madu dan teh panggang, sama-sama hangat.'],
        'm12' => ['m1', 'Untuk pencinta stroberi: dobel stroberinya.'],
        'm13' => ['m5', 'Cheesecake ringan yang tidak menutupi rasa kopinya.'],
        'm26' => ['m37', 'Teh beras panggang dan dango bakar, pasangan kedai teh.'],
        'm27' => ['m16', 'Chiffon yang ringan tidak menenggelamkan aroma sakura.'],
        'm28' => ['m24', 'Ingin yang asin? Kari pan dan soda dingin.'],
        'm29' => ['m34', 'Di upacara teh, matcha selalu ditemani wagashi.'],
        'm30' => ['m4', 'Cokelat pekat untuk minuman yang kuat.'],
        'm14' => ['m27', 'Satu tema musim semi, dari piring sampai gelas.'],
        'm15' => ['m26', 'Teh hangat yang gurih di sela lapisan krim.'],
        'm31' => ['m26', 'Teh hangat setelah es serut, supaya tidak kedinginan.'],
        'm32' => ['m11', 'Teh panggang hangat menyeimbangkan es yang dingin.'],
        'm33' => ['m13', 'Kopi susu dan kastanye, rasa yang hangat.'],
        'm34' => ['m29', 'Di upacara teh, wagashi disajikan untuk menemani matcha.'],
        'm35' => ['m29', 'Manis daifuku menyiapkan lidah untuk pahitnya matcha.'],
        'm36' => ['m11', 'Kacang merah dan teh panggang, hangat dan menenangkan.'],
        'm37' => ['m26', 'Dango bakar dan teh beras panggang, pasangan kedai teh.'],
        'm38' => ['m10', 'Custard hangat dan cokelat susu untuk sore hari.'],
        'm39' => ['m29', 'Daun sakura yang asin cocok dengan matcha yang pahit.'],
    ],
];
