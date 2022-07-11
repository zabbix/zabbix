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


require_once dirname(__FILE__).'/../../include/forms.inc.php';

class CControllerPopupMassupdateTemplate extends CControllerPopupMassupdateAbstract {

	protected function checkInput() {
		$fields = [
			'ids' => 'required|array_id',
			'update' => 'in 1',
			'visible' => 'array',
			'groups' => 'array',
			'tags' => 'array',
			'macros' => 'array',
			'linked_templates' => 'array',
			'valuemaps' => 'array',
			'valuemap_remove' => 'array',
			'valuemap_remove_except' => 'in 1',
			'valuemap_remove_all' => 'in 1',
			'valuemap_rename' => 'array',
			'valuemap_update_existing' => 'in 1',
			'valuemap_add_missing' => 'in 1',
			'mass_action_tpls' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'mass_clear_tpls' => 'in 0,1',
			'mass_update_groups' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'mass_update_tags' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'mass_update_macros' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE, ZBX_ACTION_REMOVE_ALL]),
			'valuemap_massupdate' => 'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE, ZBX_ACTION_RENAME, ZBX_ACTION_REMOVE_ALL]),
			'description' => 'string',
			'macros_add' => 'in 0,1',
			'macros_update' => 'in 0,1',
			'macros_remove' => 'in 0,1',
			'macros_remove_all' => 'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		$templates = API::Template()->get([
			'output' => [],
			'templateids' => $this->getInput('ids'),
			'editable' => true
		]);

		return count($templates) > 0;
	}

	protected function doAction() {
		if ($this->hasInput('update')) {
			$output = [];
			$templateids = $this->getInput('ids', []);
			$visible = $this->getInput('visible', []);
			$macros = array_filter(cleanInheritedMacros($this->getInput('macros', [])),
				function (array $macro): bool {
					return (bool) array_filter(
						array_intersect_key($macro, array_flip(['hostmacroid', 'macro', 'value', 'description']))
					);
				}
			);
			$tags = array_filter($this->getInput('tags', []),
				function (array $tag): bool {
					return ($tag['tag'] !== '' || $tag['value'] !== '');
				}
			);

			$result = true;

			try {
				DBstart();

				$options = [
					'output' => ['templateid'],
					'templateids' => $templateids
				];

				if (array_key_exists('groups', $visible)) {
					$options['selectGroups'] = ['groupid'];
				}

				if (array_key_exists('linked_templates', $visible)
						&& !($this->getInput('mass_action_tpls') == ZBX_ACTION_REPLACE
							&& !$this->hasInput('mass_clear_tpls'))) {
					$options['selectParentTemplates'] = ['templateid'];
				}

				if (array_key_exists('tags', $visible)) {
					$mass_update_tags = $this->getInput('mass_update_tags', ZBX_ACTION_ADD);

					if ($mass_update_tags == ZBX_ACTION_ADD || $mass_update_tags == ZBX_ACTION_REMOVE) {
						$options['selectTags'] = ['tag', 'value'];
					}

					$unique_tags = [];

					foreach ($tags as $tag) {
						$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
					}

					$tags = array_values($unique_tags);
				}

				if (array_key_exists('macros', $visible)) {
					$mass_update_macros = $this->getInput('mass_update_macros', ZBX_ACTION_ADD);

					if ($mass_update_macros == ZBX_ACTION_ADD || $mass_update_macros == ZBX_ACTION_REPLACE
							|| $mass_update_macros == ZBX_ACTION_REMOVE) {
						$options['selectMacros'] = ['hostmacroid', 'macro'];
					}
				}

				$templates = API::Template()->get($options);

				if (array_key_exists('groups', $visible)) {
					$new_groupids = [];
					$remove_groupids = [];
					$mass_update_groups = $this->getInput('mass_update_groups', ZBX_ACTION_ADD);

					if ($mass_update_groups == ZBX_ACTION_ADD || $mass_update_groups == ZBX_ACTION_REPLACE) {
						if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
							$ins_groups = [];

							foreach ($this->getInput('groups', []) as $new_group) {
								if (is_array($new_group) && array_key_exists('new', $new_group)) {
									$ins_groups[] = ['name' => $new_group['new']];
								}
								else {
									$new_groupids[] = $new_group;
								}
							}

							if ($ins_groups) {
								if (!$result = API::HostGroup()->create($ins_groups)) {
									throw new Exception();
								}

								$new_groupids = array_merge($new_groupids, $result['groupids']);
							}
						}
						else {
							$new_groupids = $this->getInput('groups', []);
						}
					}
					elseif ($mass_update_groups == ZBX_ACTION_REMOVE) {
						$remove_groupids = $this->getInput('groups', []);
					}
				}

				$new_values = [];

				if (array_key_exists('description', $visible)) {
					$new_values['description'] = $this->getInput('description');
				}

				foreach ($templates as &$template) {
					if (array_key_exists('groups', $visible)) {
						if ($new_groupids && $mass_update_groups == ZBX_ACTION_ADD) {
							$current_groupids = array_column($template['groups'], 'groupid');
							$template['groups'] = zbx_toObject(array_unique(array_merge($current_groupids, $new_groupids)),
								'groupid'
							);
						}
						elseif ($new_groupids && $mass_update_groups == ZBX_ACTION_REPLACE) {
							$template['groups'] = zbx_toObject($new_groupids, 'groupid');
						}
						elseif ($remove_groupids) {
							$current_groupids = array_column($template['groups'], 'groupid');
							$template['groups'] = zbx_toObject(array_diff($current_groupids, $remove_groupids), 'groupid');
						}
					}

					if (array_key_exists('linked_templates', $visible)) {
						$parent_templateids = array_key_exists('parentTemplates', $template)
							? array_column($template['parentTemplates'], 'templateid')
							: [];

						switch ($this->getInput('mass_action_tpls')) {
							case ZBX_ACTION_ADD:
								$template['templates'] = zbx_toObject(
									array_unique(
										array_merge($parent_templateids, $this->getInput('linked_templates', []))
									),
									'templateid'
								);
								break;

							case ZBX_ACTION_REPLACE:
								$template['templates'] = zbx_toObject($this->getInput('linked_templates', []),
									'templateid'
								);

								if ($this->getInput('mass_clear_tpls', 0)) {
									$template['templates_clear'] = zbx_toObject(
										array_diff($parent_templateids, $this->getInput('linked_templates', [])),
										'templateid'
									);
								}
								break;

							case ZBX_ACTION_REMOVE:
								$template['templates'] = zbx_toObject(
									array_diff($parent_templateids, $this->getInput('linked_templates', [])),
									'templateid'
								);

								if ($this->getInput('mass_clear_tpls', 0)) {
									$template['templates_clear'] =
										zbx_toObject($this->getInput('linked_templates', []), 'templateid');
								}
								break;
						}
					}

					if (array_key_exists('tags', $visible)) {
						if ($tags && $mass_update_tags == ZBX_ACTION_ADD) {
							$unique_tags = [];

							foreach (array_merge($template['tags'], $tags) as $tag) {
								$unique_tags[$tag['tag'].':'.$tag['value']] = $tag;
							}

							$template['tags'] = array_values($unique_tags);
						}
						elseif ($mass_update_tags == ZBX_ACTION_REPLACE) {
							$template['tags'] = $tags;
						}
						elseif ($tags && $mass_update_tags == ZBX_ACTION_REMOVE) {
							$diff_tags = [];

							foreach ($template['tags'] as $a) {
								foreach ($tags as $b) {
									if ($a['tag'] === $b['tag'] && $a['value'] === $b['value']) {
										continue 2;
									}
								}

								$diff_tags[] = $a;
							}

							$template['tags'] = $diff_tags;
						}
					}

					if (array_key_exists('macros', $visible)) {
						switch ($mass_update_macros) {
							case ZBX_ACTION_ADD:
								$update_existing = (bool) getRequest('macros_add', 0);
								$template['macros'] = array_column($template['macros'], null, 'hostmacroid');
								$template_macros_by_macro = array_column($template['macros'], null, 'macro');

								foreach ($macros as $macro) {
									if (!array_key_exists($macro['macro'], $template_macros_by_macro)) {
										$template['macros'][] = $macro;
									}
									elseif ($update_existing) {
										$hostmacroid = $template_macros_by_macro[$macro['macro']]['hostmacroid'];
										$template['macros'][$hostmacroid] = ['hostmacroid' => $hostmacroid] + $macro;
									}
								}
								break;

							case ZBX_ACTION_REPLACE:
								$add_missing = (bool) getRequest('macros_update', 0);
								$template['macros'] = array_column($template['macros'], null, 'hostmacroid');
								$template_macros_by_macro = array_column($template['macros'], null, 'macro');

								foreach ($macros as $macro) {
									if (array_key_exists($macro['macro'], $template_macros_by_macro)) {
										$hostmacroid = $template_macros_by_macro[$macro['macro']]['hostmacroid'];
										$template['macros'][$hostmacroid] = ['hostmacroid' => $hostmacroid] + $macro;
									}
									elseif ($add_missing) {
										$template['macros'][] = $macro;
									}
								}
								break;

							case ZBX_ACTION_REMOVE:
								if ($macros) {
									$except_selected = $this->getInput('macros_remove', 0);
									$template_macros_by_macro = array_column($template['macros'], null, 'macro');
									$macros_by_macro = array_column($macros, null, 'macro');

									$template['macros'] = $except_selected
										? array_intersect_key($template_macros_by_macro, $macros_by_macro)
										: array_diff_key($template_macros_by_macro, $macros_by_macro);
								}
								break;

							case ZBX_ACTION_REMOVE_ALL:
								if (!$this->getInput('macros_remove_all', 0)) {
									throw new Exception();
								}

								$template['macros'] = [];
								break;
						}

						$template['macros'] = array_values($template['macros']);
					}

					unset($template['parentTemplates']);

					$template = $new_values + $template;
				}
				unset($template);

				if (!API::Template()->update($templates)) {
					throw new Exception();
				}

				// Value mapping.
				if (array_key_exists('valuemaps', $visible)) {
					$this->updateValueMaps($templateids);
				}
			}
			catch (Exception $e) {
				$result = false;
				CMessageHelper::setErrorTitle(_('Cannot update templates'));
			}

			DBend($result);

			if ($result) {
				$output = [
					'title' => _('Templates updated'),
					'script_inline' => (new CScriptTag('sessionStorage.removeItem("cb_templates");'))->toString()
				];
				$messages = CMessageHelper::getMessages();

				if (count($messages)) {
					$output['messages'] = array_column($messages, 'message');
				}
			}
			else {
				$output['errors'] = makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())
					->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}
		else {
			$data = [
				'title' => _('Mass update'),
				'user' => [
					'debug_mode' => $this->getDebugMode()
				],
				'ids' => $this->getInput('ids'),
				'location_url' => 'templates.php'
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
