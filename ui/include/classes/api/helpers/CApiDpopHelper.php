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


/**
 * Helper class containing methods for DPoP signature and JWK integrity verifications.
 */
class CApiDpopHelper {

	private const TIME_SKEW = 2;
	private const MAX_ALLOWED_IAT_AGE = 60;

	protected const ASN1_INTEGER 		 = 0x02;
	private const ASN1_BIT_STRING        = 0x03;
	private const ASN1_OBJECT_IDENTIFIER = 0x06;
	protected const ASN1_SEQUENCE        = 0x30;

	private const OID_EC_PUBLIC_KEY = '1.2.840.10045.2.1';
	private const OID_PRIME256V1    = '1.2.840.10045.3.1.7';

	/**
	 * @throws APIException
	 */
	public static function verifyDpopSignature(string $dpop_token, array $kid_keys, string $access_token,
			string $requested_api_method, int $check_time): void {
		$segments = explode('.', $dpop_token);

		if (count($segments) != 3) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Wrong number of JWT segments.');
		}

		[$encoded_header, $encoded_payload, $encoded_signature] = $segments;

		$header = json_decode(self::base64UrlDecode($encoded_header), true);

		if (!is_array($header) || !$header) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Invalid header encoding.');
		}

		self::checkKid($header, $kid_keys);

		$payload = json_decode(self::base64UrlDecode($encoded_payload), true);

		if (!is_array($payload) || !$payload) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Invalid payload encoding.');
		}

		self::checkHtu($payload, $requested_api_method);

		self::checkAth($payload, $access_token);

		self::checkTokenLifeTime($payload, $check_time);

		$jwk = json_decode($kid_keys[$header['kid']], true);

		$public_key_pem = self::createPemFromJwk($jwk);

		$der_signature = self::jwtSignatureToDerEncode(self::base64UrlDecode($encoded_signature));

		$result = openssl_verify($encoded_header.'.'.$encoded_payload, $der_signature, $public_key_pem,
			OPENSSL_ALGO_SHA256
		);

		if ($result == 0) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'), 'Signature verification failed.');
		}
		elseif ($result == -1) {
			throw new APIException(ZBX_API_ERROR_NO_AUTH, _('Not authorized.'),
				'OpenSSL error: '.openssl_error_string()
			);
		}

		self::checkJti($payload);
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

		$expected_htu = 'urn:zbx:'.CSettingsHelper::get(CSettingsHelper::SERVER_ID).':'.$requested_api_method;

		if (!is_string($payload['htu']) || !hash_equals($expected_htu, $payload['htu'])) {
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

		$expected_ath = self::base64UrlEncode(hash('sha256', $access_token, true));

		if (!is_string($payload['ath']) || !hash_equals($expected_ath, $payload['ath'])) {
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

	public static function checkJwkIntegrity(array $jwk): bool {
		if (openssl_pkey_get_public(self::createPemFromJwk($jwk)) === false) {
			return false;
		}

		return true;
	}

	/**
	 * Convert JWT ES256 signature (R || S) into ASN.1 DER format. The encoding rules are defined in RFC 3279.
	 *
	 * @param string $signature
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	private static function jwtSignatureToDerEncode(string $signature): string {
		if (strlen($signature) != 64) {
			throw new Exception('Invalid signature length.');
		}

		$r = substr($signature, 0, 32);
		$s = substr($signature, 32, 32);

		return self::asn1SequenceEncode(self::asn1IntegerEncode($r).self::asn1IntegerEncode($s));
	}

	/**
	 * Builds a PEM encoded EC public key from JWK (P-256). The encoding rules are defined in RFC 5480, 7468.
	 *
	 * @param array $jwk
	 *
	 * @return string
	 */
	private static function createPemFromJwk(array $jwk): string {
		$algorithm_identifier = self::asn1SequenceEncode(self::asn1ObjectIdentifierEncode(self::OID_EC_PUBLIC_KEY).
			self::asn1ObjectIdentifierEncode(self::OID_PRIME256V1)
		);

		// Uncompressed point (0x04) and 32-byte coordinates (P-256).
		$ec_point = chr(0x04).self::base64UrlDecode($jwk['x']).self::base64UrlDecode($jwk['y']);

		$subject_public_key = self::asn1BitStringEncode($ec_point);

		$subject_public_key_info = self::asn1SequenceEncode($algorithm_identifier.$subject_public_key);

		return "-----BEGIN PUBLIC KEY-----\n".
			chunk_split(base64_encode($subject_public_key_info), 64, "\n").
			"-----END PUBLIC KEY-----\n";
	}

	public static function base64UrlEncode(string $data): string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	private static function base64UrlDecode(string $data): string {
		$padding_length = 4 - strlen($data) % 4;

		if ($padding_length < 4) {
			$data .= str_repeat('=', $padding_length);
		}

		return base64_decode(strtr($data, '-_', '+/'));
	}

	/**
	 * Encode ASN.1 DER SEQUENCE. The encoding rules are defined in ITU-T X.690 section 8.9.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	private static function asn1SequenceEncode(string $data): string {
		return chr(self::ASN1_SEQUENCE).self::asn1Length($data).$data;
	}

	/**
	 * Encode ASN.1 DER OBJECT IDENTIFIER (OID). The encoding rules are defined in ITU-T X.690 section 8.19.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	private static function asn1ObjectIdentifierEncode(string $data): string {
		$parts = array_map('intval', explode('.', $data));

		// ASN.1 encodes first two OID components as first * 40 + second.
		$first_byte = 40 * array_shift($parts) + array_shift($parts);

		$encoded = chr($first_byte);

		foreach ($parts as $part) {
			$bin = '';

			do {
				// Extract lowest 7 bits.
				$byte = $part & 0x7f;

				$part = $part >> 7;

				// Set continuation bit.
				if ($bin !== '') {
					$byte |= 0x80;
				}

				// Prepend byte to build big-endian representation.
				$bin = chr($byte).$bin;
			}
			while ($part > 0);

			$encoded .= $bin;
		}

		return chr(self::ASN1_OBJECT_IDENTIFIER).self::asn1Length($encoded).$encoded;
	}

	/**
	 * Encode ASN.1 DER BIT STRING. The encoding rules are defined in ITU-T X.690 section 8.6.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	private static function asn1BitStringEncode(string $data): string {
		// EC public key are byte-aligned, therefore unused bits = 0.
		$data = chr(0).$data;

		return chr(self::ASN1_BIT_STRING).self::asn1Length($data).$data;
	}

	/**
	 * Encode ASN.1 DER INTEGER. The encoding rules are defined in ITU-T X.690 sections 8.3.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	private static function asn1IntegerEncode(string $data): string {
		// Remove unnecessary leading zero bytes.
		$data = ltrim($data, chr(0));

		if ($data === '') {
			$data = chr(0);
		}

		// If highest bit is set, prepend 00 byte to keep integer positive.
		if ((ord($data[0]) & 0x80) != 0) {
			$data = chr(0).$data;
		}

		return chr(self::ASN1_INTEGER).self::asn1Length($data).$data;
	}

	/**
	 * Encode ASN.1 DER length field. Length encoding rules are defined in ITU-T X.690 sections 8.1.3 and 10.1.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	private static function asn1Length(string $data): string {
		$length = strlen($data);

		// DER short form lengths: 0..127 encoded as single byte.
		if ($length < 0x80) {
			return chr($length);
		}

		// DER long form lengths.
		$bytes = '';

		while ($length > 0) {
			// Extract lowest 8 bits and prepend byte to build big-endian representation.
			$bytes = chr($length & 0xff).$bytes;

			$length >>= 8;
		}

		return chr(0x80 | strlen($bytes)).$bytes;
	}
}
