<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Helper class containing methods for DPoP signature and JWK integrity verifications.
 */
class CApiDpopHelper {

	private const TIME_SKEW = 2;
	private const MAX_ALLOWED_IAT_AGE = 60;

	private const SIGNATURE_ALGORITHM = 'ES256';

	/**
	 * @throws Exception
	 */
	public static function verifyDpopSignature(string $signature, array $kid_keys, string $access_token,
			string $requested_api_method, int $check_time): void {
		$segments = explode('.', $signature);

		if (count($segments) != 3) {
			throw new Exception('Wrong number of JWT segments.');
		}

		[$encoded_header, $encoded_payload] = $segments;

		$header = json_decode(JWT::urlsafeB64Decode($encoded_header), true);

		if (!is_array($header) || !$header) {
			throw new Exception('Invalid header encoding.');
		}

		self::checkKid($header, $kid_keys);

		JWT::$timestamp = $check_time;
		JWT::$leeway = self::TIME_SKEW;

		$jwk = json_decode($kid_keys[$header['kid']], true);

		try {
			$key = JWK::parseKey($jwk, self::SIGNATURE_ALGORITHM);

			JWT::decode($signature, $key);
		}
		catch (Exception $e) {
			throw new Exception($e->getMessage().'.');
		}

		$payload = json_decode(JWT::urlsafeB64Decode($encoded_payload), true);

		self::checkHtu($payload, $requested_api_method);

		self::checkAth($payload, $access_token);

		self::checkTokenLifeTime($payload, $check_time);

		self::checkJti($payload, $check_time);
	}

	/**
	 * @throws Exception
	 */
	private static function checkKid(array $header, array $kid_keys): void {
		if (!array_key_exists('kid', $header)) {
			throw new Exception('Missing kid in DPoP header.');
		}

		if (!array_key_exists($header['kid'], $kid_keys)) {
			throw new Exception('Unknown identity key for provided kid.');
		}
	}

	/**
	 * @throws Exception
	 */
	private static function checkHtu(array $payload, string $requested_api_method): void {
		if (!array_key_exists('htu', $payload)) {
			throw new Exception('Missing htu claim.');
		}

		// todo - use method get server_id
		$expected_htu = 'urn:zbx:'.self::getServerId().':'.$requested_api_method;

		if (!hash_equals($expected_htu, $payload['htu'])) {
			throw new Exception('Invalid htu value.');
		}
	}

	/**
	 * @throws Exception
	 */
	private static function checkAth(array $payload, string $access_token): void {
		if (!array_key_exists('ath', $payload)) {
			throw new Exception('Missing ath claim.');
		}

		$expected_ath = JWT::urlsafeB64Encode(hash('sha256', $access_token, true));

		if (!hash_equals($expected_ath, $payload['ath'])) {
			throw new Exception('Invalid ath value.');
		}
	}

	/**
	 * @throws Exception
	 */
	private static function checkTokenLifeTime(array $payload, int $check_time): void {
		if (!array_key_exists('iat', $payload)) {
			throw new Exception('Missing iat claim.');
		}

		if (!array_key_exists('exp', $payload)) {
			throw new Exception('Missing exp claim.');
		}

		$iat = (int) $payload['iat'];
		$exp = (int) $payload['exp'];

		if ($iat > $exp) {
			throw new Exception('iat exceeds exp.');
		}

		if ($iat > $check_time + self::TIME_SKEW) {
			throw new Exception('Invalid iat: JWT token issued in the future beyond allowed skew.');
		}

		if ($exp - $iat <= self::MAX_ALLOWED_IAT_AGE) {
			if ($check_time > $exp + self::TIME_SKEW) {
				throw new Exception('JWT token expired.');
			}
		}
		elseif ($check_time > $iat + self::MAX_ALLOWED_IAT_AGE + self::TIME_SKEW) {
			throw new Exception('JWT token exceeded maximum allowed lifetime.');
		}
	}

	/**
	 * @throws Exception
	 */
	private static function checkJti(array $payload, int $check_time): void {
		if (!array_key_exists('jti', $payload)) {
			throw new Exception('Missing jti claim.');
		}

		DBexecute('DELETE FROM dpop_jti_cache WHERE expires_at<'.$check_time);

		if (DBfetch(DBselect('SELECT jti FROM dpop_jti_cache WHERE jti='.zbx_dbstr($payload['jti'])))) {
			throw new Exception('Replay detected: jti already used.');
		}

		if (!DBexecute(
				'INSERT INTO dpop_jti_cache (jti, expires_at)'.
					' VALUES ('.zbx_dbstr($payload['jti']).', '.zbx_dbstr($payload['exp']).')')) {
			throw new Exception('Internal error: unable to persist jti.');
		}
	}

	public static function checkJwkIntegrity(array $jwk): bool {
		try {
			$key = JWK::parseKey($jwk, self::SIGNATURE_ALGORITHM);
		}
		catch (Exception $e) {
			return false;
		}

		if (openssl_pkey_get_public($key->getKeyMaterial()) === false) {
			return false;
		}

		return true;
	}

	// todo - REMOVE THIS AFTER REPLACING WITH ORIGINAL METHODS

	public static function getServerId(): string {
		return 'server_id';
	}
}
