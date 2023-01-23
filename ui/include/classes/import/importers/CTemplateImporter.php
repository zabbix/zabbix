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
		$templates = zbx_toHash($templates, 'host');

		do {
			$independent_templates = $this->getIndependentTemplates($templates);
			$templates_api_params = array_flip(['uuid', 'groups', 'macros', 'templates', 'host', 'status', 'name',
				'description', 'tags'
			]);

			$templates_to_create = [];
			$templates_to_update = [];
			$valuemaps = [];

			foreach ($independent_templates as $name) {
				$template = $templates[$name];
				unset($templates[$name]);

				$template = $this->resolveTemplateReferences($template);

				if (array_key_exists('templateid', $template)
						&& ($this->options['templates']['updateExisting'] || $this->options['process_templates'])) {
					$templates_to_update[] = array_intersect_key($template,
						$templates_api_params + array_flip(['templateid'])
					);
				}
				elseif ($this->options['templates']['createMissing']) {
					if (array_key_exists('templateid', $template)) {
						throw new Exception(_s('Template "%1$s" already exists.', $template['host']));
					}

					$templates_to_create[] = array_intersect_key($template, $templates_api_params);
				}

				if (array_key_exists('valuemaps', $template)) {
					$valuemaps[$template['host']] = $template['valuemaps'];
				}
			}

			if ($templates_to_update) {
				if ($this->options['templates']['updateExisting']) {
					API::Template()->update(array_map(function($template) {
						unset($template['uuid']);
						return $template;
					}, $templates_to_update));
				}

				foreach ($templates_to_update as $template) {
					$this->referencer->setDbTemplate($template['templateid'], $template);
					$this->processed_templateids[$template['templateid']] = $template['templateid'];

					$db_valuemaps = API::ValueMap()->get([
						'output' => ['valuemapid', 'uuid'],
						'hostids' => [$template['templateid']]
					]);

					if ($this->options['valueMaps']['createMissing']
							&& array_key_exists($template['host'], $valuemaps)) {
						$valuemaps_to_create = [];
						$valuemap_uuids = array_column($db_valuemaps, 'uuid');

						foreach ($valuemaps[$template['host']] as $valuemap) {
							if (!in_array($valuemap['uuid'], $valuemap_uuids)) {
								$valuemaps_to_create[] = $valuemap  + ['hostid' => $template['templateid']];
							}
						}

						if ($valuemaps_to_create) {
							API::ValueMap()->create($valuemaps_to_create);
						}
					}

					if ($this->options['valueMaps']['updateExisting']
							&& array_key_exists($template['host'], $valuemaps)) {
						$valuemaps_to_update = [];

						foreach ($db_valuemaps as $db_valuemap) {
							foreach ($valuemaps[$template['host']] as $valuemap) {
								if ($db_valuemap['uuid'] === $valuemap['uuid']) {
									unset($valuemap['uuid']);
									$valuemaps_to_update[] = $valuemap + ['valuemapid' => $db_valuemap['valuemapid']];
								}
							}
						}

						if ($valuemaps_to_update) {
							API::ValueMap()->update($valuemaps_to_update);
						}
					}

					if ($this->options['valueMaps']['deleteMissing'] && $db_valuemaps) {
						$valuemapids_to_delete = [];

						if (array_key_exists($template['host'], $valuemaps)) {
							$valuemap_uuids = array_column($valuemaps[$template['host']], 'uuid');

							foreach ($db_valuemaps as $db_valuemap) {
								if (!in_array($db_valuemap['uuid'], $valuemap_uuids)) {
									$valuemapids_to_delete[] = $db_valuemap['valuemapid'];
								}
							}
						}
						else {
							$valuemapids_to_delete = array_column($db_valuemaps, 'valuemapid');
						}

						if ($valuemapids_to_delete) {
							API::ValueMap()->delete($valuemapids_to_delete);
						}
					}
				}
			}

			if ($this->options['templates']['createMissing'] && $templates_to_create) {
				$created_templates = API::Template()->create($templates_to_create);

				foreach ($templates_to_create as $index => $template) {
					$templateid = $created_templates['templateids'][$index];

					$this->referencer->setDbTemplate($templateid, $template);
					$this->processed_templateids[$templateid] = $templateid;

					if ($this->options['valueMaps']['createMissing']
						&& array_key_exists($template['host'], $valuemaps)) {
						$valuemaps_to_create = [];

						foreach ($valuemaps[$template['host']] as $valuemap) {
							$valuemap['hostid'] = $templateid;
							$valuemaps_to_create[] = $valuemap;
						}

						if ($valuemaps_to_create) {
							API::ValueMap()->create($valuemaps_to_create);
						}
					}
				}
			}
		} while ($independent_templates);
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
	 * Get templates that don't have not existing linked templates i.e. all templates that must be linked to these
	 * templates exist. Returns array with template names (host).
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	protected function getIndependentTemplates(array $templates): array {
		foreach ($templates as $index => $template) {
			if (!array_key_exists('templates', $template)) {
				continue;
			}

			foreach ($template['templates'] as $linked_template) {
				if ($this->referencer->findTemplateidByHost($linked_template['name']) === null) {
					unset($templates[$index]);
					continue 2;
				}
			}
		}

		return array_column($templates, 'host');
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
