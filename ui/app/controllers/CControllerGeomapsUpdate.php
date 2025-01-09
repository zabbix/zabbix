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
 * Update controller for "Geographical maps" administration screen.
 */
class CControllerGeomapsUpdate extends CController {

	protected function checkInput(): bool {
		$fields = [
			'geomaps_tile_provider'		=> 'required|db config.geomaps_tile_provider',
			'geomaps_tile_url'			=> 'required|not_empty|db config.geomaps_tile_url',
			'geomaps_max_zoom'			=> 'required|not_empty|db config.geomaps_max_zoom|ge 1|le '.ZBX_GEOMAP_MAX_ZOOM,
			'geomaps_attribution'		=> 'db config.geomaps_attribution'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			switch ($this->GetValidationError()) {
				case self::VALIDATION_ERROR:
					$response = new CControllerResponseRedirect(
						(new CUrl('zabbix.php'))
							->setArgument('action', 'geomaps.edit')
							->getUrl()
					);

					$response->setFormData($this->getInputAll());
					CMessageHelper::setErrorTitle(_('Cannot update configuration'));
					$this->setResponse($response);
					break;

				case self::VALIDATION_FATAL_ERROR:
					$this->setResponse(new CControllerResponseFatal());
					break;
			}
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
	}

	protected function doAction(): void {
		$settings = [
			CSettingsHelper::GEOMAPS_TILE_PROVIDER => $this->getInput('geomaps_tile_provider'),
			CSettingsHelper::GEOMAPS_TILE_URL => $this->getInput('geomaps_tile_url'),
			CSettingsHelper::GEOMAPS_MAX_ZOOM => $this->getInput('geomaps_max_zoom'),
			CSettingsHelper::GEOMAPS_ATTRIBUTION => $this->getInput('geomaps_attribution', '')
		];

		$result = API::Settings()->update($settings);

		$response = new CControllerResponseRedirect(
			(new CUrl('zabbix.php'))
				->setArgument('action', 'geomaps.edit')
				->getUrl()
		);

		if ($result) {
			CMessageHelper::setSuccessTitle(_('Configuration updated'));
		}
		else {
			$response->setFormData($this->getInputAll());
			CMessageHelper::setErrorTitle(_('Cannot update configuration'));
		}

		$this->setResponse($response);
	}
}
