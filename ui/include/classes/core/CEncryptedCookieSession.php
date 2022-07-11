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


/**
 * Session wrapper uses cookie for session store.
 */
class CEncryptedCookieSession extends CCookieSession {

	/**
	 * @inheritDoc
	 *
	 * @return string|null
	 */
	public function extractSessionId(): ?string {
		if (CSettingsHelper::getGlobal(CSettingsHelper::SESSION_KEY) === '') {
			CEncryptHelper::updateKey(CEncryptHelper::generateKey());
		}

		$session_data = $this->parseData();

		if (!$this->checkSign($session_data)) {
			return null;
		}

		$session_data = json_decode($session_data, true);

		if (!array_key_exists('sessionid', $session_data)) {
			return null;
		}

		return $session_data['sessionid'];
	}

	/**
	 * Prepare session data.
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	protected function prepareData(array $data): string {
		if (array_key_exists('sign', $data)) {
			unset($data['sign']);
		}

		$data['sign'] = CEncryptHelper::sign(json_encode($data));

		return base64_encode(json_encode($data));
	}

	/**
	 * Prepare data and check sign.
	 *
	 * @param string $data
	 *
	 * @return boolean
	 */
	protected function checkSign(string $data): bool {
		$data = json_decode($data, true);

		if (!is_array($data) || !array_key_exists('sign', $data)) {
			return false;
		}

		$session_sign = $data['sign'];
		unset($data['sign']);
		$sign = CEncryptHelper::sign(json_encode($data));

		return $session_sign && $sign && CEncryptHelper::checkSign($session_sign, $sign);
	}
}
