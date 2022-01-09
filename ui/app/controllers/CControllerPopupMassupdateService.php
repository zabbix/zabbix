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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once __DIR__.'/../../include/forms.inc.php';

class CControllerPopupMassupdateService extends CController {

	protected function checkInput(): bool {
		$fields = [
			'ids' =>				'required|array_id',
			'update' =>				'in 1',
			'visible' =>			'array',
			'tags' =>				'array',
			'mass_update_tags' =>	'in '.implode(',', [ZBX_ACTION_ADD, ZBX_ACTION_REPLACE, ZBX_ACTION_REMOVE]),
			'location_url' => 		'required|string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * @throws APIException
	 */
	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SERVICES)) {
			return false;
		}

		return (bool) API::Service()->get([
			'output' => [],
			'serviceids' => $this->getInput('ids')
		]);
	}

	protected function doAction(): void {
		if ($this->hasInput('update')) {
			$serviceids = $this->getInput('ids', []);
			$visible = $this->getInput('visible', []);
			$tags = array_filter($this->getInput('tags', []),
				static function (array $tag): bool {
					return ($tag['tag'] !== '' || $tag['value'] !== '');
				}
			);

			$result = true;

			try {
				$options = [
					'output' => ['serviceid'],
					'serviceids' => $serviceids
				];

				if (array_key_exists('tags', $visible)) {
					$mass_update_tags = $this->getInput('mass_update_tags', ZBX_ACTION_ADD);

					if ($mass_update_tags == ZBX_ACTION_ADD || $mass_update_tags == ZBX_ACTION_REMOVE) {
						$options['selectTags'] = ['tag', 'value'];
					}

					$unique_tags = [];
					foreach ($tags as $tag) {
						$unique_tags[$tag['tag']][$tag['value']] = $tag;
					}

					$tags = [];
					foreach ($unique_tags as $tag) {
						foreach ($tag as $value) {
							$tags[] = $value;
						}
					}
				}

				$services = API::Service()->get($options);

				foreach ($services as &$service) {
					if (array_key_exists('tags', $visible)) {
						if ($tags && $mass_update_tags == ZBX_ACTION_ADD) {
							$unique_tags = [];

							foreach (array_merge($service['tags'], $tags) as $tag) {
								$unique_tags[$tag['tag']][$tag['value']] = $tag;
							}

							$service['tags'] = [];
							foreach ($unique_tags as $tag) {
								foreach ($tag as $value) {
									$service['tags'][] = $value;
								}
							}
						}
						elseif ($mass_update_tags == ZBX_ACTION_REPLACE) {
							$service['tags'] = $tags;
						}
						elseif ($tags && $mass_update_tags == ZBX_ACTION_REMOVE) {
							$diff_tags = [];

							foreach ($service['tags'] as $a) {
								foreach ($tags as $b) {
									if ($a['tag'] === $b['tag'] && $a['value'] === $b['value']) {
										continue 2;
									}
								}

								$diff_tags[] = $a;
							}

							$service['tags'] = $diff_tags;
						}
					}
				}
				unset($service);

				if (!API::Service()->update($services)) {
					throw new Exception();
				}
			}
			catch (Exception $e) {
				$result = false;
			}

			if ($result) {
				$output = ['title' => _('Services updated')];

				if ($messages = CMessageHelper::getMessages()) {
					$output['messages'] = array_column($messages, 'message');
				}
			}
			else {
				CMessageHelper::setErrorTitle(_('Cannot update services'));

				$output = [
					'errors' => makeMessageBox(ZBX_STYLE_MSG_BAD, filter_messages(), CMessageHelper::getTitle())
						->toString()
				];
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
				'location_url' => $this->getInput('location_url')
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
