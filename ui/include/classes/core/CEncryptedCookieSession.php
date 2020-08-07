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
	 * @param string $data
	 *
	 * @return boolean
	 */
	protected function checkSign(string $data): bool {
		$data = unserialize($data);
		$session_sign = $data['sign'];
		unset($data['sign']);
		$sign = CEncryptHelper::sign(serialize($data));

		return $session_sign && $sign && CEncryptHelper::checkSign($session_sign, $sign);
	}

	/**
	 * Prepare session data.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	protected function prepareData(string $data): string {
		$data = unserialize($data);

		if (array_key_exists('sign', $data)) {
			unset($data['sign']);
		}

		$data['sign'] = CEncryptHelper::sign(serialize($data));

		return base64_encode(serialize($data));
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

		$session_data = $this->parseData();

		if (mb_strlen($session_data) === 0 || !$this->checkSign($session_data)) {
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
		$config = select_config();
		if ($config['session_key'] === '') {
			return false;
		}

		return true;
	}
}
