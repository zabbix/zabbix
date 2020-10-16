<?php declare(strict_types = 1);
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


/**
 * Session wrapper uses cookie for session store.
 */
class CEncryptedCookieSession extends CCookieSession {

	/**
	 * Prepare data and check sign.
	 *
	 * @param array $data
	 *
	 * @return boolean
	 */
	protected function checkSign(array $data): bool {
		if (!array_key_exists('sign', $data)) {
			return false;
		}

		$session_sign = $data['sign'];
		unset($data['sign']);
		$sign = CEncryptHelper::sign(json_encode($data));

		return $session_sign && $sign && CEncryptHelper::checkSign($session_sign, $sign);
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
	 * @inheritDoc
	 *
	 * @return boolean
	 */
	protected function session_start(): bool {
		if (!$this->checkSessionKey()) {
			CEncryptHelper::updateKey(CEncryptHelper::generateKey());
		}

		$session_data = json_decode($this->parseData(), true);

		if ($session_data === null || !$this->checkSign($session_data)) {
			return session_start();
		}

		$sessionid = $this->extractSessionId($session_data);
		if ($sessionid) {
			session_id($sessionid);
		}

		return session_start();
	}

	/**
	 * Check exist secret session key.
	 *
	 * @throws \Exception
	 *
	 * @return boolean
	 */
	private function checkSessionKey(): bool {
		if (CSettingsHelper::getGlobal(CSettingsHelper::SESSION_KEY) === '') {
			return false;
		}

		return true;
	}
}
