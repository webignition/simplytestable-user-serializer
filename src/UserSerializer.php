<?php

namespace webignition\SimplyTestableUserSerializer;

use webignition\SimplyTestableUserModel\User;

class UserSerializer
{
    const SERIALIZED_USER_USERNAME_KEY = 'username';
    const SERIALIZED_USER_PASSWORD_KEY = 'password';
    const SERIALIZED_USER_KEY_KEY = 'key';
    const SERIALIZED_USER_IV_KEY = 'iv';

    const KEY_HASH_ALGORITHM = 'md5';
    const KEY_LENGTH_IN_BYTES = 32;
    const OPENSSL_METHOD = 'aes-256-ctr';

    /**
     * @var string
     */
    private $surrogateKey;

    /**
     * @var string
     */
    private $key;

    /**
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key = hash(self::KEY_HASH_ALGORITHM, $key);
        $this->surrogateKey = hash(
            self::KEY_HASH_ALGORITHM,
            openssl_random_pseudo_bytes(self::KEY_LENGTH_IN_BYTES)
        );
    }

    /**
     * @param User $user
     *
     * @return array
     */
    public function serialize(User $user)
    {
        return [
            self::SERIALIZED_USER_USERNAME_KEY => $this->encrypt($user->getUsername(), $this->surrogateKey),
            self::SERIALIZED_USER_PASSWORD_KEY => $this->encrypt($user->getPassword(), $this->surrogateKey),
            self::SERIALIZED_USER_KEY_KEY => $this->encrypt($this->surrogateKey, $this->key),
        ];
    }

    /**
     * @param User $user
     *
     * @return string
     */
    public function serializeToString(User $user)
    {
        $serializedUser = $this->serialize($user);

        foreach ($serializedUser as $key => $value) {
            $serializedUser[$key] = base64_encode($value);
        }

        return base64_encode(json_encode($serializedUser));
    }

    /**
     * @param string $user
     *
     * @return User
     *
     * @throws InvalidHmacException
     */
    public function deserializeFromString($user)
    {
        $base64EncodedUserValues = json_decode(base64_decode($user), true);
        if (!is_array($base64EncodedUserValues)) {
            return null;
        }

        if (empty($base64EncodedUserValues)) {
            return null;
        }

        $expectedKeys = [
            self::SERIALIZED_USER_USERNAME_KEY,
            self::SERIALIZED_USER_PASSWORD_KEY,
            self::SERIALIZED_USER_KEY_KEY,
        ];

        foreach ($expectedKeys as $expectedKey) {
            if (!isset($base64EncodedUserValues[$expectedKey])) {
                return null;
            }
        }

        $userValues = [];

        foreach ($base64EncodedUserValues as $key => $value) {
            $base64DecodedValue = base64_decode($value);
            if ($base64DecodedValue == '') {
                return null;
            }

            $userValues[$key] = $base64DecodedValue;
        }

        return $this->deserialize($userValues);
    }

    /**
     * @param array $serializedUser
     *
     * @return User
     *
     * @throws InvalidHmacException
     */
    public function deserialize($serializedUser)
    {
        $this->surrogateKey = $this->decrypt($serializedUser[self::SERIALIZED_USER_KEY_KEY], $this->key);

        $user = new User();
        $user->setUsername(
            trim($this->decrypt($serializedUser[self::SERIALIZED_USER_USERNAME_KEY], $this->surrogateKey))
        );
        $user->setPassword(
            trim($this->decrypt($serializedUser[self::SERIALIZED_USER_PASSWORD_KEY], $this->surrogateKey))
        );

        return $user;
    }

    /**
     * @param string $plaintext
     * @param string $key
     *
     * @return string
     */
    private function encrypt($plaintext, $key)
    {
        $ivSize = openssl_cipher_iv_length(self::OPENSSL_METHOD);
        $iv = openssl_random_pseudo_bytes($ivSize);

        $rawCipherText = openssl_encrypt(
            $plaintext,
            self::OPENSSL_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        $hmac = hash_hmac('sha256', $rawCipherText, $key, true);

        return base64_encode($iv . $hmac . $rawCipherText);
    }

    /**
     * @param string $cipherText
     * @param string $key
     *
     * @return string
     *
     * @throws InvalidHmacException
     */
    private function decrypt($cipherText, $key)
    {
        $data = base64_decode($cipherText);

        $ivSize = openssl_cipher_iv_length(self::OPENSSL_METHOD);

        $iv = substr($data, 0, $ivSize);
        $hmac = substr($data, $ivSize, 32);
        $rawCipherText = substr($data, $ivSize + 32);

        $plainText = openssl_decrypt($rawCipherText, self::OPENSSL_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        $calculatedHmac = hash_hmac('sha256', $rawCipherText, $key, true);

        if ($hmac !== $calculatedHmac) {
            throw new InvalidHmacException();
        }

        return $plainText;
    }
}
