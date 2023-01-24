<?php declare(strict_types = 0);
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


class CTemplateImporter extends CImporter {

	/**
	 * @var array  A list of template IDs which were created or updated.
	 */
	protected $processed_templateids = [];

	/**
	 * Import templates.
	 *
	 * @throws Exception
	 *
	 * @param array $templates
	 */
	public function import(array $templates) {
		$template_fields = array_flip(['uuid', 'groups', 'macros', 'host', 'status', 'name', 'description', 'tags']);

		$upd_templates = [];
		$ins_templates = [];

		$valuemaps = [];
		$create_valuemaps = [];

		foreach ($templates as $i => $template) {
			unset($templates[$i]);
			$template = $this->resolveTemplateReferences($template);

			if (array_key_exists('templateid', $template)
					&& ($this->options['templates']['updateExisting'] || $this->options['process_templates'])) {
				$upd_templates[] = array_intersect_key($template, array_flip(['templateid']) + $template_fields);
			}
			elseif ($this->options['templates']['createMissing']) {
				if (array_key_exists('templateid', $template)) {
					throw new Exception(_s('Template "%1$s" already exists.', $template['host']));
				}

				$ins_templates[] = array_intersect_key($template, $template_fields);
			}

			if (array_key_exists('valuemaps', $template)) {
				if (array_key_exists('templateid', $template)) {
					$valuemaps[$template['templateid']] = $template['valuemaps'];
				}
				else {
					$create_valuemaps[$template['host']] = $template['valuemaps'];
				}
			}
		}

		$db_valuemaps = [];

		if ($upd_templates && array_filter($this->options['valueMaps'])) {
			$result = API::ValueMap()->get([
				'output' => ['valuemapid', 'hostid', 'uuid', 'name'],
				'hostids' => array_keys($valuemaps)
			]);

			foreach ($result as $row) {
				$db_valuemaps[$row['hostid']][] = array_diff_key($row, array_flip(['hostid']));
			}
		}

		$del_valuemaps = [];
		$upd_valuemaps = [];
		$ins_valuemaps = [];

		foreach ($upd_templates as &$template) {
			$templateid = $template['templateid'];

			$this->referencer->setDbTemplate($templateid, $template);
			unset($template['uuid']);
			$this->processed_templateids[$templateid] = $templateid;

			$tpl_db_valuemaps = array_key_exists($templateid, $db_valuemaps) ? $db_valuemaps[$templateid] : [];

			if (array_key_exists($templateid, $valuemaps)) {
				if ($this->options['valueMaps']['createMissing']) {
					$valuemap_uuids = array_flip(array_column($tpl_db_valuemaps, 'uuid'));
					$valuemap_names = array_flip(array_column($tpl_db_valuemaps, 'name'));

					foreach ($valuemaps[$templateid] as $i => $valuemap) {
						if (!array_key_exists($valuemap['uuid'], $valuemap_uuids)
								&& !array_key_exists($valuemap['name'], $valuemap_names)) {
							$ins_valuemaps[] = ['hostid' => $templateid] + $valuemap;

							unset($valuemaps[$templateid][$i]);
						}
					}
				}

				if ($this->options['valueMaps']['updateExisting']) {
					foreach ($tpl_db_valuemaps as $i => $db_valuemap) {
						foreach ($valuemaps[$templateid] as $valuemap) {
							if ($db_valuemap['uuid'] === $valuemap['uuid']
									|| $db_valuemap['name'] === $valuemap['name']) {
								unset($valuemap['uuid']);
								$upd_valuemaps[] = ['valuemapid' => $db_valuemap['valuemapid']] + $valuemap;

								unset($tpl_db_valuemaps[$i]);
								break;
							}
						}
					}
				}
			}

			if ($this->options['valueMaps']['deleteMissing'] && $tpl_db_valuemaps) {
				foreach ($tpl_db_valuemaps as $valuemap) {
					$del_valuemaps[] = $valuemap['valuemapid'];
				}
			}
		}
		unset($template);

		if ($this->options['templates']['updateExisting'] && $upd_templates) {
			API::Template()->update($upd_templates);
		}

		if ($this->options['templates']['createMissing'] && $ins_templates) {
			$created_templates = API::Template()->create($ins_templates);

			foreach ($ins_templates as $i => $template) {
				$templateid = $created_templates['templateids'][$i];

				$this->referencer->setDbTemplate($templateid, $template);
				$this->processed_templateids[$templateid] = $templateid;

				if ($this->options['valueMaps']['createMissing']
						&& array_key_exists($template['host'], $create_valuemaps)) {
					foreach ($create_valuemaps[$template['host']] as $valuemap) {
						$ins_valuemaps[] = ['hostid' => $templateid] + $valuemap;
					}
				}
			}
		}

		if ($del_valuemaps) {
			API::ValueMap()->delete($del_valuemaps);
		}

		if ($upd_valuemaps) {
			API::ValueMap()->update($upd_valuemaps);
		}

		if ($ins_valuemaps) {
			API::ValueMap()->create($ins_valuemaps);
		}
	}

	/**
	 * Get a list of created or updated template IDs.
	 *
	 * @return array
	 */
	public function getProcessedTemplateids(): array {
		return $this->processed_templateids;
	}

	/**
	 * Change all references in template to database IDs.
	 *
	 * @param array $template
	 *
	 * @return array
	 *
	 * @throws Exception
	 */
	protected function resolveTemplateReferences(array $template): array {
		$templateid = $this->referencer->findTemplateidByUuid($template['uuid']);

		if ($templateid === null) {
			$templateid = $this->referencer->findTemplateidByHost($template['host']);
		}

		if ($templateid !== null) {
			$template['templateid'] = $templateid;

			// If we update template, existing macros should have hostmacroid.
			if (array_key_exists('macros', $template)) {
				foreach ($template['macros'] as &$macro) {
					$hostmacroid = $this->referencer->findTemplateMacroid($templateid, $macro['macro']);

					if ($hostmacroid !== null) {
						$macro['hostmacroid'] = $hostmacroid;
					}
				}
				unset($macro);
			}
		}

		foreach ($template['groups'] as $index => $group) {
			$groupid = $this->referencer->findTemplateGroupidByName($group['name']);

			if ($groupid === null) {
				throw new Exception(_s('Group "%1$s" does not exist.', $group['name']));
			}

			$template['groups'][$index] = ['groupid' => $groupid];
		}

		return $template;
	}
}
