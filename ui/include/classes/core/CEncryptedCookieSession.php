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
 * Session wrapper uses cookie for session store.
 */
class CEncryptedCookieSession extends CCookieSession {

	/**
	 * @inheritDoc
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	#[\ReturnTypeWillChange]
	public function read($id) {
		return $this->checkSign($this->parseData()) ? parent::read($id) : '';
	}

	/**
	 * @inheritDoc
	 *
	 * @return string|null
	 */
	public function extractSessionId(): ?string {
		if (CSettingsHelper::getPrivate(CSettingsHelper::SESSION_KEY) === '') {
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
