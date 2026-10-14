# Madezur Poker

Jalankan Apache XAMPP dan PostgreSQL 15, lalu buka http://localhost/poker/.
Konfigurasi koneksi ada di `config.php`. Database: `dbp`.

## Bermain Poker bersama

1. Login, pilih **Main Poker**, tentukan jumlah pemain (4–20) dan deck (1–10), lalu **Buat ruang baru**.
2. Bagikan kode ruang. Teman login dengan akun berbeda, membuka Poker, dan memasukkan kode di **Gabung ruang**.
3. Pembuat ruang menekan **Mulai permainan** setelah jumlah pemain yang dipilih sudah bergabung.
4. Pemegang 4♣ dari deck 1 memulai. Pilih satu kartu yang aktif, lalu tekan **Mainkan kartu**. Salinan kartu dari deck lain memiliki nilai yang sama, dengan ID berbeda.
5. Jika tidak ada kartu lebih tinggi, tombol **Pass** muncul. Setelah semua lawan pass berturut-turut, pemain terakhir bebas membuka.
6. Pemain pertama yang menghabiskan kartu menjadi juara 1. Permainan berlanjut hingga seluruh peringkat ditentukan. Setelah ronde selesai, setiap pemain memilih **Lanjut** atau **Berhenti**. Ronde berikutnya otomatis dimulai setelah semua pemain tersisa memilih Lanjut. Jika tersisa kurang dari 4 pemain, permainan dihentikan.
7. Pemain yang berhenti mendapat 0 pada kolom ronde berikutnya, termasuk baris kosong yang disediakan. Poin ronde sebelumnya tetap dipertahankan. Nol karena berhenti tidak menambah jumlah kekalahan atau ronde yang dimainkan.

## Ruang online, poin, dan statistik

Daftar ruang online menampilkan ruang dengan pemain yang mengirim pembaruan dalam 30 detik terakhir. Ruang yang menunggu pemain dapat digabung dari daftar. Data diperbarui setiap 5 detik.
Tombol **Poin** menampilkan aturan dan peringkat total poin. Poin = jumlah pemain dikurangi peringkat: untuk 4 pemain, 3, 2, 1, 0; untuk 20 pemain, 19 hingga 0.
Setelah ronde selesai, nama ruang dapat diklik untuk membuka tabel hasil: baris Ronde, kolom username pemain, dan isi sel berupa poin. Tabel menyediakan minimal 20 baris, lalu bertambah mengikuti jumlah ronde selesai. Ronde yang belum selesai tidak masuk tabel. Hasil ruang tetap tersedia setelah ruang ditutup. Kolom pemain yang tidak ikut suatu ronde dibiarkan kosong. Tabel global kekalahan tidak ditampilkan langsung di lobi.
Hasil ronde tersimpan sekali setelah semua peringkat ditentukan dalam tabel `poker_results`. Kekalahan dihitung hanya untuk peringkat terakhir; kemenangan untuk juara 1.
Poin dan statistik berlaku untuk ronde baru setelah fitur ini ditambahkan. Ronde lama tidak memiliki catatan semua peringkat.
Jika pemain terakhir yang mengeluarkan kartu sudah selesai dan semua pemain aktif pass, pemain aktif berikutnya membuka kartu apa saja.

Setiap simbol memiliki nilai yang sama. Urutan: 3–10, J, Q, K, A, 2, joker.
Joker hitam dan merah bernilai sama, lebih tinggi dari semua kartu biasa.
Joker tidak bisa dikalahkan; semua lawan pass dan pemain terakhir bebas membuka.
Setiap deck berisi 54 kartu. Seluruh kartu dibagikan bergiliran. Dengan 1 deck dan 4 pemain, jumlah kartu awal adalah 14, 14, 13, 13. Dengan 10 deck dan 20 pemain, setiap pemain mendapat 27 kartu.

Status ruang dan kartu tersimpan di tabel `poker_rooms`; frontend memperbarui status setiap 1,5 detik.
Aturan divalidasi di server, dan hanya kartu pemain sendiri dikirim ke browser.
Refresh halaman mempertahankan permainan. Setelah login ulang, gunakan kode ruang yang sama untuk kembali.
Ruang tidak bisa ditinggalkan selama ronde aktif; saat pemain terputus, giliran menunggu pemain tersebut kembali.

Untuk menguji dari satu komputer, gunakan browser atau profil browser berbeda untuk setiap akun.
Untuk perangkat lain di jaringan yang sama, gunakan `http://<IP-komputer-XAMPP>/poker/`.
Permainan ini belum dipublikasikan ke internet. TJ masih belum diimplementasikan.

## Database dan pengujian

`schema.sql` membuat tabel akun `public.user`; `poker-schema.sql` membuat tabel ruang; `stats-schema.sql` membuat tabel hasil dan kehadiran; `continuation-schema.sql` menyimpan pemain yang berhenti.
Jalankan keempatnya berurutan pada database PostgreSQL `dbp` jika menyiapkan server baru.
Tes CLI: `C:\xampp\php\php.exe tests\poker-test.php`.
Tes mencakup 170 kombinasi pemain/deck, 51 simulasi lengkap, satu permainan HTTP dengan 2 deck, serta ruang 20 pemain dengan 10 deck.
Akun dan ruang sementara dibersihkan setelah pengujian selesai.
