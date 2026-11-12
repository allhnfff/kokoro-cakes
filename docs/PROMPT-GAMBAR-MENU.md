# Prompt gambar menu Kokoro Cakes

Dokumen ini berisi prompt untuk membuat foto 39 menu Kokoro Cakes dengan ChatGPT (atau generator gambar lain). Semua prompt memakai gaya yang sama supaya fotonya terlihat satu set.

## Cara pakai

1. Buka ChatGPT dan mulai chat baru khusus gambar menu.
2. Kirim **Prompt gaya dasar** di bawah lebih dulu. Ini membuat ChatGPT mengingat gayanya.
3. Kirim prompt menu satu per satu (satu gambar per pesan). Setiap prompt sudah lengkap, jadi tetap bisa dipakai walaupun langkah 2 dilewati.
4. Unduh gambarnya, lalu ganti namanya sesuai **kode menu**, misalnya `m1.jpg`, `m36.jpg`. Format `.jpg`, `.png`, atau `.webp` semuanya bisa.
5. Simpan ke folder `public/assets/img/menu/foto/`.
6. Muat ulang website. Foto langsung dipakai di kartu menu, halaman detail, keranjang, dan halaman kasir. Menu yang belum punya foto tampil sebagai kartu teks.

Tips:
- Ukuran persegi 1024×1024. Kecilkan ke sekitar 200–300 KB (misalnya lewat squoosh.app) agar halaman cepat dibuka di ponsel.
- Jika hasilnya ada tulisan, logo, atau tangan, minta ulang dengan: *"same image, remove all text and hands"*.
- Jika warna antarfoto mulai berbeda, kirim lagi prompt gaya dasar.
- Halaman detail menu menulis keterangan "Foto adalah ilustrasi penyajian", karena gambar hasil AI bukan foto produk asli.

## Prompt gaya dasar

```text
You are creating a consistent set of menu photos for "Kokoro Cakes", a cozy Japanese cake and tea house (wa-cafe).
For every image I request, use this exact style:
- Square 1:1 image, 1024x1024, food photography, photorealistic.
- Camera at about 45 degrees, the dish centered and filling about 60% of the frame.
- Served on handmade Japanese ceramics (wabi-sabi stoneware, matte glaze in cream, celadon, or charcoal) on a light hinoki wood table.
- Background: soft cream washi paper wall, slightly blurred, with a hint of a matcha-green noren curtain far behind.
- Soft natural morning window light from the left, gentle shadows, shallow depth of field.
- Color palette: matcha green, sakura pink, cream, warm wood, small touches of vermilion red.
- Props kept minimal: at most a folded linen napkin, a wooden spoon or kuromoji pick, and two or three sakura petals.
- Appetizing, fresh, realistic portion size for one person.
- Absolutely no text, no letters, no logo, no watermark, no price tags, no people, no hands.
Reply "OK" and wait for the first dish.
```

## Prompt per menu

Akhiran gaya yang sama dipakai di semua prompt agar setiap prompt bisa berdiri sendiri.

### Cake & Dessert (ケーキ)

**m1 · Strawberry Cake (苺のショートケーキ)** → simpan sebagai `m1.jpg`
```text
A slice of Japanese strawberry shortcake: fluffy white sponge layers with fresh whipped cream and sliced strawberries inside, topped with a whole glossy strawberry, on a small cream ceramic plate with a silver dessert fork. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m2 · Matcha Cake (抹茶ケーキ)** → `m2.jpg`
```text
A slice of layered matcha cake: vivid green tea sponge with light matcha cream between layers, dusted with matcha powder on top, on a charcoal stoneware plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m3 · Tiramisu (ティラミス)** → `m3.jpg`
```text
A square portion of tiramisu in a small rectangular ceramic dish, showing layers of coffee-soaked ladyfingers and mascarpone cream, dusted with cocoa powder. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m4 · Chocolate Cake (チョコレートケーキ)** → `m4.jpg`
```text
A slice of rich chocolate cake with glossy dark ganache coating and moist layers, a small dollop of whipped cream on the side, on a cream ceramic plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m5 · Japanese Cheesecake (スフレチーズケーキ)** → `m5.jpg`
```text
A tall, jiggly Japanese souffle cheesecake slice with a golden-brown top and pale fluffy interior, lightly dusted with powdered sugar, on a celadon ceramic plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m16 · Hokkaido Chiffon Cake (北海道シフォン)** → `m16.jpg`
```text
A tall wedge of airy Hokkaido milk chiffon cake, very soft and pale golden, with a spoon of lightly whipped unsweetened cream beside it, on a cream ceramic plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m17 · Matcha Mille Crêpe (抹茶ミルクレープ)** → `m17.jpg`
```text
A slice of matcha mille crepe cake showing about twenty thin crepe layers alternating with pale green matcha cream, top dusted with matcha powder, on a charcoal stoneware plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m18 · Japanese Purin (プリン)** → `m18.jpg`
```text
A classic Japanese kissaten-style custard pudding (purin), firm and glossy, with dark amber caramel sauce dripping down the sides, a small swirl of whipped cream and one cherry on top, on a vintage glass dessert plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m19 · Hojicha Pudding (ほうじ茶プリン)** → `m19.jpg`
```text
A smooth light-brown roasted green tea (hojicha) pudding in a small glass jar, drizzled with dark kuromitsu syrup, a wooden spoon beside it, a few roasted tea leaves on the table. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m20 · Yuzu Cheesecake (柚子チーズケーキ)** → `m20.jpg`
```text
A slice of baked cheesecake with a thin glossy yellow yuzu glaze on top, a thin twist of yuzu peel as garnish, graham crumb base visible, on a cream ceramic plate with half a fresh yuzu beside it. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

### Pastry (パン)

**m6 · Butter Croissant (バタークロワッサン)** → `m6.jpg`
```text
A golden, flaky butter croissant with visible laminated layers and a few crumbs, on a small wooden board with a folded linen napkin. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m7 · Cream Puff (シュークリーム)** → `m7.jpg`
```text
Two Japanese cream puffs (choux) with crisp golden shells, one cut open showing rich vanilla custard cream, dusted with powdered sugar, on a cream ceramic plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m8 · Japanese Roll Cake (ロールケーキ)** → `m8.jpg`
```text
Two thick slices of Japanese roll cake with soft light sponge spiraled around a generous center of fresh whipped cream, on a long rectangular ceramic plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m21 · Melon Pan (メロンパン)** → `m21.jpg`
```text
A round Japanese melon pan bun with a crisp golden cookie crust scored in a criss-cross pattern and sprinkled with sugar, on a small wooden tray lined with parchment paper. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m22 · Matcha Melon Pan (抹茶メロンパン)** → `m22.jpg`
```text
A matcha melon pan bun with a pale green criss-cross cookie crust, torn open to show soft bread and matcha cream filling, on parchment paper on a wooden tray. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m23 · Anpan (あんパン)** → `m23.jpg`
```text
A glossy brown Japanese anpan bun topped with black sesame seeds, cut in half to reveal smooth sweet red bean paste, on a cream ceramic plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m24 · Curry Pan (カレーパン)** → `m24.jpg`
```text
A golden deep-fried Japanese curry bread with crunchy panko coating, one piece cut open showing thick Japanese curry filling, on a small bamboo basket with parchment paper. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

**m25 · Matcha Croissant (抹茶クロワッサン)** → `m25.jpg`
```text
A flaky butter croissant with a thin bright matcha glaze and a line of matcha cream filling visible from the side, sprinkled with crushed pistachio, on a charcoal stoneware plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

### Drink (お飲み物)

**m9 · Iced Matcha Latte (アイス抹茶ラテ)** → `m9.jpg`
```text
A tall clear glass of iced matcha latte with distinct layers of white milk at the bottom and vivid green matcha on top, ice cubes, condensation on the glass, on a round wooden coaster. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m10 · Chocolate Latte (チョコレートラテ)** → `m10.jpg`
```text
A warm chocolate latte in a handmade cream ceramic mug with smooth latte art on top and a light dusting of cocoa, on a wooden saucer. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m11 · Hojicha Latte (ほうじ茶ラテ)** → `m11.jpg`
```text
A warm hojicha latte, caramel-brown roasted green tea with steamed milk and a leaf latte art, in a handmade earthy brown ceramic cup, a small pile of roasted tea leaves beside it. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m12 · Strawberry Milk (いちごミルク)** → `m12.jpg`
```text
A glass of Japanese strawberry milk with chunky fresh strawberry puree at the bottom gradually blending into pale pink milk, ice cubes, a fresh strawberry on the rim. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m13 · Cafe Latte (カフェラテ)** → `m13.jpg`
```text
A hot cafe latte with neat rosetta latte art in a handmade celadon ceramic cup and saucer. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m26 · Hot Genmaicha (玄米茶)** → `m26.jpg`
```text
A Japanese yunomi cup of hot genmaicha, pale golden-green tea with gentle steam, next to a small cast-iron teapot and a little dish of genmaicha leaves with toasted rice grains. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, handmade Japanese ceramics on light hinoki wood, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

**m27 · Sakura Latte (桜ラテ)** → `m27.jpg`
```text
A soft pink sakura latte in a white handmade ceramic cup with milk foam on top, decorated with one salted cherry blossom flower floating in the center, a small sprig of sakura beside the cup. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

**m28 · Yuzu Honey Soda (柚子はちみつソーダ)** → `m28.jpg`
```text
A tall glass of sparkling yuzu honey soda, pale yellow with bubbles, ice cubes, thin yuzu slices inside and a sprig of mint, condensation on the glass, a small honey dipper on the side. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

**m29 · Usucha Matcha (薄茶)** → `m29.jpg`
```text
Traditional thin matcha (usucha) whisked to a fine frothy jade-green foam in a rustic raku chawan bowl, a bamboo chasen whisk resting on its stand and a bamboo chashaku scoop beside it, one small wagashi on a tiny plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

**m30 · Matcha Espresso Fusion (抹茶エスプレッソ)** → `m30.jpg`
```text
A clear glass showing three distinct layers: white milk at the bottom, bright green matcha in the middle, and dark espresso floating on top, with ice cubes. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

### Seasonal (季節限定)

**m14 · Sakura Dessert (桜デザート)** → `m14.jpg`
```text
An elegant sakura dessert: a pale pink sakura mousse dome with a clear sakura jelly top holding a preserved cherry blossom flower, on a thin almond sable base, served on a white ceramic plate with a few petals. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

**m15 · Matcha Strawberry Special (抹茶いちごスペシャル)** → `m15.jpg`
```text
A tall parfait glass layered with matcha cream, matcha sponge cubes, fresh strawberries, white chocolate pieces and whipped cream, topped with a whole strawberry and a matcha dusting. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m31 · Kakigori Ichigo Milk (いちごミルクかき氷)** → `m31.jpg`
```text
A tall fluffy mound of Japanese shaved ice (kakigori) in a glass bowl, drenched with bright red fresh strawberry sauce and white condensed milk, a few fresh strawberry halves on top. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

**m32 · Kakigori Uji Kintoki (宇治金時かき氷)** → `m32.jpg`
```text
A tall fluffy mound of Japanese shaved ice in a ceramic bowl, soaked with deep green Uji matcha syrup, topped with sweet azuki red beans and three white shiratama mochi balls, a drizzle of condensed milk. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

**m33 · Waguri Mont Blanc (和栗モンブラン)** → `m33.jpg`
```text
A Japanese chestnut Mont Blanc: fine piped strands of pale brown waguri chestnut cream on a dome of whipped cream and crisp meringue base, topped with one glossy candied chestnut and a little gold leaf, on a charcoal stoneware plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft autumn morning light from the left, a few small autumn maple leaves instead of petals, no text, no logo, no hands.
```

### Wagashi (和菓子)

**m34 · Matcha Warabi Mochi (抹茶わらび餅)** → `m34.jpg`
```text
Soft translucent warabi mochi cubes heavily coated in green matcha powder and a little golden kinako, on a dark lacquer plate, with a small dish of kuromitsu syrup and a kuromoji wooden pick. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m35 · Ichigo Daifuku (いちご大福)** → `m35.jpg`
```text
Two ichigo daifuku: soft white mochi balls dusted with potato starch, one cut in half showing a whole bright red strawberry wrapped in smooth red bean paste, on a small celadon plate with a kuromoji pick. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m36 · Dorayaki (どら焼き)** → `m36.jpg`
```text
Two dorayaki: fluffy golden-brown honey pancakes sandwiching thick sweet red bean paste, one cut to show the filling, on a rustic cream ceramic plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m37 · Mitarashi Dango (みたらし団子)** → `m37.jpg`
```text
Three bamboo skewers of mitarashi dango, chewy rice dumplings lightly grilled with char marks, glazed with glossy amber sweet soy sauce, on a long rectangular ceramic plate. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m38 · Taiyaki Custard (たい焼き)** → `m38.jpg`
```text
Two golden crispy fish-shaped taiyaki cakes with detailed scale patterns, one broken open showing warm vanilla custard filling, on parchment paper on a small wooden tray. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, a few sakura petals, no text, no logo, no hands.
```

**m39 · Sakura Mochi (桜餅)** → `m39.jpg`
```text
Two Kansai-style sakura mochi: pink glutinous rice mochi with a slightly grainy texture filled with red bean paste, each wrapped in a pickled green-brown cherry leaf, on a small white ceramic plate with a sprig of cherry blossoms. Kokoro Cakes style: square 1:1, photorealistic, 45-degree angle, light hinoki wood table, blurred cream washi paper background, soft morning light from the left, no text, no logo, no hands.
```

## Opsional: gambar hero beranda

Beranda menampilkan foto besar di bawah sambutan jika file `public/assets/img/hero.jpg` (atau .webp/.png) ada. Tanpa file itu, beranda tanpa foto.

```text
A wide 4:3 photo of a Japanese tea house table: a light hinoki wooden tray holding a bowl of frothy matcha in a raku chawan, a skewer of pink-white-green hanami dango, a slice of strawberry shortcake and a small ichigo daifuku, a matcha-green noren curtain softly blurred in the background, cream washi paper wall, sakura branch at the top right, soft morning light from the left, calm and cozy wa-cafe mood, photorealistic, no text, no logo, no people, no hands.
```
