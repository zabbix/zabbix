<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	 * Import templates.
	 *
	 * @param array $templates
	 *
	 * @throws Exception
	 */
	public function import(array $templates): array {
		$templateids = [];
		$upd_templates = [];
		$ins_templates = [];

		foreach ($templates as $template) {
			$template = $this->resolveTemplateReferences($template);

			if (array_key_exists('templateid', $template)) {
				if ($this->options['templates']['updateExisting'] || $this->options['process_templates']) {
					$templateids[] = $template['templateid'];
					$this->referencer->setDbTemplate($template['templateid'], $template);

					if ($this->options['templates']['updateExisting']) {
						$upd_templates[] = $template;
					}
				}
			}
			else {
				if ($this->options['templates']['createMissing']) {
					$ins_templates[] = $template;
				}
			}
		}

		if ($upd_templates) {
			API::Template()->update($upd_templates);
		}

		if ($ins_templates) {
			$ins_templateids = API::Template()->create($ins_templates)['templateids'];

			foreach ($ins_templates as $template) {
				$templateid = array_shift($ins_templateids);

				$templateids[] = $templateid;
				$this->referencer->setDbTemplate($templateid, $template);
			}
		}

		return $templateids;
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
