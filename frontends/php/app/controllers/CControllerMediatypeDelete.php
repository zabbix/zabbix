<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class CControllerMediatypeDelete extends CController {

	protected function checkInput() {
		$fields = array(
			'mediatypeids' =>	'fatal|array_db media_type.mediatypeid|required'
		);

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		$mediatypes = API::Mediatype()->get(array(
			'mediatypeids' => $this->getInput('mediatypeids'),
			'countOutput' => true,
			'editable' => true
		));

		return ($mediatypes == count($this->getInput('mediatypeids')));
	}

	protected function doAction() {
		$result = API::Mediatype()->delete(array($this->getInput('mediatypeids')));

		$response = new CControllerResponseRedirect('zabbix.php?action=mediatype.list&uncheck=1');

		if ($result) {
			$response->setMessageOk(_('Media type deleted'));
		}
		else {
			$response->setMessageError(_('Cannot delete media type'));
		}
		$this->setResponse($response);
	}
}
