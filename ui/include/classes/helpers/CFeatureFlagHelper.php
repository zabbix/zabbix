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

	public static function getSupportedMediaTypes(): array {
		$denied_media_types = APP::getConfig()['ZBX_FEATURE_FLAGS'][self::MEDIATYPES_FEATURE_FLAG];

		$all_types = [
			MEDIA_TYPE_EMAIL => 'Email',
			MEDIA_TYPE_EXEC => 'Script',
			MEDIA_TYPE_SMS => 'SMS',
			MEDIA_TYPE_WEBHOOK => 'Webhook'
		];

		if (!is_array($denied_media_types)) {
			return $all_types;
		}

		$supported_media_types = array_change_key_case(array_flip($all_types));
		$supported_media_types = array_diff_key($supported_media_types, array_flip($denied_media_types));

		return array_intersect_key($all_types, array_flip($supported_media_types));
	}

	public static function isFeatureDisabled(string $type): bool {
		return match ($type) {
			self::MODULE_FEATURE_FLAG => APP::getConfig()['ZBX_FEATURE_FLAGS'][$type],
			self::HTTP_AUTH_FEATURE_FLAG => APP::getConfig()['ZBX_FEATURE_FLAGS'][$type],
			self::MEDIATYPES_FEATURE_FLAG => APP::getConfig()['ZBX_FEATURE_FLAGS'][$type] != null
		};
	}
}
