<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2025 Zabbix SIA
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


class CTemplateHelper {

	/**
	 * Get host or template parent templates recursively, input host or template will be added to result array.
	 *
	 * @param array  $hostids  An array of host or template IDs.
	 * @param string $context  'host' or 'template'
	 *
	 * @return array
	 */
	static public function getParentTemplatesRecursive(array $hostids, string $context): array {
		$templateids = array_flip($hostids);
		$all_templateids = $templateids;

		do {
			$entity = ($context == 'host') ? API::Host() : API::Template();
			$param = ($context == 'host') ? 'hostids' : 'templateids';
			$context = 'template';

			$hosts = $entity->get([
				'output' => [],
				'selectParentTemplates' => ['templateid'],
				$param => array_keys($templateids)
			]);

			$parent_templateids = [];

			foreach ($hosts as $host) {
				$parent_templateids += array_column($host['parentTemplates'], null, 'templateid');
			}

			$templateids = array_diff_key($parent_templateids, $templateids);
			$all_templateids += $parent_templateids;
		}
		while ($templateids);

		return array_keys($all_templateids);
	}
}
