<?php

class Encrypt
{
    private $salt;
    private $cipher_method;
    private $key;
    private $iv;
    private $useUrlEncoding;

    public function __construct($key="", $useUrlEncoding = false){
        $this->salt = hash('sha256', uniqid(mt_rand(), true));
        $this->cipher_method = 'AES-256-CBC';
        $this->key = empty($key)?ENCRYPT_SECRET_KEY:$key;
        $this->iv = str_pad(substr($this->salt, 0, 16), 16, '0', STR_PAD_LEFT);//openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher_method));
        $this->useUrlEncoding = $useUrlEncoding;

        if (mb_strlen($this->key, '8bit') !== 32) {
            throw new InvalidArgumentException("Key must be exactly 32 bytes long.");
        }
    }

    public function encrypt($data){
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        } elseif (!is_string($data)) {
            throw new InvalidArgumentException("Data must be a string or an array.");
        }
        $encrypted = openssl_encrypt($data, $this->cipher_method, $this->key, 0, $this->iv);
        return $this->useUrlEncoding ? urlencode(base64_encode($encrypted)) : base64_encode($encrypted).':'.$this->salt;
    }

    public function decrypt($encrypted_data){
        if( count(explode(':', $encrypted_data)) !== 2 ) { return $encrypted_data; }
        $salt = explode(":",$encrypted_data)[1]; $encrypted_data = explode(":",$encrypted_data)[0]; // read salt from entry

        if ($this->useUrlEncoding) {
            $encrypted_data = urldecode($encrypted_data);
        }

        if (!is_string($encrypted_data)) {
            throw new InvalidArgumentException("Encrypted data must be a string.");
        }

        $decoded_data = base64_decode($encrypted_data);
        $decrypted = openssl_decrypt($decoded_data, $this->cipher_method, $this->key, 0, str_pad(substr($salt, 0, 16), 16, '0', STR_PAD_LEFT));

        if ($decrypted === false) {
            throw new RuntimeException("Decryption failed. Check your key and data.");
        }

        if ($this->isJson($decrypted)) {
            return json_decode($decrypted, true);
        }

        return $decrypted;
    }

    public function validate($encrypted_data, $expected_data_type = 'json')
    {
        $decrypted_data = $this->decrypt($encrypted_data);
        switch ($expected_data_type) {
            case 'json':
                $decoded_data = json_decode($decrypted_data, true);
                return ($decoded_data !== null) ? $decoded_data : false;
            // Diğer veri tipleri için doğrulama işlemleri ekleyebilirsiniz
            default:
                return $decrypted_data;
        }
    }

    private function isJson($string)
    {
        return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }
}

