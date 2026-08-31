<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * A class for validating relative URLs pointing to Frontend actions.
 */
class CFrontendActionValidator extends CValidator {

	/**
	 * Validate the relative URL to a registered frontend action.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function validate($value): bool {
		$value = explode('#', $value, 2)[0];

		preg_match('/^(?<filename>[a-z0-9_.]+\.php)(?<query>\?.*)?$/i', $value, $match);

		if (!array_key_exists('filename', $match)) {
			$this->setError(_('a relative URL to the frontend is expected'));

			return false;
		}

		if (CRouter::getInstance()->isLegacyActionFile($match['filename'])) {
			return true;
		}

		if ($match['filename'] !== 'zabbix.php' || !array_key_exists('query', $match)) {
			$this->setError(_('a relative URL to the frontend is expected'));

			return false;
		}

		parse_str(substr($match['query'], 1), $query);

		if (!array_key_exists('action', $query) || !is_string($query['action'])) {
			$this->setError(_('a relative URL to the frontend is expected'));

			return false;
		}

		if (!CRouter::getInstance()->isMvcAction($query['action'])) {
			$this->setError(_('invalid action in the frontend URL'));

			return false;
		}

		return true;
	}
}
