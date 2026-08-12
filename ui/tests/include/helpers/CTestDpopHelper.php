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


require_once __DIR__.'/../../../include/classes/api/helpers/CApiDpopHelper.php';

/**
 * Helper for creating a JWT token.
 */
class CTestDpopHelper extends CApiDpopHelper {

	/**
	 * @throws Exception
	 */
	public static function makeJwt(array $head, array $payload, string $private_key_pem): string {
		$private_key = openssl_pkey_get_private($private_key_pem);

		if ($private_key === false) {
			throw new Exception('Invalid private key.');
		}

		$encoded_head = self::base64UrlEncode(json_encode($head, JSON_UNESCAPED_SLASHES));
		$encoded_payload = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

		$signing_data = $encoded_head.'.'.$encoded_payload;
		$der_signature = '';

		if (!openssl_sign($signing_data, $der_signature, $private_key, OPENSSL_ALGO_SHA256)) {
			throw new Exception('OpenSSL signing failed.');
		}

		$jwt_signature = self::derToJwtSignatureDecode($der_signature);

		return $signing_data.'.'.self::base64UrlEncode($jwt_signature);
	}

	/**
	 * Convert ASN.1 DER encoded ECDSA signature into JWT ES256 raw signature format.
	 *
	 * @param string $der_signature
	 *
	 * @throws Exception
	 * @return string
	 */
	private static function derToJwtSignatureDecode(string $der_signature): string {
		$offset = 0;

		if (ord($der_signature[$offset]) != self::ASN1_SEQUENCE) {
			throw new Exception('Invalid SEQUENCE type value.');
		}

		$offset++;

		// Skip sequence length.
		self::parseAsn1LengthFromContent($der_signature, $offset);

		if (ord($der_signature[$offset]) != self::ASN1_INTEGER) {
			throw new Exception('Invalid INTEGER type value for R.');
		}

		$offset++;

		$r_length = self::parseAsn1LengthFromContent($der_signature, $offset);

		$r = substr($der_signature, $offset, $r_length);

		$offset += $r_length;

		if (ord($der_signature[$offset]) != self::ASN1_INTEGER) {
			throw new Exception('Invalid INTEGER type value for S.');
		}

		$offset++;

		$s_length = self::parseAsn1LengthFromContent($der_signature, $offset);

		$s = substr($der_signature, $offset, $s_length);

		// Remove optional ASN.1 sign padding.
		$r = ltrim($r, chr(0));
		$s = ltrim($s, chr(0));

		// JWT ES256 requires fixed 32-byte values.
		$r = str_pad($r, 32, chr(0), STR_PAD_LEFT);
		$s = str_pad($s, 32, chr(0), STR_PAD_LEFT);

		return $r.$s;
	}

	private static function parseAsn1LengthFromContent(string $data, int &$offset): int {
		$length = ord($data[$offset]);

		$offset++;

		// DER short form lengths.
		if (($length & 0x80) == 0) {
			return $length;
		}

		// DER long form length.
		// Lower 7 bits specify how many bytes encode the actual length value.
		$num_bytes = $length & 0x7f;

		$length = 0;

		// Build big-endian integer from length bytes.
		for ($i = 0; $i < $num_bytes; $i++) {
			$length = ($length << 8) | ord($data[$offset]);

			$offset++;
		}

		return $length;
	}
}
