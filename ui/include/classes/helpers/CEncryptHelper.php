<?php

class CEncryptHelper {

	private const SIGN_ALGO = 'aes-256-ecb';

	private static function getKey(): string {
		$config = select_config();
		if (!array_key_exists('session_key', $config)) {
			throw new \Exception('Please define session secret key'); // FIXME:
		}

		return $config['session_key'];
	}

	public static function checkSign(string $known_string, string $user_string): bool {
		return hash_equals($known_string, $user_string);
	}

	public static function sign(string $data): string {
		$key = self::getKey();

		return openssl_encrypt($data, self::SIGN_ALGO, $key);
	}
}
