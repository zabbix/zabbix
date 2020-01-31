<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
			$independentTemplates = $this->getIndependentTemplates($templates);

			$templatesToCreate = [];
			$templatesToUpdate = [];
			$templateLinkage = [];
			$tmpls_to_clear = [];

			foreach ($independentTemplates as $name) {
				$template = $templates[$name];
				unset($templates[$name]);

				$template = $this->resolveTemplateReferences($template);

				/*
				 * Save linked templates for 2 purposes:
				 *  - save linkages to add in case if 'create new' linkages is checked;
				 *  - calculate missing linkages in case if 'delete missing' is checked.
				 */
				if (!empty($template['templates'])) {
					$templateLinkage[$template['host']] = $template['templates'];
				}
				unset($template['templates']);

				if (array_key_exists('templateid', $template) && ($this->options['templates']['updateExisting']
						|| $this->options['process_templates'])) {
					$templatesToUpdate[] = $template;
				}
				else if ($this->options['templates']['createMissing']) {
					if (array_key_exists('templateid', $template)) {
						throw new Exception(_s('Template "%1$s" already exists.', $name));
					}

					$templatesToCreate[] = $template;
				}
			}

			if ($this->options['templates']['createMissing'] && $templatesToCreate) {
				$newTemplateIds = API::Template()->create($templatesToCreate);

				foreach ($templatesToCreate as $num => $createdTemplate) {
					$templateId = $newTemplateIds['templateids'][$num];

					$this->referencer->addTemplateRef($createdTemplate['host'], $templateId);
					$this->processedTemplateIds[$templateId] = $templateId;

					if ($this->options['templateLinkage']['createMissing']
							&& !empty($templateLinkage[$createdTemplate['host']])) {
						API::Template()->massAdd([
							'templates' => ['templateid' => $templateId],
							'templates_link' => $templateLinkage[$createdTemplate['host']]
						]);
					}
				}
			}

			if ($templatesToUpdate) {

				// Get template linkages to unlink and clear.
				if ($this->options['templateLinkage']['deleteMissing']) {
					// Get already linked templates.
					$db_template_links = API::Template()->get([
						'output' => ['templateid'],
						'selectParentTemplates' => ['templateid'],
						'templateids' => zbx_objectValues($templatesToUpdate, 'templateid'),
						'preservekeys' => true
					]);

					foreach ($db_template_links as &$db_template_link) {
						$db_template_link = zbx_objectValues($db_template_link['parentTemplates'], 'templateid');
					}
					unset($db_template_link);

					foreach ($templatesToUpdate as $tmpl) {
						if (array_key_exists($tmpl['host'], $templateLinkage)) {
							$tmpls_to_clear[$tmpl['templateid']] = array_diff($db_template_links[$tmpl['templateid']],
								zbx_objectValues($templateLinkage[$tmpl['host']], 'templateid')
							);
						}
						else {
							$tmpls_to_clear[$tmpl['templateid']] = $db_template_links[$tmpl['templateid']];
						}
					}
				}

				if ($this->options['templates']['updateExisting']) {
					API::Template()->update($templatesToUpdate);
				}

				foreach ($templatesToUpdate as $updatedTemplate) {
					$this->processedTemplateIds[$updatedTemplate['templateid']] = $updatedTemplate['templateid'];

					// Drop existing template linkages if 'delete missing' is selected.
					if (array_key_exists($updatedTemplate['templateid'], $tmpls_to_clear)
							&& $tmpls_to_clear[$updatedTemplate['templateid']]) {
						API::Template()->massRemove([
							'templateids' => [$updatedTemplate['templateid']],
							'templateids_clear' => $tmpls_to_clear[$updatedTemplate['templateid']]
						]);
					}

					// Make new template linkages.
					if ($this->options['templateLinkage']['createMissing']
							&& !empty($templateLinkage[$updatedTemplate['host']])) {
						API::Template()->massAdd([
							'templates' => $updatedTemplate,
							'templates_link' => $templateLinkage[$updatedTemplate['host']]
						]);
					}
				}
			}
		} while (!empty($independentTemplates));

		// if there are templates left in $templates, then they have unresolved references
		foreach ($templates as $template) {
			$unresolvedReferences = [];
			foreach ($template['templates'] as $linkedTemplate) {
				if (!$this->referencer->resolveTemplate($linkedTemplate['name'])) {
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
		foreach ($templates as $num => $template) {
			if (empty($template['templates'])) {
				continue;
			}

			foreach ($template['templates'] as $linkedTpl) {
				if (!$this->referencer->resolveTemplate($linkedTpl['name'])) {
					unset($templates[$num]);
					continue 2;
				}
			}
		}

		return zbx_objectValues($templates, 'host');
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
		if ($templateId = $this->referencer->resolveTemplate($template['host'])) {
			$template['templateid'] = $templateId;

			// if we update template, existing macros should have hostmacroid
			if (array_key_exists('macros', $template)) {
				foreach ($template['macros'] as &$macro) {
					if ($hostMacroId = $this->referencer->resolveMacro($templateId, $macro['macro'])) {
						$macro['hostmacroid'] = $hostMacroId;
					}
				}
				unset($macro);
			}
		}

		foreach ($template['groups'] as $gnum => $group) {
			if (!$this->referencer->resolveGroup($group['name'])) {
				throw new Exception(_s('Group "%1$s" does not exist.', $group['name']));
			}
			$template['groups'][$gnum] = ['groupid' => $this->referencer->resolveGroup($group['name'])];
		}

		if (isset($template['templates'])) {
			foreach ($template['templates'] as $tnum => $parentTemplate) {
				$template['templates'][$tnum] = [
					'templateid' => $this->referencer->resolveTemplate($parentTemplate['name'])
				];
			}
		}

		return $template;
	}
}
