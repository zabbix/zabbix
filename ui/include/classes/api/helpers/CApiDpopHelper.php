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
	 * @throws APIException
	 */
	public static function verifyDpopSignature(string $signature, array $kid_keys, string $access_token,
			string $requested_api_method, int $check_time): void {
		$segments = explode('.', $signature);

		if (count($segments) != 3) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Wrong number of JWT segments.');
		}

		[$encoded_header, $encoded_payload] = $segments;

		$header = json_decode(JWT::urlsafeB64Decode($encoded_header), true);

		if (!is_array($header) || !$header) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Invalid header encoding.');
		}

		self::checkKid($header, $kid_keys);

		$payload = json_decode(JWT::urlsafeB64Decode($encoded_payload), true);

		if (!is_array($payload) || !$payload) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Invalid payload encoding.');
		}

		self::checkHtu($payload, $requested_api_method);

		self::checkAth($payload, $access_token);

		self::checkTokenLifeTime($payload, $check_time);

		self::checkJti($payload);

		self::checkSignature($signature, $header, $kid_keys, $check_time);
	}

	/**
	 * @throws APIException
	 */
	private static function checkKid(array $header, array $kid_keys): void {
		if (!array_key_exists('kid', $header)) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Missing kid in DPoP header.');
		}

		if (!array_key_exists($header['kid'], $kid_keys)) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'),
				'Unknown identity key for provided kid.'
			);
		}
	}

	/**
	 * @throws APIException
	 */
	private static function checkHtu(array $payload, string $requested_api_method): void {
		if (!array_key_exists('htu', $payload)) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Missing htu claim.');
		}

		// todo - use method get server_id
		$expected_htu = 'urn:zbx:'.self::getServerId().':'.$requested_api_method;

		if (!hash_equals($expected_htu, $payload['htu'])) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Invalid htu value.');
		}
	}

	/**
	 * @throws APIException
	 */
	private static function checkAth(array $payload, string $access_token): void {
		if (!array_key_exists('ath', $payload)) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Missing ath claim.');
		}

		$expected_ath = JWT::urlsafeB64Encode(hash('sha256', $access_token, true));

		if (!hash_equals($expected_ath, $payload['ath'])) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Invalid ath value.');
		}
	}

	/**
	 * @throws APIException
	 */
	private static function checkTokenLifeTime(array $payload, int $check_time): void {
		if (!array_key_exists('iat', $payload)) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Missing iat claim.');
		}

		if (!array_key_exists('exp', $payload)) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Missing exp claim.');
		}

		$iat = (int) $payload['iat'];
		$exp = (int) $payload['exp'];

		if ($iat > $exp) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'iat exceeds exp.');
		}

		if ($iat > $check_time + self::TIME_SKEW) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'),
				'Invalid iat: JWT token issued in the future beyond allowed skew.'
			);
		}

		if ($check_time > $exp + self::TIME_SKEW) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'JWT token expired.');
		}

		if ($check_time > $iat + self::MAX_ALLOWED_IAT_AGE + self::TIME_SKEW) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'),
				'JWT token exceeded maximum allowed lifetime.'
			);
		}
	}

	/**
	 * @throws APIException
	 */
	private static function checkJti(array $payload): void {
		if (!array_key_exists('jti', $payload)) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Missing jti claim.');
		}

		if (DBfetch(DBselect('SELECT jti FROM dpop_jti_cache WHERE jti='.zbx_dbstr($payload['jti'])))) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'jti already used.');
		}

		$dpop_jti_cache_ins = [
			'jti' => $payload['jti'],
			'expires_at' => $payload['exp']
		];

		DB::insertBatch('dpop_jti_cache', [$dpop_jti_cache_ins], false);
	}

	private static function checkSignature(string $signature, array $header, array $kid_keys, int $check_time): void {
		JWT::$timestamp = $check_time;
		JWT::$leeway = self::TIME_SKEW;

		$jwk = json_decode($kid_keys[$header['kid']], true);

		try {
			$key = JWK::parseKey($jwk, self::SIGNATURE_ALGORITHM);

			JWT::decode($signature, $key);
		}
		catch (Exception $e) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), $e->getMessage().'.');
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
