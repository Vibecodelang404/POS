<?php
class AuthSecurity {
    private const PRIVATE_KEY_PATH = APP_ROOT . '/keys/login_private.pem';
    private const PUBLIC_KEY_PATH = APP_ROOT . '/keys/login_public.pem';

    public static function getPublicKey(): string {
        if (!file_exists(self::PUBLIC_KEY_PATH)) {
            throw new RuntimeException('Login public key is not configured.');
        }

        $key = file_get_contents(self::PUBLIC_KEY_PATH);
        if ($key === false || trim($key) === '') {
            throw new RuntimeException('Login public key could not be loaded.');
        }

        return $key;
    }

    public static function getIncomingPassword(array $input): string {
        $encryptedPassword = trim((string) ($input['encrypted_password'] ?? ''));
        if ($encryptedPassword !== '') {
            return self::decryptPassword($encryptedPassword);
        }

        return (string) ($input['password'] ?? '');
    }

    public static function decryptPassword(string $encryptedPassword): string {
        if (!file_exists(self::PRIVATE_KEY_PATH)) {
            throw new RuntimeException('Login private key is not configured.');
        }

        $privateKeyPem = file_get_contents(self::PRIVATE_KEY_PATH);
        if ($privateKeyPem === false || trim($privateKeyPem) === '') {
            throw new RuntimeException('Login private key could not be loaded.');
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('Login private key is invalid.');
        }

        $decodedPayload = base64_decode($encryptedPassword, true);
        if ($decodedPayload === false) {
            throw new RuntimeException('Encrypted login payload is not valid base64.');
        }

        $decrypted = '';
        $success = openssl_private_decrypt($decodedPayload, $decrypted, $privateKey, OPENSSL_PKCS1_OAEP_PADDING);
        if ($success !== true) {
            throw new RuntimeException('Encrypted login payload could not be decrypted.');
        }

        return $decrypted;
    }
}
?>
