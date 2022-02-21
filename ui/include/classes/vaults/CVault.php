<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Abstract vault access class.
 */
abstract class CVault {

	/**
	 * @var array
	 */
	private $errors = [];

	abstract public function validateParameters(): bool;
	abstract public function getCredentials(): ?array;

	abstract public function validateMacroValue(string $value): bool;

	public function addError(string $error): void {
		$this->errors[] = $error;
	}

	public function getErrors(): array {
		return $this->errors;
	}

//	public function loadCredentials(array $config, bool $use_cache = false): array {
//		if ($this->$config['DB']['USER'] === '' || $this->$config['DB']['PASSWORD'] === '') {
//			throw new Exception(_('Unable to load database credentials from Vault.'), CConfigFile::CONFIG_VAULT_ERROR);
//		}
//		return [$this->config['DB']['USER'], $this->$config['DB']['PASSWORD']];
//	}

//	static function credentialsInUse() {
//		return self::$instance->expectsCredentials();
//	}

//	static function expectsCredentials() {
//		return array_key_exists('VAULT', self::$configuration);
//	}

//	static function exception(string $error_message, int $error_type) {
//		throw new Exception($error_message);
//	}
}
