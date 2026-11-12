<?php

declare(strict_types=1);

namespace App\Http;

use Predis\ClientInterface;
use Predis\PredisException;

/**
 * Penyimpanan sesi PHP di Redis (key sess:{id}, TTL = umur sesi).
 * Dipakai saat SESSION_DRIVER=redis, misalnya di Vercel: di sana tiap permintaan bisa jalan di
 * mesin berbeda dan folder sementara tidak dibagi, jadi file sesi biasa akan hilang.
 *
 * Hemat perintah: dengan session.lazy_write (bawaan PHP), sesi yang tidak berubah hanya
 * diperpanjang (EXPIRE) lewat updateTimestamp, tidak ditulis ulang.
 * Jika Redis gangguan, sesi dianggap kosong; halaman yang butuh data akan menampilkan error Redis.
 */
final class RedisSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    private const PREFIX = 'sess:';

    /** @param callable(): ClientInterface $connect dibuat saat pertama dipakai */
    public function __construct(private $connect, private readonly int $ttl)
    {
    }

    private ?ClientInterface $redis = null;

    private function redis(): ClientInterface
    {
        return $this->redis ??= ($this->connect)();
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            return (string) ($this->redis()->get(self::PREFIX . $id) ?? '');
        } catch (PredisException $e) {
            error_log('[kokoro] sesi redis (baca): ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $this->redis()->setex(self::PREFIX . $id, $this->ttl, $data);
            return true;
        } catch (PredisException $e) {
            error_log('[kokoro] sesi redis (tulis): ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->redis()->del([self::PREFIX . $id]);
        } catch (PredisException $e) {
            error_log('[kokoro] sesi redis (hapus): ' . $e->getMessage());
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0; // TTL Redis yang membersihkan sesi kedaluwarsa
    }

    /** Dipanggil PHP (use_strict_mode) untuk menolak ID sesi buatan sendiri yang tidak ada di server. */
    public function validateId(string $id): bool
    {
        try {
            return (bool) $this->redis()->exists(self::PREFIX . $id);
        } catch (PredisException) {
            return false;
        }
    }

    /** Sesi tidak berubah: cukup perpanjang umurnya. */
    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            $this->redis()->expire(self::PREFIX . $id, $this->ttl);
            return true;
        } catch (PredisException) {
            return false;
        }
    }
}
