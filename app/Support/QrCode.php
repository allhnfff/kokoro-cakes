<?php

declare(strict_types=1);

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRMarkupSVG;

/**
 * Gambar kode QR sebagai SVG (tanpa ekstensi GD), untuk kartu QR meja yang dicetak.
 * Isinya hanya tautan meja; SVG dibuat di server dan dicetak langsung ke halaman.
 */
final class QrCode
{
    public static function svg(string $text): string
    {
        $options = new QROptions([
            'outputInterface'   => QRMarkupSVG::class,
            'outputBase64'      => false,
            'svgAddXmlHeader'   => false,
            'eccLevel'          => EccLevel::M, // tahan sedikit goresan/noda di meja
            'addQuietzone'      => true,
            'quietzoneSize'     => 2,
            'drawLightModules'  => false,
            'connectPaths'      => true,
            'cssClass'          => 'qr-svg',
        ]);
        return (new Generator($options))->render($text);
    }

    /**
     * Alamat dasar untuk tautan QR.
     * Bawaan APP_URL. Jika APP_URL masih localhost tetapi halaman kasir dibuka lewat IP jaringan lokal
     * (misalnya http://192.168.1.5:8000), QR memakai alamat itu agar bisa dipindai HP di Wi-Fi yang sama.
     * Hanya IP privat yang diterima, jadi header Host palsu tidak bisa membelokkan QR ke situs lain.
     */
    public static function baseUrl(string $appUrl, string $requestHost): string
    {
        $appUrl = rtrim($appUrl, '/');
        $appHost = (string) parse_url($appUrl, PHP_URL_HOST);
        if (!in_array($appHost, ['localhost', '127.0.0.1'], true)) {
            return $appUrl;
        }
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})(?::(\d{1,5}))?$/', $requestHost, $m) === 1
            && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false
            && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && !str_starts_with($m[1], '127.')) {
            return 'http://' . $requestHost;
        }
        return $appUrl;
    }

    /** Apakah alamat ini hanya bisa dibuka dari komputer itu sendiri (localhost)? */
    public static function isLocalOnly(string $baseUrl): bool
    {
        return in_array((string) parse_url($baseUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
    }

    /**
     * IPv4 jaringan lokal laptop ini (untuk petunjuk "buka lewat IP ini agar HP bisa memindai").
     * @return list<string>
     */
    public static function lanAddresses(): array
    {
        $ips = gethostbynamel((string) gethostname()) ?: [];
        $ips = array_values(array_filter($ips, static fn ($ip) => !str_starts_with($ip, '127.')
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE) === false));
        // Wi-Fi rumah/kafe biasanya 192.168.x.x; 192.168.56.x (VirtualBox) dan 172.x (WSL/Hyper-V) di belakang.
        $rank = static fn (string $ip): int => match (true) {
            str_starts_with($ip, '192.168.56.') => 2,
            str_starts_with($ip, '192.168.') => 0,
            str_starts_with($ip, '10.') => 1,
            default => 3,
        };
        usort($ips, static fn ($a, $b) => $rank($a) <=> $rank($b));
        return $ips;
    }
}
