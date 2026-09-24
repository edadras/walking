<?php

namespace App\Domain\Device;

/**
 * Canonical request signing (docs/phase-0/06-security.md §6.2).
 *
 *   canonical = METHOD \n PATH \n TIMESTAMP \n NONCE \n hex(sha256(body))
 *   signature = base64(DER ECDSA-P256-SHA256(canonical))
 */
final class RequestSignature
{
    public static function canonical(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [strtoupper($method), $path, $timestamp, $nonce, hash('sha256', $body)]);
    }

    public static function verify(string $publicKeyPem, string $canonical, string $signatureBase64): bool
    {
        $signature = base64_decode($signatureBase64, true);
        if ($signature === false || $signature === '') {
            return false;
        }

        $key = openssl_pkey_get_public($publicKeyPem);
        if ($key === false) {
            return false;
        }

        return openssl_verify($canonical, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Accepts a PEM or a base64 DER SubjectPublicKeyInfo, and returns a normalised
     * PEM only if it is an EC key on P-256.
     */
    public static function normalizePublicKey(string $input): ?string
    {
        $input = trim($input);
        if (! str_contains($input, 'BEGIN PUBLIC KEY')) {
            $der = base64_decode($input, true);
            if ($der === false) {
                return null;
            }
            $input = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
        }

        $key = openssl_pkey_get_public($input);
        if ($key === false) {
            return null;
        }

        $details = openssl_pkey_get_details($key);
        if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($details['ec']['curve_name'] ?? null) !== 'prime256v1') {
            return null;
        }

        return $details['key'];
    }

    /** sha256 (hex) of the DER SubjectPublicKeyInfo — reproducible on the client from key.getEncoded(). */
    public static function fingerprint(string $pem): string
    {
        $body = preg_replace('/-----[A-Z ]+-----|\s+/', '', $pem) ?? '';

        return hash('sha256', base64_decode($body));
    }
}
