<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
?>
<?php

/**
 * Class is used to validate and parse item keys
 * Example of usage:
 *      $itemKey = new CItemKey('test.key[a, b, c]');
 *      echo $itemKey->isValid(); // true
 *      echo $itemKey->getKeyId(); // test.key
 *      print_r($itemKey->parameters()); // array('a', 'b', 'c')
 */
class CItemKey {

	private $key;

	// variables required for parsing
	private $keyByteCnt;
	private $currentByte;
	// key info (is available after parsing)
	private $isValid = true;        // let's hope for the best :)
	private $error = '';            // if key is invalid
	private $parameters = array();  // array of key parameters
	private $keyId = '';            // main part of the key (for 'key[1, 2, 3]' key id would be 'key')

	/**
	 * Parse key and determine if it is valid
	 * @param string $key
	 */
	public function __construct($key) {

		$this->key = $key;

		// get key length
		$this->keyByteCnt = strlen($this->key);

		// checking if key is empty
		if ($this->keyByteCnt == 0) {
			$this->isValid = false;
			$this->error = _('Key cannot be empty.');
		}
		else {
			// getting key id out of the key
			$this->parseKeyId();
			if ($this->isValid) {
				// and parameters ($currentByte now points to start of parameters)
				$this->parseKeyParameters();
			}
		}
	}


	/**
	 * Get the key id and put $currentByte after it
	 * @return void
	 */
	private function parseKeyId() {
		// checking every byte, one by one, until first 'not key_id' char is reached
		for ($this->currentByte = 0; $this->currentByte < $this->keyByteCnt; $this->currentByte++) {
			if (!isKeyIdChar($this->key[$this->currentByte])) {
				break; // $this->currentByte now points to a first 'not a key name' char
			}
		}

		// checking if key is empty
		if ($this->currentByte == 0) {
			$this->isValid = false;
			$this->error = _('Invalid item key format.');
		}
		else {
			$this->keyId = substr($this->key, 0, $this->currentByte);
		}
	}


	/**
	 * Parse key parameters and put them into $this->parameters array
	 * @return void
	 */
	private function parseKeyParameters() {

		// no parameters?
		if ($this->currentByte == $this->keyByteCnt) {
			return;
		}

		// invalid symbol instead of '[', which would be the beginning of params
		if ($this->key[$this->currentByte] != '[') {
			$this->isValid = false;
			$this->error = _('Invalid item key format.');
			return;
		}

		// let the parsing begin!
		$state = 0;	// 0 - initial
					// 1 - inside quoted param
					// 2 - inside unquoted param
		$nestLevel = 0;
		$currParamNo = 0;
		$this->parameters[$currParamNo] = '';

		// for every byte, starting after '['
		for ($this->currentByte++; $this->currentByte < $this->keyByteCnt; $this->currentByte++) {
			switch($state) {
				case 0:	// initial state
					if ($this->key[$this->currentByte] == ',') {
						if ($nestLevel == 0) {
							$currParamNo++;
							$this->parameters[$currParamNo] = '';
						}
						else {
							$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
						}
					}
					// Zapcat: '][' is treated as ','
					else if ($this->key[$this->currentByte] == ']' && isset($this->key[$this->currentByte + 1]) && $this->key[$this->currentByte + 1] == '[' && $nestLevel == 0) {
						$currParamNo++;
						$this->parameters[$currParamNo] = '';
						$this->currentByte++;
					}
					// entering quotes
					else if ($this->key[$this->currentByte] == '"') {
						$state = 1;
						// in key[["a"]] param is "a"
						if ($nestLevel > 0) {
							$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
						}
					}
					// next nesting level
					else if ($this->key[$this->currentByte] == '[') {
						if ($nestLevel > 0) {
							$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
						}
						$nestLevel++;
					}
					// one of the nested sets ended
					else if ($this->key[$this->currentByte] == ']' && $nestLevel > 0) {

						$nestLevel--;

						if ($nestLevel > 0) {
							$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
						}

						// skipping spaces
						while (isset($this->key[$this->currentByte + 1]) && $this->key[$this->currentByte + 1] == ' ') {
							$this->currentByte++;
							if ($nestLevel > 0) {
								$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
							}
						}
						// all nestings are closed correctly
						if ($nestLevel == 0 && isset($this->key[$this->currentByte + 1]) && $this->key[$this->currentByte + 1] == ']' && !isset($this->key[$this->currentByte + 2])) {
							return;
						}

						if (
							!isset($this->key[$this->currentByte + 1]) || $this->key[$this->currentByte + 1] != ','
							&& !(
								$nestLevel > 0
								&& isset($this->key[$this->currentByte + 1])
								&& $this->key[$this->currentByte + 1] == ']'
							)
							// Zapcat - '][' is the same as ','
							&& $this->key[$this->currentByte + 1] != ']'
							&& $this->key[$this->currentByte + 2] != '['
						) {
							$this->isValid = false;
							$this->error = sprintf(_('incorrect syntax near \'%1$s\''), $this->key[$this->currentByte]);
							return;
						}
					}
					// looks like we have reached final ']'
					else if ($this->key[$this->currentByte] == ']' && $nestLevel == 0) {
						if (!isset($this->key[$this->currentByte + 1])) {
							return;
						}

						// nothing else is allowed after final ']'
						$this->isValid = false;
						$this->error = sprintf(_('incorrect usage of bracket symbols. \'%s\' found after final bracket.'), $this->key[$this->currentByte + 1]);
						return;
					}
					else if ($this->key[$this->currentByte] != ' ') {
						$state = 2;
						// this is a first symbol of unquoted param
						$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
					}
					else if ($nestLevel > 0) {
						$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
					}
					break;

				case 1:	// quoted
					// ending quote is reached
					if ($this->key[$this->currentByte] == '"' && $this->key[$this->currentByte - 1] != '\\') {
						// skipping spaces
						while (isset($this->key[$this->currentByte + 1]) && $this->key[$this->currentByte + 1] == ' ') {
							$this->currentByte++;
							if ($nestLevel > 0) {
								$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
							}
						}

						// Zapcat
						if ($nestLevel == 0 && isset($this->key[$this->currentByte + 1]) && isset($this->key[$this->currentByte + 2]) && $this->key[$this->currentByte + 1] == ']' && $this->key[$this->currentByte + 2] == '[') {
							$state = 0;
							break;
						}

						if ($nestLevel == 0 && isset($this->key[$this->currentByte + 1]) && $this->key[$this->currentByte + 1] == ']' && !isset($this->key[$this->currentByte + 2])) {
							return;
						}
						else if ($nestLevel == 0 && $this->key[$this->currentByte + 1] == ']' && isset($this->key[$this->currentByte + 2])) {
							// nothing else is allowed after final ']'
							$this->isValid = false;
							$this->error = sprintf(_('incorrect usage of bracket symbols. \'%s\' found after final bracket.'), $this->key[$this->currentByte + 1]);
							return;
						}

						if ((!isset($this->key[$this->currentByte + 1]) || $this->key[$this->currentByte + 1] != ',')	// if next symbol is not ','
							&& !($nestLevel != 0 && isset($this->key[$this->currentByte + 1]) && $this->key[$this->currentByte + 1] == ']'))
						{
							// nothing else is allowed after final ']'
							$this->isValid = false;
							$this->error = sprintf(_('incorrect syntax near \'%1$s\' at position %2$d'), $this->key[$this->currentByte], $this->currentByte);
							return;
						}

						// in key[["a"]] param is "a"
						if ($nestLevel > 0) {
							$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
						}

						$state = 0;
					}
					//escaped quote (\")
					else if ($this->key[$this->currentByte] == '\\' && isset($this->key[$this->currentByte + 1]) && $this->key[$this->currentByte + 1] == '"') {
						if ($nestLevel > 0) {
							$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
						}
					}
					else {
						$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
					}
					break;

				case 2:	// unquoted
					// Zapcat
					if ($nestLevel == 0 && $this->key[$this->currentByte] == ']' && isset($this->key[$this->currentByte + 1]) && $this->key[$this->currentByte + 1] =='[' ) {
						$this->currentByte--;
						$state = 0;
					}
					else if ($this->key[$this->currentByte] == ',' || ($this->key[$this->currentByte] == ']' && $nestLevel > 0)) {
						$this->currentByte--;
						$state = 0;
					}
					else if ($this->key[$this->currentByte] == ']' && $nestLevel == 0) {
						if (isset($this->key[$this->currentByte + 1])) {
							// nothing else is allowed after final ']'
							$this->isValid = false;
							$this->error = sprintf(_('incorrect usage of bracket symbols. \'%s\' found after final bracket.'), $this->key[$this->currentByte + 1]);
							return;
						}
						else {
							return;
						}
					}
					else {
						$this->parameters[$currParamNo] .= $this->key[$this->currentByte];
					}
				break;
			}
		}
		$this->isValid = false;
		$this->error = _('Invalid item key format.');
	}


	/**
	 * Is key valid?
	 * @return bool
	 */
	public function isValid() {
		return $this->isValid;
	}


	/**
	 * Get the error if key is invalid
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}


	/**
	 * Get the list of key parameters
	 * @return array
	 */
	public function getParameters() {
		return $this->parameters;
	}


	/**
	 * Get the key id (first part of the key)
	 * @return string
	 */
	public function getKeyId() {
		return $this->keyId;
	}
}

?>
