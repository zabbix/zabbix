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
	 * Resolves a proxy ID into a display-safe representation, respecting the current user's proxy permissions
	 *
	 * @param string $proxyid  ID of the proxy to resolve
	 *
	 * @return array
	 */
	public static function resolveProxyOption(string $proxyid): array {
		if ($proxyid === '0') {
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
	 * @param array $all_proxies  all proxues not assigned to a proxy group
	 * @param array $user_groups  user groups the corrent user belongs to
	 *
	 * @return array
	 */
	public static function getProxiesHtml(array $all_proxies, array $user_groups): array {
		$data = self::prepareAccessListData($all_proxies, $user_groups, 'proxy_mode', 'proxies', 'proxyid');

		return self::buildAccessListHtml($data);
	}

	/**
	 * Builds HTML badges for Proxy Group Allow List for the specified user.
	 *
	 * @param array $all_proxy_groups  all proxy groups
	 * @param array $user_groups       user groups the corrent user belongs to
	 *
	 * @return array
	 */
	public static function getProxyGroupsHtml(array $all_proxy_groups, array $user_groups): array {
		$data = self::prepareAccessListData($all_proxy_groups, $user_groups, 'proxy_group_mode', 'proxy_groups',
			'proxy_groupid'
		);

		return self::buildAccessListHtml($data);
	}

	/**
	 * Builds a default access indicator for cases when access applies to all objects.
	 *
	 * @param int $mode  access mode (PROXY_MODE_ALLOW or PROXY_MODE_DENY)
	 *
	 * @return array
	 */
	public static function getDefaultAccessHtml(int $mode): array {
		return [
			(new CSpan(_('All')))->addClass(
					$mode == PROXY_MODE_ALLOW ? ZBX_STYLE_STATUS_GREEN : ZBX_STYLE_STATUS_GREY
				)
		];
	}

	private static function prepareAccessListData(array $all_objects, array $user_groups, string $mode_key,
			string $object_key, string $id_key): array {
		$all_by_id = [];

		foreach ($all_objects as $object) {
			$all_by_id[$object[$id_key]] = $object['name'];
		}

		$result_allow_ids = [];
		$result_deny_ids = [];
		$has_allow_list = false;
		$has_deny_list = false;

		foreach ($user_groups as $user_group) {
			$object_ids = array_column($user_group[$object_key], $id_key);

			if ((int) $user_group[$mode_key] == PROXY_MODE_ALLOW) {
				$has_allow_list = true;
				$result_allow_ids = array_merge($result_allow_ids, $object_ids);
			}
			else {
				$has_deny_list = true;
				$result_deny_ids = array_merge($result_deny_ids, $object_ids);
			}
		}

		$result_allow_ids = array_unique($result_allow_ids);
		$result_deny_ids = array_unique($result_deny_ids);

		if ($has_allow_list && $has_deny_list) {
			$final_allowed_ids = array_diff($result_allow_ids, $result_deny_ids);
		}
		elseif ($has_allow_list) {
			$final_allowed_ids = $result_allow_ids;
		}
		elseif ($has_deny_list) {
			$final_allowed_ids = array_diff(array_keys($all_by_id), $result_deny_ids);
		}
		else {
			$final_allowed_ids = array_keys($all_by_id);
		}

		$total_objects = count($all_objects);
		$all_allowed = count($final_allowed_ids) == $total_objects;
		$all_denied = count($final_allowed_ids) == 0;

		if ($all_allowed || $all_denied) {
			return [
				'list' => [],
				'mode' => $all_allowed ? PROXY_MODE_ALLOW : PROXY_MODE_DENY,
				'more' => 0
			];
		}

		$allowed_lookup = array_flip($final_allowed_ids);
		$list = [];

		foreach ($all_by_id as $object_id => $object_name) {
			$list[] = [
				'name' => $object_name,
				'mode' => array_key_exists($object_id, $allowed_lookup) ? PROXY_MODE_ALLOW : PROXY_MODE_DENY
			];
		}

		return [
			'list' => $list,
			'mode' => PROXY_MODE_DENY,
			'more' => max(0, $total_objects - CSettingsHelper::get(CSettingsHelper::MAX_IN_TABLE))
		];
	}

	private static function buildAccessListHtml(array $objects): array {
		if (!$objects['list']) {
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
			$objects_list[] = (new CButton('plus_more', _s('+ %1$d more', $objects['more'])))
				->addClass(ZBX_STYLE_BTN_PLUS_MORE)
				->setHint(
					$all_entities,
					ZBX_STYLE_HINTBOX_WRAP.' '.ZBX_STYLE_TAGS_WRAPPER
				);
		}

		return $objects_list;
	}

	private static function buildObjectBadge(array $object): CSpan {
		return (new CSpan($object['name']))->addClass(
			$object['mode'] == PROXY_MODE_ALLOW ? ZBX_STYLE_STATUS_GREEN : ZBX_STYLE_STATUS_GREY
		);
	}
}
