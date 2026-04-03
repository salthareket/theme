<?php

/**
 * Encrypt / Decrypt utility (AES-256-CBC)
 *
 * Format: base64(encrypted_data):salt
 * Salt is stored alongside the encrypted data, IV is derived from salt.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Basic encrypt/decrypt
 * $enc = new Encrypt();
 * $token = $enc->encrypt('user_123');
 * $value = $enc->decrypt($token);              // 'user_123'
 *
 * // Encrypt array (auto JSON)
 * $token = $enc->encrypt(['user_id' => 5, 'role' => 'admin']);
 * $data  = $enc->decrypt($token);              // ['user_id' => 5, 'role' => 'admin']
 *
 * // Custom key
 * $enc = new Encrypt('my-32-byte-secret-key-here!!!!!');
 *
 * // URL-safe mode (for query strings)
 * $enc = new Encrypt('', true);
 * $token = $enc->encrypt('data');              // URL-safe string with salt
 * $value = $enc->decrypt($token);
 *
 * // Validate and get typed data
 * $result = $enc->validate($token, 'json');    // decoded array or false
 * $result = $enc->validate($token, 'string');  // raw string
 *
 * ──────────────────────────────────────────────────────────
 */
class Encrypt
{
    private $cipher_method;
    private $key;
    private $useUrlEncoding;

    public function __construct($key = "", $useUrlEncoding = false) {
        $this->cipher_method = 'AES-256-CBC';
        $this->useUrlEncoding = $useUrlEncoding;

        if (empty($key)) {
            if (!defined('ENCRYPT_SECRET_KEY')) {
                throw new RuntimeException("ENCRYPT_SECRET_KEY constant is not defined.");
            }
            $key = ENCRYPT_SECRET_KEY;
        }
        $this->key = $key;

        if (mb_strlen($this->key, '8bit') !== 32) {
            throw new InvalidArgumentException("Key must be exactly 32 bytes long.");
        }
    }

    /**
     * Derive IV from salt (deterministic — same salt = same IV)
     */
    private function derive_iv($salt) {
        return str_pad(substr($salt, 0, 16), 16, '0', STR_PAD_LEFT);
    }

    public function encrypt($data) {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        } elseif (!is_string($data)) {
            throw new InvalidArgumentException("Data must be a string or an array.");
        }

        $salt = hash('sha256', uniqid(mt_rand(), true));
        $iv = $this->derive_iv($salt);

        $encrypted = openssl_encrypt($data, $this->cipher_method, $this->key, 0, $iv);

        if ($encrypted === false) {
            throw new RuntimeException("Encryption failed.");
        }

        // Format: base64(encrypted):salt
        $output = base64_encode($encrypted) . ':' . $salt;

        return $this->useUrlEncoding ? urlencode($output) : $output;
    }

    public function decrypt($encrypted_data) {
        if (!is_string($encrypted_data) || empty($encrypted_data)) {
            return $encrypted_data;
        }

        // URL decode if needed
        if ($this->useUrlEncoding) {
            $encrypted_data = urldecode($encrypted_data);
        }

        // Parse format: base64(encrypted):salt
        $parts = explode(':', $encrypted_data, 2);
        if (count($parts) !== 2) {
            return $encrypted_data; // Not our format, return as-is
        }

        $encoded = $parts[0];
        $salt = $parts[1];
        $iv = $this->derive_iv($salt);

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            return $encrypted_data;
        }

        $decrypted = openssl_decrypt($decoded, $this->cipher_method, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new RuntimeException("Decryption failed. Check your key and data.");
        }

        // Auto-decode JSON arrays
        if ($this->isJson($decrypted)) {
            return json_decode($decrypted, true);
        }

        return $decrypted;
    }

    public function validate($encrypted_data, $expected_data_type = 'json') {
        try {
            $decrypted = $this->decrypt($encrypted_data);
        } catch (RuntimeException $e) {
            return false;
        }

        switch ($expected_data_type) {
            case 'json':
                // decrypt() already decodes JSON to array
                return is_array($decrypted) ? $decrypted : false;
            case 'string':
                return is_string($decrypted) ? $decrypted : false;
            case 'numeric':
                return is_numeric($decrypted) ? $decrypted : false;
            default:
                return $decrypted;
        }
    }

    private function isJson($string): bool {
        if (!is_string($string) || $string === '') return false;
        json_decode($string, true);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
