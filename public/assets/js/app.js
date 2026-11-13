/* =====================================================================
   KOKORO CAKES: JavaScript kecil tanpa library.
   - Polling ketersediaan menu (/api/menu)
   - Polling status pesanan pelanggan (/api/pesanan/{id})
   - Polling dashboard kasir (/api/kasir/ringkasan)
   - Tambah ke keranjang tanpa pindah halaman (cadangan: form biasa)
   - Momen "pesanan siap": getar ponsel + pesan saat status berubah
   - Tombol stepper jumlah, cegah klik ganda, salin tautan QR
   - Foto beranda yang bisa digeser: titik navigasi, ganti otomatis tiap 5 detik
   Semua data diambil lewat server PHP; browser tidak pernah terhubung ke Redis.
   Halaman tetap berfungsi tanpa JavaScript.
   ===================================================================== */
(function () {
    'use strict';

    function getJson(url) {
        return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) {
                return res.json().then(function (data) { return { status: res.status, data: data }; });
            });
    }

    /** Ulangi fungsi setiap ms, berhenti sementara saat tab tidak terlihat. */
    function every(ms, fn) {
        if (!ms || ms < 1000) { return; }
        var timer = null;
        function tick() {
            if (document.hidden) { timer = setTimeout(tick, ms); return; }
            Promise.resolve(fn()).catch(function () {}).then(function () { timer = setTimeout(tick, ms); });
        }
        timer = setTimeout(tick, ms);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { clearTimeout(timer); tick(); }
        });
    }

    // ---------- Ketersediaan menu ----------
    var menuNote = document.querySelector('[data-menu-poll]');
    if (menuNote) {
        every(parseInt(menuNote.getAttribute('data-menu-poll'), 10), function () {
            return getJson('/api/menu').then(function (r) {
                if (!r.data.ok) { menuNote.textContent = 'Tidak dapat memperbarui ketersediaan menu.'; return; }
                document.querySelectorAll('[data-menu-id]').forEach(function (el) {
                    var status = r.data.status[el.getAttribute('data-menu-id')];
                    if (!status) { return; }
                    var habis = status !== 'Tersedia';
                    el.classList.toggle('is-habis', habis);
                    var tag = el.querySelector('[data-sold-tag]');
                    if (tag) { tag.hidden = !habis; }
                    var stock = el.querySelector('[data-stock-text]');
                    if (stock) { stock.textContent = 'Stok: ' + (habis ? 'Habis' : 'Tersedia'); stock.classList.toggle('is-out', habis); }
                    var btn = el.querySelector('[data-add-btn]');
                    if (btn) { btn.disabled = habis; }
                });
                menuNote.textContent = 'Ketersediaan menu diperbarui ' + r.data.fetched_at + '.';
            });
        });
    }

    // ---------- Status pesanan pelanggan ----------
    var orderHead = document.querySelector('[data-order-poll]');
    if (orderHead) {
        var orderId = orderHead.getAttribute('data-order-poll');
        var note = document.querySelector('[data-poll-note]');
        every(parseInt(orderHead.getAttribute('data-interval'), 10), function () {
            return getJson('/api/pesanan/' + encodeURIComponent(orderId)).then(function (r) {
                if (!r.data.ok) { if (note) { note.textContent = 'Tidak dapat memperbarui status. Mencoba lagi…'; } return; }
                var changed = r.data.status_pesanan !== orderHead.getAttribute('data-status')
                    || r.data.status_bayar !== orderHead.getAttribute('data-pay');
                if (changed) {
                    // Tandai agar setelah dimuat ulang pesan status baru langsung disorot.
                    try { sessionStorage.setItem('kokoro:changed', orderId); } catch (e) {}
                    var delay = 0;
                    if (r.data.status_pesanan === 'Siap Diantar' && navigator.vibrate) {
                        navigator.vibrate([180, 90, 180]);
                        delay = 600;
                    }
                    // Muat ulang agar timeline, riwayat, dan tombol ikut diperbarui dari server.
                    setTimeout(function () { window.location.replace(window.location.pathname); }, delay);
                } else if (note) {
                    note.textContent = 'Status diperbarui otomatis. Terakhir dicek ' + new Date().toLocaleTimeString('id-ID') + '.';
                }
            });
        });
    }

    // Setelah status berubah: sorot pesan "siap diantar" / "terima kasih".
    var moment = document.querySelector('[data-moment]');
    if (moment && orderHead) {
        var flagged = null;
        try { flagged = sessionStorage.getItem('kokoro:changed'); sessionStorage.removeItem('kokoro:changed'); } catch (e) {}
        if (flagged === orderHead.getAttribute('data-order-poll')) {
            moment.classList.add('is-new');
            moment.focus({ preventScroll: true });
            moment.scrollIntoView({ block: 'center' });
        }
    }

    // ---------- Tambah ke keranjang tanpa pindah halaman ----------
    var toast = document.querySelector('[data-toast]');
    var toastTimer = null;
    function showToast(text, isError) {
        if (!toast) { return; }
        toast.querySelector('[data-toast-text]').textContent = text;
        toast.classList.toggle('is-error', !!isError);
        toast.querySelector('[data-toast-link]').hidden = !!isError;
        clearTimeout(toastTimer);
        if (toast.hidden) {
            // Mulai dari posisi sedikit di bawah & transparan, lalu meluncur naik.
            toast.classList.add('is-entering');
            toast.hidden = false;
            requestAnimationFrame(function () {
                requestAnimationFrame(function () { toast.classList.remove('is-entering'); });
            });
        }
        toastTimer = setTimeout(function () {
            toast.classList.add('is-entering');
            setTimeout(function () { toast.hidden = true; toast.classList.remove('is-entering'); }, 230);
        }, 3500);
    }
    function setCartCount(n) {
        n = parseInt(n, 10) || 0;
        // Satu tempat keranjang per layar: ikon header (desktop) atau tab bawah (HP); angka disembunyikan saat 0.
        document.querySelectorAll('[data-cart-count]').forEach(function (el) {
            var before = parseInt(el.textContent, 10) || 0;
            el.textContent = n;
            el.hidden = n === 0;
            if (n > before && el.classList.contains('cart-badge')) {
                el.classList.remove('is-bump');
                void el.offsetWidth; // mulai ulang animasi pantul
                el.classList.add('is-bump');
            }
        });
        document.querySelectorAll('.cart-btn, .tab-cart').forEach(function (el) { el.setAttribute('aria-label', 'Keranjang, ' + n + ' item'); });
    }
    if (toast && window.fetch && window.FormData) {
        document.querySelectorAll('form[data-add-form]').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                if (form.getAttribute('data-sending') === '1') { ev.preventDefault(); return; }
                ev.preventDefault();
                var btn = form.querySelector('[data-add-btn]');
                form.setAttribute('data-sending', '1');
                if (btn) { btn.disabled = true; }
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                }).then(function (res) {
                    var type = res.headers.get('Content-Type') || '';
                    if (type.indexOf('application/json') === -1) {
                        // Bukan JSON (sesi habis, meja tidak aktif, server data mati): server belum
                        // menyimpan apa pun, jadi kirim ulang sebagai form biasa agar pesannya tampil.
                        form.removeAttribute('data-add-form');
                        form.setAttribute('data-sending', '0');
                        HTMLFormElement.prototype.submit.call(form);
                        return null;
                    }
                    return res.json();
                }).then(function (data) {
                    if (!data) { return; }
                    setCartCount(data.cart_count);
                    showToast(data.message, !data.ok);
                }).catch(function () {
                    showToast('Gagal terhubung. Periksa koneksi lalu coba lagi.', true);
                }).then(function () {
                    form.setAttribute('data-sending', '0');
                    if (!btn) { return; }
                    // Tombol tetap terkunci hanya jika ini tombol utama menu yang sedang habis.
                    var owner = btn.closest('[data-menu-id]');
                    var sold = owner && owner.classList.contains('is-habis') && owner.querySelector('[data-add-btn]') === btn;
                    btn.disabled = !!sold;
                });
            });
        });
    }

    // ---------- Hitung mundur waktu bayar di kasir ----------
    var payBox = document.querySelector('[data-pay-deadline]');
    if (payBox) {
        var deadline = parseInt(payBox.getAttribute('data-pay-deadline'), 10);
        // Selisih jam HP tamu dengan jam server, supaya hitung mundur tetap tepat walau jam HP meleset.
        var skew = Date.now() / 1000 - parseInt(payBox.getAttribute('data-server-now'), 10);
        var warnAt = parseInt(payBox.getAttribute('data-warn'), 10) || 300;
        var line = payBox.querySelector('[data-countdown]');
        var alertEl = payBox.querySelector('[data-countdown-alert]');
        var warned = payBox.classList.contains('is-urgent');
        var timer = null;
        var tick = function () {
            var left = deadline - (Date.now() / 1000 - skew);
            if (left <= 0) {
                line.textContent = 'Waktu bayar habis. Memeriksa status pesanan…';
                if (alertEl) { alertEl.textContent = 'Waktu bayar habis.'; }
                clearInterval(timer);
                return;
            }
            var mins = Math.ceil(left / 60);
            if (left <= warnAt) {
                line.textContent = 'Sisa ' + (left < 60 ? 'kurang dari 1' : mins) + ' menit, segera ke kasir!';
                if (!warned) {
                    warned = true;
                    payBox.classList.add('is-urgent');
                    if (alertEl) { alertEl.textContent = 'Sisa ' + mins + ' menit, segera ke kasir.'; }
                    if (navigator.vibrate) { navigator.vibrate([200, 100, 200]); }
                }
            } else {
                line.innerHTML = 'Sisa <b>' + mins + ' menit</b> untuk membayar.';
            }
        };
        tick();
        timer = setInterval(tick, 10000);
    }

    // ---------- Dashboard kasir ----------
    var kasirNote = document.querySelector('[data-kasir-poll]');
    if (kasirNote) {
        var banner = document.querySelector('[data-kasir-banner]');
        var signature = null;
        var check = function () {
            return getJson('/api/kasir/ringkasan').then(function (r) {
                if (r.status === 401) { window.location.href = '/kasir/login'; return; }
                if (!r.data.ok) { kasirNote.textContent = 'Tidak dapat memperbarui: ' + r.data.message; return; }
                if (signature === null) { signature = r.data.signature; }
                if (r.data.signature !== signature && banner) {
                    banner.hidden = false;
                    document.title = '(' + r.data.masuk + ' baru) ' + document.title.replace(/^\(\d+ baru\) /, '');
                }
                kasirNote.textContent = 'Dicek ' + new Date().toLocaleTimeString('id-ID');
            });
        };
        check();
        every(parseInt(kasirNote.getAttribute('data-kasir-poll'), 10), check);
    }

    // ---------- Stepper jumlah ----------
    document.querySelectorAll('[data-stepper]').forEach(function (box) {
        var input = box.querySelector('input');
        box.querySelectorAll('[data-step]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var min = parseInt(input.min || '1', 10);
                var max = parseInt(input.max || '20', 10);
                var v = (parseInt(input.value, 10) || min) + parseInt(btn.getAttribute('data-step'), 10);
                input.value = Math.min(max, Math.max(min, v));
            });
        });
    });

    // ---------- Cegah kirim ganda ----------
    document.querySelectorAll('form[data-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (!btn) { return; }
            // Tunda sedikit agar nilai tombol tetap terkirim, lalu kunci.
            setTimeout(function () {
                btn.disabled = true;
                var text = btn.getAttribute('data-loading-text');
                if (text) { btn.textContent = text; }
            }, 0);
        });
    });

    // ---------- Foto memudar masuk saat selesai dimuat ----------
    // Hanya foto yang BELUM selesai dimuat saat skrip berjalan; yang sudah tampil tidak disentuh.
    document.querySelectorAll('.menu-card img, .index-sample img, .pairing img, .entrance-photo img, .item-img img, .cart-line img').forEach(function (img) {
        if (img.complete && img.naturalWidth > 0) { return; }
        img.classList.add('kk-fade');
        var done = function () { img.classList.add('is-loaded'); };
        img.addEventListener('load', done, { once: true });
        img.addEventListener('error', done, { once: true });
    });
    document.querySelectorAll('.cart-badge').forEach(function (el) {
        el.addEventListener('animationend', function () { el.classList.remove('is-bump'); });
    });

    // ---------- Pop-up konfirmasi (misalnya logout kasir) ----------
    // Tanpa JavaScript atau tanpa dukungan <dialog>, form langsung terkirim seperti biasa.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        if (typeof HTMLDialogElement !== 'function') { return; }
        form.addEventListener('submit', function (ev) {
            if (form.getAttribute('data-confirmed') === '1') { return; }
            ev.preventDefault();
            var dlg = document.createElement('dialog');
            dlg.className = 'kk-dialog';
            dlg.innerHTML = '<div class="kk-dialog-icon" aria-hidden="true">!</div><p></p>'
                + '<div class="kk-dialog-actions"><button type="button" class="btn" data-no>Batal</button>'
                + '<button type="button" class="btn btn-primary" data-yes></button></div>';
            dlg.querySelector('p').textContent = form.getAttribute('data-confirm');
            dlg.querySelector('[data-yes]').textContent = form.getAttribute('data-confirm-ok') || 'Ya';
            document.body.appendChild(dlg);
            dlg.addEventListener('close', function () { dlg.remove(); });
            dlg.querySelector('[data-no]').addEventListener('click', function () { dlg.close(); });
            dlg.querySelector('[data-yes]').addEventListener('click', function () {
                form.setAttribute('data-confirmed', '1');
                dlg.close();
                HTMLFormElement.prototype.submit.call(form);
            });
            dlg.showModal();
            dlg.querySelector('[data-no]').focus();
        });
    });

    // ---------- Foto beranda: titik navigasi, ganti otomatis tiap 5 detik ----------
    // Tanpa JavaScript foto tetap bisa digeser (scroll-snap di CSS); di sini hanya tambahan.
    document.querySelectorAll('[data-carousel]').forEach(function (track) {
        var media = track.closest('.entrance-media');
        var slides = track.querySelectorAll('.hero-slide');
        var dotsBox = media && media.querySelector('[data-carousel-dots]');
        if (slides.length < 2 || !dotsBox) { return; }
        var DELAY = 5000;
        // "Kurangi animasi" aktif: foto tetap berganti, tetapi langsung tanpa efek geser.
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var index = 0, timer = null, settle = null;
        var hold = false;      // sementara: kursor di atas foto atau fokus keyboard di dalamnya
        var visible = true;

        // Foto menu memakai loading="lazy"; setelah halaman selesai dimuat, ambil semuanya agar tidak kosong saat bergeser.
        function loadAll() { track.querySelectorAll('img[loading="lazy"]').forEach(function (img) { img.loading = 'eager'; }); }
        if (document.readyState === 'complete') { loadAll(); } else { window.addEventListener('load', loadAll, { once: true }); }

        var dots = [];
        slides.forEach(function (slide, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'hero-dot';
            b.setAttribute('aria-label', 'Tampilkan foto ' + (i + 1) + ' dari ' + slides.length);
            b.addEventListener('click', function () { go(i); });
            dotsBox.appendChild(b);
            dots.push(b);
        });
        dotsBox.hidden = false;

        function render() {
            dots.forEach(function (d, i) { d.setAttribute('aria-current', i === index ? 'true' : 'false'); });
        }
        function go(i) {
            track.scrollTo({ left: i * track.clientWidth, behavior: reduce ? 'auto' : 'smooth' });
        }
        function schedule() {
            clearTimeout(timer);
            if (hold || !visible || document.hidden) { return; }
            timer = setTimeout(function () { go((index + 1) % slides.length); }, DELAY);
        }
        // Setiap geseran selesai (otomatis, lewat titik, atau jari): perbarui titik lalu hitung ulang 5 detik.
        track.addEventListener('scroll', function () {
            clearTimeout(timer);
            clearTimeout(settle);
            settle = setTimeout(function () {
                index = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
                render();
                schedule();
            }, 120);
        }, { passive: true });

        // Berhenti sebentar saat kursor di atas foto (hanya perangkat dengan kursor; di layar sentuh tidak ada "mouseleave").
        if (window.matchMedia('(hover: hover)').matches) {
            media.addEventListener('mouseenter', function () { hold = true; schedule(); });
            media.addEventListener('mouseleave', function () { hold = false; schedule(); });
        }
        // Fokus keyboard (Tab) di foto/titik juga menahan. Klik/ketuk titik tidak: setelahnya tetap berganti otomatis.
        media.addEventListener('focusin', function (e) { hold = e.target.matches(':focus-visible'); schedule(); });
        media.addEventListener('focusout', function (e) { if (!media.contains(e.relatedTarget)) { hold = false; schedule(); } });
        document.addEventListener('visibilitychange', schedule);
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) { visible = entries[0].isIntersecting; schedule(); }, { threshold: 0.5 }).observe(track);
        }
        // Ponsel diputar / jendela diubah ukurannya: tetap di foto yang sama.
        window.addEventListener('resize', function () { track.scrollLeft = index * track.clientWidth; });

        render();
        schedule();
    });

    // ---------- Tombol cetak (kartu QR meja) ----------
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () { window.print(); });
    });

    // ---------- Salin tautan QR ----------
    document.querySelectorAll('[data-copy]').forEach(function (input) {
        input.addEventListener('focus', function () { input.select(); });
    });
})();
