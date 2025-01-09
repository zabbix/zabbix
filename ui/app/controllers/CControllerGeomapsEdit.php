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


/**
 * Edit view controller for "Geographical maps" administration screen.
 */
class CControllerGeomapsEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'geomaps_tile_provider'	=> 'string',
			'geomaps_tile_url'		=> 'string',
			'geomaps_max_zoom'		=> 'string',
			'geomaps_attribution'	=> 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$data = [
			'geomaps_tile_provider' => $this->getInput('geomaps_tile_provider', CSettingsHelper::get(
				CSettingsHelper::GEOMAPS_TILE_PROVIDER
			)),
			'tile_providers' => getTileProviders()
		];

		$data += (array_key_exists($data['geomaps_tile_provider'], $data['tile_providers']))
			? $data['tile_providers'][$data['geomaps_tile_provider']]
			: [
				'geomaps_tile_url' => $this->getInput('geomaps_tile_url', CSettingsHelper::get(
					CSettingsHelper::GEOMAPS_TILE_URL
				)),
				'geomaps_max_zoom' => $this->getInput('geomaps_max_zoom', CSettingsHelper::get(
					CSettingsHelper::GEOMAPS_MAX_ZOOM
				)),
				'geomaps_attribution' => $this->getInput('geomaps_attribution', CSettingsHelper::get(
					CSettingsHelper::GEOMAPS_ATTRIBUTION
				))
			];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Geographical maps'));
		$this->setResponse($response);
	}
}
