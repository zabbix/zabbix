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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CHostGroupNameValidator extends CValidator {

	/**
	 * Checks if host group name is string or at least an integer, is not empty. Check if name contains forward slashes
	 * and asterisks. Slashes cannot be first character, last or repeat in the middle multiple times. Asterisks are not
	 * allowed at all.
	 *
	 * @param mixed $name				Host group name.
	 *
	 * @return bool
	 */
	public function validate($name) {
		if (!is_string($name) && !is_int($name)) {
			$this->error(_s('Incorrect value for field "%1$s": %2$s.', 'name', _('must be a string')));

			return false;
		}

		if ($name === '') {
			$this->error(_s('Incorrect value for field "%1$s": %2$s.', 'name', _('cannot be empty')));

			return false;
		}

		$error = false;
		$len = strlen($name);

		foreach (['/', '*'] as $char) {
			switch ($char) {
				case '/':
					$groups = explode($char, $name);
					$cnt = count($groups);
					foreach ($groups as $i => $group) {
						if (!$group) {
							unset($groups[$i]);
						}
					}

					$expected_cnt = count($groups);

					if ($cnt != $expected_cnt) {
						$str = '';
						for ($i = 0; $i < $cnt; $i++) {
							if (array_key_exists($i, $groups)) {
								$str .= $groups[$i].'/';
							}
							else {
								$pos = strlen($str) - 1;
								if ($pos == -1) {
									$pos = 0;
								}

								$error = true;
								break 3;
							}
						}
					}
					break;

				case '*':
					$pos = strpos($name, $char);

					if ($pos !== false) {
						$error = true;
						break 2;
					}
			}
		}

		if ($error) {
			for ($i = $pos, $chunk = '', $max_chunk_size = 50; isset($name[$i]); $i++) {
				if (0x80 != (0xc0 & ord($name[$i])) && $max_chunk_size-- == 0) {
					break;
				}
				$chunk .= $name[$i];
			}

			if (isset($name[$i])) {
				$chunk .= ' ...';
			}

			$this->error(_s('Incorrect value for field "%1$s": %2$s.', 'name',
				_s('incorrect syntax near "%1$s"', $chunk)
			));

			return false;
		}

		return true;
	}
}
