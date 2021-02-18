<?php namespace DE\RUB\AutofillExternalModule;

use Exception;

/**
 * A simple class that relies on OpenSSL to encrypt/decrypt data.
 */
class Crypto {

    const BLOB_KEY = "CRYPTO-BLOBKEY";
    const HMAC_KEY = "CRYPTO-HMACKEY";

    private $cipher = "AES-256-CBC";
    private $blobKey = null;
    private $hmacKey = null;

    /**
     * @param string $blobKey Encryption key (base64-encoded, 32 bytes)
     * @param string $hmacKey Checksum key (base64-encoded, 32 bytes)
     */
    private function __construct($blobKey, $hmacKey)
    {
        $this->blobKey = $blobKey;
        $this->hmacKey = $hmacKey;
    }

    /**
     * Gets an instance of the Crypto class.
     * Keys are auto-gernerated and saved as a module system setting.
     * If no module is given, the keys are derived from the environment (user, project).
     * 
     * @param AbstractExternalModule $module The module instance wanting to use Crypto (optional)
     */
    public static function init($module = null) {
        if (!function_exists("openssl_encrypt")) {
            throw new Exception("OpenSSL functions are not available!");
        }
        if ($module) {
            $blobKey = $module->framework->getSystemSetting(self::BLOB_KEY);
            if ($blobKey == null) {
                $blobKey = self::genKey();
                $module->framework->setSystemSetting(self::BLOB_KEY, $blobKey);
            }
            $hmacKey = $module->framework->getSystemSetting(self::HMAC_KEY);
            if ($hmacKey == null) {
                $hmacKey = Crypto::genKey();
                $module->framework->setSystemSetting(self::HMAC_KEY, $hmacKey);
            }
        }
        else {
            $rc_salt = $GLOBALS["salt"];
            $userid = $GLOBALS["userid"];
            $proj_salt = $GLOBALS["Proj"]->project["__SALT__"];
            $proj_id = $GLOBALS["Proj"]->project_id;
            $blobKey = hash("sha256", "BlobKey-$rc_salt-$userid-$proj_salt-$proj_id");
            $hmacKey = hash("md5", "HmacKey-$blobKey");
            $blobKey = base64_encode(substr($blobKey, 0, 32));
            $hmacKey = base64_encode(substr($hmacKey.$hmacKey, 0, 32));
        }
        return new self($blobKey, $hmacKey);
    }


    /**
     * Encrytps data using AES-256-CBC.
     * @param mixed $data The data to be encrypted. It must be JSON-encodable.
     * @return string Base64-encoded encrypted blob.
     */
    public function encrypt($data)
    {
        $this->checkKeys();
        $payload = array (
            "data" => $data,
            "random" => base64_encode(openssl_random_pseudo_bytes(20))
        );
        $jsonData = json_encode($payload);
        $key = base64_decode($this->blobKey);
        $ivLen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $aesData = openssl_encrypt($jsonData, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $aesData, $this->hmacKey, true);
        $blob = base64_encode($iv.$hmac.$aesData);
        return $blob;
    }

    /**
     * Decrypts a base64-encoded blob.
     * @param string $blob The encrypted blob (base64-encoded).
     * @return mixed the original data.
     */
    public function decrypt($blob) 
    {
        $this->checkKeys();
        $raw = base64_decode($blob);
        $key = base64_decode($this->blobKey);
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = substr($raw, 0, $ivlen);
        $blobHmac = substr($raw, $ivlen, 32);
        $aesData = substr($raw, $ivlen + 32);
        $jsonData = openssl_decrypt($aesData, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        $calcHmac = hash_hmac('sha256', $aesData, $this->hmacKey, true);
        // Only return data if the hashes match.
        if (hash_equals($blobHmac, $calcHmac)) {
            $payload = json_decode($jsonData, true);
            $data = $payload["data"];
            return $data;
        }
        return null;
    }

    private function checkKeys() 
    {
        if (!strlen($this->blobKey) || !strlen($this->hmacKey)) {
            throw new Exception("Must set keys first!");
        }
        if (strlen(base64_decode($this->blobKey)) != 32) {
            throw new Exception("Blob key is not of the correct size");
        }
        if (strlen(base64_decode($this->hmacKey)) != 32) {
            throw new Exception("HMAC key is not of the correct size");
        }
        if ($this->blobKey == $this->hmacKey) {
            throw new Exception("Blob and HMAC keys must not be identical");
        }
    }

    /**
     * Generates a key (32 bytes) that can be used for encryption.
     */
    public static function genKey()
    {
        $key = openssl_random_pseudo_bytes(32);
        return base64_encode($key);
    }

    /**
     * Generates a Guid in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.
     * @return string A Guid in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.
     */
    public static function getGuid() 
    {
        if (function_exists('com_create_guid') === true) {
            return strtolower(trim(com_create_guid(), '{}'));
        }
        return strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)));
    }
}