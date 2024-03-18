<?php declare(strict_types=1);
namespace Duo\Tests;

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public $username = "user";
    public $bad_username = "baduser";
    public $code = "abcdefghijkl";
    public $bad_expiration = 1234567;
    public $nonce = "deadbeefdeadbeefdeadbeef";
    public $bad_nonce = "beefdeadbeefdeadbeef";
    public $client_id = "12345678901234567890";
    public $client_secret = "1234567890123456789012345678901234567890";
    public $api_host = "api-123456.duo.com";
    public $redirect_url = "https://redirect_example.com";
    public $url_enc_redirect_url = "https%3A%2F%2Fredirect_example.com";
    public $bad_client_id = "1234567890123456789";
    public $long_client_secret = "1234567890123456789012345678901234567890000";
    public $bad_client_secret = "1111111111111111111111111111111111111111";
    public $bad_api_host = 123456;
    public $good_http_request = ["response" => ["timestamp" => 1607009339],
                                 "stat" => "OK"];
    public $bad_http_request = ["message" => "invalid_client",
                                "code" => 40002,
                                "timestamp" => 1607014550,
                                "message_detail" => "Failed to verify signature.",
                                "stat" => "FAIL"];
    public $missing_stat_health_check = ["response" => ["timestamp" => 1607009339]];
    public $missing_message_health_check = ["stat" => "Fail"];
    public $good_state = "deadbeefdeadbeefdeadbeefdeadbeefdead";
    public $short_state = "deadbeefdeadbeefdeadb";
    public $bad_http_request_exception = "invalid_client: Failed to verify signature.";
    public $expected_good_http_request = array("response" => array("timestamp" => 1607009339),
                                         "stat" => "OK");


    protected function setUp(): void
    {
        // null is the default behavior and signifies that JWT will use the real current timestamp
        JWT::$timestamp = null;
    }

    /**
     * Create Client
     */
    public function createGoodClient(): Client
    {
        return new Client(
            $this->client_id,
            $this->client_secret,
            $this->api_host,
            $this->redirect_url
        );
    }

    /**
     * Create Client with mocked out makeHttpsCall() to return $result
     *
     * @param array $result             The data makeHttpsCall will return when running test
     * @param string $bad_client_secret (Optional) Use bad client secret to create client
     */
    public function createClientMockHttp(array $result, string $bad_client_secret = '')
    {
        $client_secret = $bad_client_secret ? $bad_client_secret : $this->client_secret;
        $client = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$this->client_id, $client_secret, $this->api_host, $this->redirect_url])
            ->setMethods(['makeHttpsCall'])
            ->getMock();
        $client->method('makeHttpsCall')
            ->will($this->returnValue($result));
        return $client;
    }

    /**
     * Creates and signs jwt to be used for id_token in createTokenResult.
     *
     * @param string|null           $remove_index Removes entry in $payload
     * @param array<string, string> $change_val   Changes entry for key to new value in $payload
     *
     * @return string encoded JWT
     */
    public function createIdToken(?string $remove_index = null, array $change_val = []): string
    {
        $date = new \DateTime();
        $current_date = $date->getTimestamp();
        $payload = ["exp" => $current_date + Client::JWT_EXPIRATION,
                "iat" => $current_date,
                "iss" => "https://" . $this->api_host . Client::TOKEN_ENDPOINT,
                "aud" => $this->client_id,
                "preferred_username" => $this->username,
                "nonce" => $this->nonce
        ];
        if ($remove_index) {
            unset($payload[$remove_index]);
        }
        if ($change_val) {
            $payload[key($change_val)] = $change_val[key($change_val)];
        }
        return JWT::encode($payload, $this->client_secret, Client::SIG_ALGORITHM);
    }

    /**
     * Create token result returned From Duo after exchange with code.
     *
     * @param string $id_token     A signed JWT
     *
     * @return array An array containing the token data
     */
    public function createTokenResult(string $id_token = ''): array
    {
        if (!$id_token) {
            $id_token = $this->createIdToken();
        }
        return ["id_token" => $id_token,
                "access_token" => "90101112",
                "expires_in" => "1234567890",
                "token_type" => "Bearer"];
    }

    /**
     * Test that creating a client with proper inputs does not throw an error.
     */
    public function testClientGood(): void
    {
        $client = $this->createGoodClient();
        $this->assertInstanceOf(Client::class, $client);
    }

    /**
     * Test that an invalid client_id will cause the Client to throw a DuoException
     */
    public function testClientBadClientId(): void
    {
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::INVALID_CLIENT_ID_ERROR);
        $client = new Client(
            $this->bad_client_id,
            $this->client_secret,
            $this->api_host,
            $this->redirect_url
        );
    }

    /**
     * Test that an invalid client_secret
     * will cause the Client to throw a DuoException
     */
    public function testClientBadClientSecret(): void
    {
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::INVALID_CLIENT_SECRET_ERROR);
        $client = new Client(
            $this->client_id,
            $this->long_client_secret,
            $this->api_host,
            $this->redirect_url
        );
    }

    /**
     * Test that generateState does not return the same
     * string twice.
     */
    public function testGenerateState(): void
    {
        $client = $this->createGoodClient();
        $string_1 = $client->generateState();
        $this->assertNotEquals(
            $string_1,
            $client->generateState()
        );
    }

    /**
     * Test that a successful health check returns a successful result.
     */
    public function testHealthCheckGood(): void
    {
        $client = $this->createClientMockHttp($this->good_http_request);
        $result = $client->healthCheck();
        $this->assertEquals($this->expected_good_http_request, $result);
    }

    /**
     * Test that a failed connection to Duo throws a FAILED_CONNECTION exception.
     */
    public function testHealthCheckConnectionFail(): void
    {
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::FAILED_CONNECTION);
        $client = $this->getMockBuilder(Client::class)
            ->setConstructorArgs([$this->client_id, $this->client_secret, $this->api_host, $this->redirect_url])
            ->setMethods(['makeHttpsCall'])
            ->getMock();
        $client->method('makeHttpsCall')
            ->will($this->throwException(new DuoException(Client::FAILED_CONNECTION)));
        $client->healthCheck();
    }

    /**
     * Test that when Duo is down the client throws an error
     */
    public function testHealthCheckBadSig(): void
    {
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage($this->bad_http_request_exception);
        $client = $this->createClientMockHttp($this->bad_http_request);
        $client->healthCheck();
    }

    /**
     * Test that if the health check response is missing stat then the client throws an error.
     */
    public function testHealthCheckMissingStat(): void
    {
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::MALFORMED_RESPONSE);
        $client = $this->createClientMockHttp($this->missing_stat_health_check);
        $client->healthCheck();
    }

    /**
     * Test that if the health check failed and the response is malformed then the client throws an error.
     */
    public function testHealthCheckMissingMessage(): void
    {
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::MALFORMED_RESPONSE);
        $client = $this->createClientMockHttp($this->missing_message_health_check);
        $client->healthCheck();
    }

    /**
     * @dataProvider providerMissingResponseField
     */
    public function testMissingResponseField($missing_field): void
    {
        $result = $this->createTokenResult();
        unset($result[$missing_field]);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::MALFORMED_RESPONSE);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
    }

    /**
     * Provides a list of missing fields for the response when hitting the TOKEN_ENDPOINT.
     */
    public function providerMissingResponseField(): array
    {
        return [
            ["token_type"],
            ["access_token"],
            ["expires_in"],
            ["id_token"]
        ];
    }
    /**
     * Test bad token_type in response during token exchange throws an error.
     */
    public function testTokenExchangeBadTokenType(): void
    {
        $result_good = $this->createTokenResult();
        $result = str_replace('Bearer', 'BadTokenType', $result_good);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::MALFORMED_RESPONSE);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
    }

    /**
     * Test bad nonce in id_token during token exchange throws an error.
     */
    public function testTokenExchangeBadNonce(): void
    {
        $payload = $this->createIdToken("nonce");
        $result = $this->createTokenResult($payload);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::NONCE_ERROR);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username, $this->bad_nonce);
    }

    /**
     * Test bad JWT signature for id_token during token exchange throws an error.
     */
    public function testTokenExchangeBadSig(): void
    {
        $result = $this->createTokenResult();
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::JWT_DECODE_ERROR);
        $client = $this->createClientMockHttp($result, $this->bad_client_secret);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
    }

    /**
     * Test expired id_token during token exchange throws an error.
     */
    public function testTokenExchangeExpired(): void
    {
        $expired = ["exp" => $this->bad_expiration];
        $payload = $this->createIdToken(null, $expired);
        $result = $this->createTokenResult($payload);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::JWT_DECODE_ERROR);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
    }

    /**
     * Test clock skew more than leeway throws an error.
     */
    public function testTokenExchangeLargeClockSkew(): void
    {
        // Simulate a clock skew (greater than the leeway) by feeding JWT a slightly different timestamp.
        JWT::$timestamp = time() - Client::JWT_LEEWAY * 2;

        $payload = $this->createIdToken();
        $result = $this->createTokenResult($payload);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::JWT_DECODE_ERROR);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
    }

    /**
     * Test clock skew less than leeway is successful.
     */
    public function testTokenExchangeSmallClockSkew(): void
    {
        // Simulate a clock skew (smaller than the leeway) by feeding JWT a slightly different timestamp.
        JWT::$timestamp = time() - Client::JWT_LEEWAY / 2;

        $payload = $this->createIdToken();
        $result = $this->createTokenResult($payload);
        $client = $this->createClientMockHttp($result);
        $token = $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
        $this->assertEquals($this->username, $token['preferred_username']);
    }

    /**
     * @dataProvider providerMissingField
     */
    public function testMissingField(string $missing_field, string $expected_response): void
    {
        $payload = $this->createIdToken($missing_field);
        $result = $this->createTokenResult($payload);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage($expected_response);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username, $this->nonce);
    }

    /**
     * Provides a list of missing fields and expected expections
     * for the id_token in the response when hitting the TOKEN_ENDPOINT.
     */
    public function providerMissingField(): array
    {
        return [
            [ "exp", Client::MALFORMED_RESPONSE],
            [ "iat", Client::MALFORMED_RESPONSE],
            [ "iss", Client::MALFORMED_RESPONSE],
            [ "aud", Client::MALFORMED_RESPONSE],
            [ "nonce", Client::NONCE_ERROR],
            [ "preferred_username", Client::USERNAME_ERROR ]
        ];
    }

    /**
     * Test bad iss in id_token during token exchange throws an error.
     */
    public function testTokenExchangeBadIss(): void
    {
        $bad_iss = ["iss" => "https://" . $this->bad_api_host . Client::TOKEN_ENDPOINT];
        $payload = $this->createIdToken(null, $bad_iss);
        $result = $this->createTokenResult($payload);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::MALFORMED_RESPONSE);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
    }

    /**
     * Test bad aud in id_token during token exchange throws an error.
     */
    public function testTokenExchangeBadAud(): void
    {
        $bad_aud = ["aud" => $this->bad_client_id];
        $payload = $this->createIdToken(null, $bad_aud);
        $result = $this->createTokenResult($payload);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::MALFORMED_RESPONSE);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
    }

    /**
     * Test wrong preferred_username in id_token during token exchange throws an error.
     */
    public function testTokenExchangeBadUsername(): void
    {
        $bad_aud = ["preferred_username" => $this->bad_username];
        $payload = $this->createIdToken(null, $bad_aud);
        $result = $this->createTokenResult($payload);
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::USERNAME_ERROR);
        $client = $this->createClientMockHttp($result);
        $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
    }

    /**
     * Test a successful token exchange.
     */
    public function testTokenExchangeSuccess(): void
    {
        $id_token = $this->createIdToken();
        $result = $this->createTokenResult($id_token);
        $jwt_key = new Key($this->client_secret, Client::SIG_ALGORITHM);
        $expected_result_obj = JWT::decode($id_token, $jwt_key);
        $expected_result = json_decode(json_encode($expected_result_obj), true);
        $client = $this->createClientMockHttp($result);
        $exchange_result = $client->exchangeAuthorizationCodeFor2FAResult($this->code, $this->username);
        $this->assertEquals($expected_result, $exchange_result);
    }

    /**
     * @dataProvider providerState
     */
    public function testCreateAuthUrlState(string $state): void
    {
        $this->expectException(DuoException::class);
        $this->expectExceptionMessage(Client::DUO_STATE_ERROR);
        $client = $this->createGoodClient();
        $client->createAuthUrl($this->username, $state);
    }

    /**
     * Provides a list of invalid states for createAuthUrl
     */
    public function providerState(): array
    {
        $long_state = str_repeat("a", Client::MAX_STATE_LENGTH + 1);
        return [
            [$this->short_state],
            [$long_state]
        ];
    }

    /**
     * Test that by default we request the duo_code parameter in our JWT
     */
    public function testDuoCodeDefaultTrue(): void
    {
        $client = new Client(
            $this->client_id,
            $this->client_secret,
            $this->api_host,
            $this->redirect_url
        );
        $auth_url = $client->createAuthUrl($this->username, $this->good_state);
        $jwt = $this->decodeJWTFromURL($auth_url);
        $this->assertTrue($jwt["use_duo_code_attribute"]);
    }

    /**
     * Test that passing false to constructor causes our JWT not request use_duo_code_attribute
     */
    public function testDuoCodeSetFalse(): void
    {
        $client = new Client(
            $this->client_id,
            $this->client_secret,
            $this->api_host,
            $this->redirect_url,
            false
        );
        $auth_url = $client->createAuthUrl($this->username, $this->good_state);
        $jwt = $this->decodeJWTFromURL($auth_url);
        $this->assertFalse($jwt["use_duo_code_attribute"]);
    }

    /**
     * Helper to decode a JWT from a URL
     */
    public function decodeJWTFromURL(string $url): array
    {
        $query_str = parse_url($url, PHP_URL_QUERY);
        parse_str($query_str, $query_params);
        $token = $query_params["request"];
        $jwt_key = new Key($this->client_secret, Client::SIG_ALGORITHM);
        $result_obj = JWT::decode($token, $jwt_key);
        return json_decode(json_encode($result_obj), true);
    }

    /**
     * Test a successful createAuthUrl returns a good uri.
     */
    public function testCreateAuthUrlSuccess(): void
    {
        $client = $this->createGoodClient();
        $duo_uri = $client->createAuthUrl($this->username, $this->good_state);
        $expected_client_id = "client_id=" . $this->client_id;
        $expected_redir_uri = "redirect_uri=" . $this->url_enc_redirect_url;

        $this->assertStringContainsString($expected_client_id, $duo_uri);
        $this->assertStringContainsString("response_type=code", $duo_uri);
        $this->assertStringContainsString("scope=openid", $duo_uri);
        $this->assertStringContainsString($expected_redir_uri, $duo_uri);
    }
}
