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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Helper class containing methods for operations with hosts.
 */
class CApiHostHelper {

	/**
	 * Get all parent templates recursively for given hosts and/or templates.
	 *
	 * @param array $hostids  Mandatory, not empty. Host or template id's for whom you are searching parents.
	 *
	 * @throws APIException if templates are looped.
	 *
	 * @return array          List with two arrays: host to parent templates template map, list of all parent templates.
	 */
	public static function getParentTemplates(array $hostids): array {
		$hosts_templates = [];
		$step_hostids = array_flip($hostids);

		do {
			$step_hostids = array_keys($step_hostids);

			foreach ($step_hostids as $hostid) {
				$hosts_templates[$hostid]['parents'] = [];
			}

			$templateids = [];
			$db_host_templates = DBselect(
				'SELECT ht.hostid,ht.templateid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionInt('ht.hostid', $step_hostids)
			);
			while ($db_host_template = DBfetch($db_host_templates)) {
				$hosts_templates[$db_host_template['hostid']]['parents'][$db_host_template['templateid']] = true;
				$templateids[$db_host_template['templateid']] = true;
			}

			// Only unprocessed templates will be populated.
			$step_hostids = [];
			foreach (array_keys($templateids) as $templateid) {
				if (!array_key_exists($templateid, $hosts_templates)) {
					$step_hostids[$templateid] = true;
				}
			}
		} while ($step_hostids);

		$all_templateids = array_keys(array_diff_key($hosts_templates, array_flip($hostids)));

		$parent_map = [];
		foreach	($hostids as $hostid) {
			$parent_map[$hostid] = array_keys(CApiHostHelper::recursiveGetAllParents($hosts_templates, $hostid));
		}

		return [$parent_map, $all_templateids];
	}

	/**
	 * Get all parent templates recursively for given templateid.
	 *
	 * @param array  $template_tree  Array with all template parents.
	 * @param string $templateid     ID of template whose parents we are looking for.
	 *
	 * @throws APIException if templates are looped.
	 *
	 * @return array                 All parent templates (as keys) for given templateid.
	 */
	private static function recursiveGetAllParents(array &$template_tree, $templateid): array {
		if (array_key_exists('started', $template_tree[$templateid])) {
			// Loop in recursion detected.
			throw new APIException(ZBX_API_ERROR_INTERNAL, _('Internal error.'));
		}

		$parents = $template_tree[$templateid]['parents'];

		// This template's parents are not yet retrieved. Collect them.
		if (!array_key_exists('final', $template_tree[$templateid])) {
			$template_tree[$templateid]['started'] = true;

			foreach (array_keys($template_tree[$templateid]['parents']) as $parentid) {
				$parents += CApiHostHelper::recursiveGetAllParents($template_tree, $parentid);
			}

			$template_tree[$templateid]['parents'] = $parents;
			unset($template_tree[$templateid]['started']);
			$template_tree[$templateid]['final'] = true;
		}

		return $parents;
	}
}
