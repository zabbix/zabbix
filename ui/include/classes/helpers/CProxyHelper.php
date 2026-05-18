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


class CProxyHelper {

	/**
	 * Resolves proxy data.
	 *
	 * @param int $proxyid
	 * @return array
	 */
	public static function resolveProxyOption(int $proxyid): array {
		if ($proxyid === 0) {
			return [];
		}

		$proxies = API::Proxy()->get([
			'output' => ['proxyid', 'name'],
			'proxyids' => $proxyid
		]);

		if ($proxies) {
			$proxy = $proxies[0];

			return [
				'id' => $proxy['proxyid'],
				'name' => $proxy['name'],
				'inaccessible' => false
			];
		}

		return [
			'id' => $proxyid,
			'name' => _('Inaccessible proxy'),
			'inaccessible' => true
		];
	}

	/**
	 * Builds HTML badges for Proxy Allow List for the specified user.
	 *
	 * @param array $all_proxies
	 * @param array $user_groups
	 * @return array
	 */
	public static function getProxiesHtml(array $all_proxies, array $user_groups): array {
		$data = self::prepareAccessListData($all_proxies, $user_groups, 'proxy_mode', 'proxies', 'proxyid');
		return self::buildAccessListHtml($data);
	}

	/**
	 * Builds HTML badges for Proxy Group Allow List for the specified user.
	 *
	 * @param array $all_proxy_groups
	 * @param array $user_groups
	 * @return array
	 */
	public static function getProxyGroupsHtml(array $all_proxy_groups, array $user_groups): array {
		$data = self::prepareAccessListData($all_proxy_groups, $user_groups, 'proxy_group_mode', 'proxy_groups',
			'proxy_groupid');
		return self::buildAccessListHtml($data);
	}

	/**
	 * Builds a default access indicator for cases when access applies to all objects.
	 *
	 * @param int $mode
	 * @return array
	 */
	public static function getDefaultAccessHtml(int $mode): array {
		return [
			(new CSpan(_('All')))->addClass(
					$mode === PROXY_MODE_ALLOW
						? ZBX_STYLE_STATUS_GREEN
						: ZBX_STYLE_STATUS_GREY
				)
		];
	}
	private static function prepareAccessListData(array $all_objects, array $user_groups, string $mode_key,
											  string $object_key, string $id_key): array {
		$show_objects_limit = CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE);
		$all_by_id = [];

		foreach ($all_objects as $object) {
			$all_by_id[$object[$id_key]] = $object['name'];
		}

		$allowed_ids = [];
		$denied_ids = [];

		$has_allow_rules = false;

		foreach ($user_groups as $user_group) {
			$object_ids = array_column($user_group[$object_key], $id_key);
			$is_allow_mode = (int) $user_group[$mode_key] === PROXY_MODE_ALLOW;

			if ($is_allow_mode && empty($object_ids)) {
				return [
					'list' => [],
					'mode' => PROXY_MODE_DENY,
					'more' => 0
				];
			}

			if (!$is_allow_mode && empty($object_ids)) {
				$allowed_ids = array_keys($all_by_id);
				continue;
			}

			if ($is_allow_mode) {
				$has_allow_rules = true;
				$allowed_ids = array_merge($allowed_ids, $object_ids);
			}
			else {
				$denied_ids = array_merge($denied_ids, $object_ids);
			}
		}

		$allowed_ids = array_unique($allowed_ids);
		$denied_ids = array_unique($denied_ids);

		if (!$has_allow_rules && empty($allowed_ids)) {
			$allowed_ids = array_keys($all_by_id);
		}

		$final_allowed_ids = array_diff($allowed_ids, $denied_ids);
		$allowed_lookup = array_flip($final_allowed_ids);

		$list = [];

		foreach ($all_by_id as $object_id => $object_name) {
			$list[] = [
				'name' => $object_name,
				'mode' => array_key_exists($object_id, $allowed_lookup) ? PROXY_MODE_ALLOW : PROXY_MODE_DENY
			];
		}

		$total_objects = count($all_objects);
		$all_allowed = count($final_allowed_ids) === $total_objects;

		return [
			'list' => $all_allowed ? [] : $list,
			'mode' => $all_allowed ? PROXY_MODE_ALLOW : PROXY_MODE_DENY,
			'more' => max(0, $total_objects - $show_objects_limit)
		];
	}

	private static function buildAccessListHtml($objects): array {
		if (empty($objects['list'])) {
			return self::getDefaultAccessHtml($objects['mode']);
		}

		$show_objects_limit = CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE);
		$objects_list = [];
		$all_entities = [];

		foreach ($objects['list'] as $index => $object) {
			$object_html = self::buildObjectBadge($object);

			$all_entities[] = $object_html;

			if ($index < $show_objects_limit) {
				$objects_list[] = $object_html;
			}
		}

		if ($objects['more'] > 0) {
			$objects_list[] = (new CSpan(_s('+ %1$d more', $objects['more'])))
				->addClass(ZBX_STYLE_PLUS_N_MORE)
				->setHint(
					$all_entities,
					ZBX_STYLE_HINTBOX_WRAP.' '.ZBX_STYLE_TAGS_WRAPPER
				);
		}

		return $objects_list;
	}

	private static function buildObjectBadge($object): CSpan {
		return (new CSpan($object['name']))->addClass(
			$object['mode'] === PROXY_MODE_ALLOW ? ZBX_STYLE_STATUS_GREEN : ZBX_STYLE_STATUS_GREY
		);
	}
}
