<?php

final class CCookieHelper {

	public static function has(string $name): bool {
		return array_key_exists($name, $_COOKIE);
	}

	public static function get(string $name) {
		return self::has($name) ? $_COOKIE[$name] : null;
	}

	public static function set(string $name, $value, int $time = 0): bool {
		if (headers_sent()) {
			throw new \Exception('Headers already sent.'); // FIXME:
		}

		$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$path = rtrim(substr($path, 0, strrpos($path, '/')), '/');

		if (strlen($value) === 0) {
			throw new \Exception("Value cannot be empty. To delete cookie use ".self::class."::unset()"); // FIXME:
		}

		if (!setcookie($name, $value, $time, $path, null, HTTPS, true)) {
			return false;
		}

		$_COOKIE[$name] = $value;

		return true;
	}

	public static function unset(string $name): bool {
		if (!self::has($name)) {
			return false;
		}

		if (headers_sent()) {
			throw new \Exception('Headers already sent.'); // FIXME:
		}

		unset($_COOKIE[$name]);

		return setcookie($name, '', 0);
	}

	public static function getAll(): array {
		return $_COOKIE;
	}
}
