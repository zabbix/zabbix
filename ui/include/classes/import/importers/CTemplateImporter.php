<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	 * @var array		a list of template IDs which were created or updated
	 */
	protected $processedTemplateIds = [];

	/**
	 * Import templates.
	 *
	 * @throws Exception
	 *
	 * @param array $templates
	 */
	public function import(array $templates) {
		$templates = zbx_toHash($templates, 'host');

		$this->checkCircularTemplateReferences($templates);

		if (!$this->options['templateLinkage']['createMissing']
				&& !$this->options['templateLinkage']['deleteMissing']) {
			foreach ($templates as $name => $template) {
				unset($templates[$name]['templates']);
			}
		}

		do {
			$independent_templates = $this->getIndependentTemplates($templates);

			$templates_to_create = [];
			$templates_to_update = [];
			$valuemaps = [];
			$template_linkage = [];
			$templates_to_clear = [];

			foreach ($independent_templates as $name) {
				$template = $templates[$name];
				unset($templates[$name]);

				$template = $this->resolveTemplateReferences($template);

				/*
				 * Save linked templates for 2 purposes:
				 *  - save linkages to add in case if 'create new' linkages is checked;
				 *  - calculate missing linkages in case if 'delete missing' is checked.
				 */
				if (array_key_exists('templates', $template)) {
					$template_linkage[$template['host']] = $template['templates'];
				}
				unset($template['templates']);

				if (array_key_exists('templateid', $template)
						&& ($this->options['templates']['updateExisting'] || $this->options['process_templates'])) {
					$templates_to_update[] = $template;
				}
				elseif ($this->options['templates']['createMissing']) {
					if (array_key_exists('templateid', $template)) {
						throw new Exception(_s('Template "%1$s" already exists.', $template['host']));
					}

					$templates_to_create[] = $template;
				}

				if (array_key_exists('valuemaps', $template)) {
					$valuemaps[$template['host']] = $template['valuemaps'];
				}
			}

			if ($templates_to_update) {
				// Get template linkages to unlink and clear.
				if ($this->options['templateLinkage']['deleteMissing']) {
					// Get already linked templates.
					$db_template_links = API::Template()->get([
						'output' => ['templateid'],
						'selectParentTemplates' => ['templateid'],
						'templateids' => array_column($templates_to_update, 'templateid'),
						'preservekeys' => true
					]);

					foreach ($db_template_links as &$db_template_link) {
						$db_template_link = array_column($db_template_link['parentTemplates'], 'templateid');
					}
					unset($db_template_link);

					foreach ($templates_to_update as $template) {
						if (array_key_exists($template['host'], $template_linkage)) {
							$templates_to_clear[$template['templateid']] = array_diff(
								$db_template_links[$template['templateid']],
								array_column($template_linkage[$template['host']], 'templateid')
							);
						}
						else {
							$templates_to_clear[$template['templateid']] = $db_template_links[$template['templateid']];
						}
					}
				}

				if ($this->options['templates']['updateExisting']) {
					API::Template()->update(array_map(function($template) {
						unset($template['uuid']);
						return $template;
					}, $templates_to_update));
				}

				foreach ($templates_to_update as $template) {
					$this->processedTemplateIds[$template['templateid']] = $template['templateid'];

					// Drop existing template linkages if 'delete missing' is selected.
					if (array_key_exists($template['templateid'], $templates_to_clear)
							&& $templates_to_clear[$template['templateid']]) {
						API::Template()->massRemove([
							'templateids' => [$template['templateid']],
							'templateids_clear' => $templates_to_clear[$template['templateid']]
						]);
					}

					// Make new template linkages.
					if ($this->options['templateLinkage']['createMissing']
							&& array_key_exists($template['host'], $template_linkage)) {
						API::Template()->massAdd([
							'templates' => $template,
							'templates_link' => $template_linkage[$template['host']]
						]);
					}

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
					$this->processedTemplateIds[$templateid] = $templateid;

					if ($this->options['templateLinkage']['createMissing']
						&& array_key_exists($template['host'], $template_linkage)) {
						API::Template()->massAdd([
							'templates' => ['templateid' => $templateid],
							'templates_link' => $template_linkage[$template['host']]
						]);
					}

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

		// if there are templates left in $templates, then they have unresolved references
		foreach ($templates as $template) {
			$unresolvedReferences = [];
			foreach ($template['templates'] as $linkedTemplate) {
				if (!$this->referencer->findTemplateidByHost($linkedTemplate['name'])) {
					$unresolvedReferences[] = $linkedTemplate['name'];
				}
			}
			throw new Exception(_n('Cannot import template "%1$s", linked template "%2$s" does not exist.',
				'Cannot import template "%1$s", linked templates "%2$s" do not exist.',
				$template['host'], implode(', ', $unresolvedReferences), count($unresolvedReferences)));
		}
	}

	/**
	 * Get a list of created or updated template IDs.
	 *
	 * @return array
	 */
	public function getProcessedTemplateIds() {
		return $this->processedTemplateIds;
	}

	/**
	 * Check if templates have circular references.
	 *
	 * @throws Exception
	 * @see checkCircularRecursive
	 *
	 * @param array $templates
	 */
	protected function checkCircularTemplateReferences(array $templates) {
		foreach ($templates as $name => $template) {
			if (empty($template['templates'])) {
				continue;
			}

			foreach ($template['templates'] as $linkedTemplate) {
				$checked = [$name];
				if ($circTemplates = $this->checkCircularRecursive($linkedTemplate, $templates, $checked)) {
					throw new Exception(_s('Circular reference in templates: %1$s.', implode(' - ', $circTemplates)));
				}
			}
		}
	}

	/**
	 * Recursive function for searching for circular template references.
	 * If circular reference exist it return array with template names with circular reference.
	 *
	 * @param array $linkedTemplate template element to inspect on current recursive loop
	 * @param array $templates      all templates where circular references should be searched
	 * @param array $checked        template names that already were processed,
	 *                              should contain unique values if no circular references exist
	 *
	 * @return array|bool
	 */
	protected function checkCircularRecursive(array $linkedTemplate, array $templates, array $checked) {
		$linkedTemplateName = $linkedTemplate['name'];

		// if current element map name is already in list of checked map names,
		// circular reference exists
		if (in_array($linkedTemplateName, $checked)) {
			// to have nice result containing only maps that have circular reference,
			// remove everything that was added before repeated map name
			$checked = array_slice($checked, array_search($linkedTemplateName, $checked));
			// add repeated name to have nice loop like m1->m2->m3->m1
			$checked[] = $linkedTemplateName;
			return $checked;
		}
		else {
			$checked[] = $linkedTemplateName;
		}

		// we need to find map that current element reference to
		// and if it has selements check all them recursively
		if (!empty($templates[$linkedTemplateName]['templates'])) {
			foreach ($templates[$linkedTemplateName]['templates'] as $tpl) {
				return $this->checkCircularRecursive($tpl, $templates, $checked);
			}
		}

		return false;
	}

	/**
	 * Get templates that don't have not existing linked templates i.e. all templates that must be linked to these templates exist.
	 * Returns array with template names (host).
	 *
	 * @param array $templates
	 *
	 * @return array
	 */
	protected function getIndependentTemplates(array $templates) {
		foreach ($templates as $index => $template) {
			if (!array_key_exists('templates', $template)) {
				continue;
			}

			foreach ($template['templates'] as $linked_template) {
				if (!$this->referencer->findTemplateidByHost($linked_template['name'])) {
					unset($templates[$index]);
					continue 2;
				}
			}
		}

		return array_column($templates, 'host');
	}

	/**
	 * Change all references in template to database ids.
	 *
	 * @throws Exception
	 *
	 * @param array $template
	 *
	 * @return array
	 */
	protected function resolveTemplateReferences(array $template) {
		$templateid = $this->referencer->findTemplateidByUuid($template['uuid']);
//		$templateid = $this->referencer->findTemplateidByHost($template['host']);

		if ($templateid !== null) {
			$template['templateid'] = $templateid;

			// If we update template, existing macros should have hostmacroid.
			if (array_key_exists('macros', $template)) {
				foreach ($template['macros'] as &$macro) {
					$hostmacroid = $this->referencer->findMacroid($templateid, $macro['macro']);

					if ($hostmacroid !== null) {
						$macro['hostmacroid'] = $hostmacroid;
					}
				}
				unset($macro);
			}
		}

		foreach ($template['groups'] as $index => $group) {
			$groupid = $this->referencer->findGroupidByName($group['name']);

			if ($groupid === null) {
				throw new Exception(_s('Group "%1$s" does not exist.', $group['name']));
			}

			$template['groups'][$index] = ['groupid' => $groupid];
		}

		if (array_key_exists('templates', $template)) {
			foreach ($template['templates'] as $index => $parent_template) {
				$parent_templateid = $this->referencer->findTemplateidByHost($parent_template['name']);

				// TODO VM: what will happen, if template with such name is not found? How null will be processed? Does this require error message here?
				if ($parent_templateid === null) {
//					throw new Exception(_n('Cannot import template "%1$s", linked template "%2$s" does not exist.',
//						'Cannot import template "%1$s", linked templates "%2$s" do not exist.',
//						$template['host'], implode(', ', $unresolvedReferences), count($unresolvedReferences)));
					throw new Exception(_n('TODO VM: It is real error'));
				}

				$template['templates'][$index] = [
					'templateid' => $parent_templateid
				];
			}
		}

		return $template;
	}
}
