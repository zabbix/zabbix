<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


class CControllerMediatypeEnable extends CController {

	protected function checkInput() {
		$fields = [
			'mediatypeids' =>	'required|array_db media_type.mediatypeid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->getUserType() != USER_TYPE_SUPER_ADMIN) {
			return false;
		}

		$mediatypes = API::Mediatype()->get([
			'mediatypeids' => $this->getInput('mediatypeids'),
			'countOutput' => true,
			'editable' => true
		]);

		return ($mediatypes == count($this->getInput('mediatypeids')));
	}

	protected function doAction() {
		$mediatypes = [];

		foreach ($this->getInput('mediatypeids') as $mediatypeid) {
			$mediatypes[] = [
				'mediatypeid' => $mediatypeid,
				'status' => MEDIA_TYPE_STATUS_ACTIVE
			];
		}
		$result = API::Mediatype()->update($mediatypes);

		$updated = count($mediatypes);

		$response = new CControllerResponseRedirect('zabbix.php?action=mediatype.list&uncheck=1');

		if ($result) {
			$response->setMessageOk(_n('Media type enabled', 'Media types enabled', $updated));
		}
		else {
			$response->setMessageError(_n('Cannot enable media type', 'Cannot enable media types', $updated));
		}
		$this->setResponse($response);
	}
}
