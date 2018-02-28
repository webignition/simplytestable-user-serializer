<?php

namespace Tests\WebClientBundle\Functional\Services;

use webignition\SimplyTestableUserModel\User;
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

        $this->userSerializer = new UserSerializer('foo');

        $this->user = new User(self::USER_USERNAME, self::USER_PASSWORD);
        $this->userAsString = $this->userSerializer->serializeToString($this->user);
    }

    public function testSerializeDeserialize()
    {
        $serializedUser = $this->userSerializer->serialize($this->user);

        $this->assertInternalType('array', $serializedUser);
        $this->assertEquals([
            UserSerializer::SERIALIZED_USER_USERNAME_KEY,
            UserSerializer::SERIALIZED_USER_PASSWORD_KEY,
            UserSerializer::SERIALIZED_USER_KEY_KEY,
            UserSerializer::SERIALIZED_USER_IV_KEY,
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
     */
    public function testDeserializeFromStringFailure($userAsString, $expectedUser)
    {
        if ($userAsString === '{{userAsString}}') {
            $userAsString = $this->userAsString;
        }

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
            UserSerializer::SERIALIZED_USER_IV_KEY => '',
        ]));

        return [
            'not an array' => [
                'stringifiedUser' => 'foo',
                'expectedUser' => null,
            ],
            'empty array' => [
                'stringifiedUser' => $empty,
                'expectedUser' => null,
            ],
            'invalid array' => [
                'stringifiedUser' => $invalid,
                'expectedUser' => null,
            ],
            'empty values' => [
                'stringifiedUser' => $validKeysEmptyValues,
                'expectedUser' => null,
            ],
        ];
    }

    public function testUnserializeFromStringSuccess()
    {
        $this->assertEquals(
            $this->user,
            $this->userSerializer->deserializeFromString($this->userAsString)
        );
    }
}
