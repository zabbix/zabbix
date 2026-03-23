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
 * Helper class for feature flags
 */
class CFeatureFlagHelper {

	public const MODULE_FEATURE_FLAG = 'modules_config_enabled';
	public const HTTP_AUTH_FEATURE_FLAG = 'http_auth_enabled';
	public const MEDIATYPES_FEATURE_FLAG = 'media_type_denylist';

	public static function isFlagModulesEnabled(): bool {
		return APP::getConfig()['ZBX_FEATURE_FLAGS'][self::MODULE_FEATURE_FLAG];
	}

	public static function isFlagHttpAuthEnabled(): bool {
		return APP::getConfig()['ZBX_FEATURE_FLAGS'][self::HTTP_AUTH_FEATURE_FLAG];
	}

	public static function getSupportedMediaTypes(): array {
		return APP::getConfig()['ZBX_FEATURE_FLAGS'][self::MEDIATYPES_FEATURE_FLAG];
	}

	public static function isFeatureFlagEnabled(string $type): bool {
		return match ($type) {
			self::MODULE_FEATURE_FLAG => self::isFlagModulesEnabled(),
			self::HTTP_AUTH_FEATURE_FLAG => self::isFlagHttpAuthEnabled()
		};
	}
}
