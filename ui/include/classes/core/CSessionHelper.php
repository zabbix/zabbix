<?php

final class CSessionHelper {

	public static function clear(): bool {
		return session_unset();
	}

	public static function has(string $key): bool {
		return array_key_exists($key, $_SESSION);
	}

	public static function get(string $key) {
		return self::has($key) ? $_SESSION[$key] : null;
	}

	public static function set(string $key, $value): void {
		$_SESSION[$key] = $value;
	}

	public static function unset(array $keys): void {
		foreach ($keys as $key) {
			unset($_SESSION[$key]);
		}
	}

	public static function getId(): string {
		return session_id();
	}
}
