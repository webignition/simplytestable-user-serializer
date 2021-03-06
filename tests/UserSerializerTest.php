<?php

namespace Tests\WebClientBundle\Functional\Services;

use webignition\SimplyTestableUserModel\User;
use webignition\SimplyTestableUserSerializer\InvalidCipherTextException;
use webignition\SimplyTestableUserSerializer\InvalidHmacException;
use webignition\SimplyTestableUserSerializer\UserSerializer;

class UserSerializerServiceTest extends \PHPUnit_Framework_TestCase
{
    const USER_USERNAME = 'username-value';
    const USER_PASSWORD = 'password-value';

    /**
     * @var UserSerializer
     */
    private $userSerializer;

    /**
     * @var User
     */
    private $user;

    /**
     * @var string
     */
    private $userAsString;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->userSerializer = new UserSerializer(md5('foo'));

        $this->user = new User(self::USER_USERNAME, self::USER_PASSWORD);
        $this->userAsString = $this->userSerializer->serializeToString($this->user);
    }

    /**
     * @throws InvalidHmacException
     * @throws InvalidCipherTextException
     */
    public function testSerializeDeserialize()
    {
        $serializedUser = $this->userSerializer->serialize($this->user);

        $this->assertInternalType('array', $serializedUser);
        $this->assertEquals([
            UserSerializer::SERIALIZED_USER_USERNAME_KEY,
            UserSerializer::SERIALIZED_USER_PASSWORD_KEY,
            UserSerializer::SERIALIZED_USER_KEY_KEY,
        ], array_keys($serializedUser));

        $this->assertEquals($this->user, $this->userSerializer->deserialize($serializedUser));
    }

    public function testSerializeToString()
    {
        $this->assertInternalType('string', $this->userAsString);
    }

    /**
     * @dataProvider deserializeFromStringFailureDataProvider
     *
     * @param string $userAsString
     * @param User|null $expectedUser
     *
     * @throws InvalidHmacException
     * @throws InvalidCipherTextException
     */
    public function testDeserializeFromStringFailure($userAsString, $expectedUser)
    {
        $user = $this->userSerializer->deserializeFromString($userAsString);

        $this->assertEquals($expectedUser, $user);
    }

    /**
     * @return array
     */
    public function deserializeFromStringFailureDataProvider()
    {
        $empty = base64_encode(json_encode([]));

        $invalid = base64_encode(json_encode([
            'foo' => 'bar',
        ]));

        $validKeysEmptyValues = base64_encode(json_encode([
            UserSerializer::SERIALIZED_USER_USERNAME_KEY => 'username',
            UserSerializer::SERIALIZED_USER_PASSWORD_KEY => 'password',
            UserSerializer::SERIALIZED_USER_KEY_KEY => '',
        ]));

        return [
            'not an array' => [
                'userAsString' => 'foo',
                'expectedUser' => null,
            ],
            'empty array' => [
                'userAsString' => $empty,
                'expectedUser' => null,
            ],
            'invalid array' => [
                'userAsString' => $invalid,
                'expectedUser' => null,
            ],
            'empty values' => [
                'userAsString' => $validKeysEmptyValues,
                'expectedUser' => null,
            ],
        ];
    }

    /**
     * @throws InvalidHmacException
     * @throws InvalidCipherTextException
     */
    public function testDeserializeFromStringInvalidCipherTextException()
    {
        $userAsStringWithInvalidIv = 'eyJ1c2VybmFtZSI6Ik01bEVNaWpzREVIeW5RTEZOUjJnb09UWWRaQWdVTkhqVzlZQkJYeGdjcmM9Iiw'.
            'icGFzc3dvcmQiOiJ0QXpNQTB1empRT1hQV1wvenpoTjZHSVFoZkNQcDNEc21saWFNS0JOOTl3RT0iLCJrZXkiOiJweWFXN2hsSVYwXC9'.
            'ocVR2dUpXRTd6UFpwU2R6QnJxQzBQMkpcL09LMndFRWs9IiwiaXYiOiJNOGFUOG1YWW5rQUhrRzdTUXFvMTZhWmo5MnJScnVDZ1FONnV'.
            'WcUxDS1VBPSJ9';

        $this->expectException(InvalidCipherTextException::class);

        $this->userSerializer->deserializeFromString($userAsStringWithInvalidIv);
    }

    /**
     * @throws InvalidHmacException
     * @throws InvalidCipherTextException
     */
    public function testDeserializeFromStringInvalidHmacException()
    {
        $serializedUser = $this->userSerializer->serialize($this->user);
        $cipherUsername = $serializedUser[UserSerializer::SERIALIZED_USER_USERNAME_KEY];

        $ivSize = openssl_cipher_iv_length(UserSerializer::OPENSSL_METHOD);

        $iv = substr($cipherUsername, 0, $ivSize);
        $hmac = substr($cipherUsername, $ivSize, UserSerializer::HMAC_LENGTH);
        $rawCipherText = substr($cipherUsername, $ivSize + UserSerializer::HMAC_LENGTH);

        $mutatedCipherUsername = $iv . md5($hmac) . $rawCipherText;

        $serializedUser[UserSerializer::SERIALIZED_USER_USERNAME_KEY] = $mutatedCipherUsername;

        foreach ($serializedUser as $key => $value) {
            $serializedUser[$key] = base64_encode($value);
        }

        $userAsString = base64_encode(json_encode($serializedUser));

        $this->expectException(InvalidHmacException::class);

        $this->userSerializer->deserializeFromString($userAsString);
    }

    /**
     * @throws InvalidHmacException
     * @throws InvalidCipherTextException
     */
    public function testDeserializeFromStringSuccess()
    {
        $this->assertEquals(
            $this->user,
            $this->userSerializer->deserializeFromString($this->userAsString)
        );
    }
}
