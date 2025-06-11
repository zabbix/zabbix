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


class CColorPicker extends CTag {

	public const PALETTE_COLORS = [
		['F48485', 'CA767B', 'FF78D9', 'CA76BE', '8867B9', '3B97FF', '4876B1', '6DBCCD', '7AD9CC', '619F3A', '92E79A', 'BBBC39', 'FCC95A', 'F69F89', 'E37E23', '7E574D', 'B89B93', '7E7E7E'],
		['C75566', 'BF7FCB', 'B34FB7', '91497D', '7F3FA1', '8775BB', '646AD0', '68AFE4', '5188B9', '598530', '486C4E', '75CAC8', '67B08A', 'BCC239', 'A1A441', 'F0B338', 'EB9A39', 'DB6D47'],
		['DD530E', 'F64C68', 'ED5AAD', 'DB4FEE', '9E7DF7', '6A45FC', '6A71F6', '3370FF', '3290FB', '0AABF0', '05ACD1', '66A22A', '62C51B', '15BC9E', '14B86B', 'EDB007', 'F16E22', 'A19AA2'],
		['EFECFE', 'E6E1FE', 'D9D2FE', 'CFC3FE', 'C7B9FE', 'AF97FC', '9F83FB', '946CF9', '895DF8', '8247F0', '7534EF', '6D2CDD', '6724DB', '5320AC', '471B93', '411985', '2C115A', '220D45'],
		['3975EC', '4173ED', '4571ED', '4A70EE', '4E6FEE', '546DEF', '596BEF', '5D6AEF', '6268EF', '6767F0', '6C66F1', '7064F1', '7563F1', '7A62F2', '7F60F2', '845FF3', '895EF3', '8C5CF3'],
		['B8DFFF', '8ACAFF', '58B0FE', '4198FB', '3290FB', '2778F1', '196FF0', '1D64E7', '175BD9', '1A4DBC', '1848AF', '1C4797', '19418A', '19428F', '163A7E', '153675', '102A5B', '0C2045'],
		['CCE7F0', 'A9D6E5', '89C2D9', '61A5C2', '59A1BF', '468FAF', '4489A7', '2C7DA0', '2A7798', '2A6F97', '266488', '014F86', '014B7F', '01497C', '01416F', '013A63', '012A4A', '00060A'],
		['3D77EA', '557FD6', '6E88C1', '8992AB', 'A59C94', 'C5A77A', 'D1AC6F', 'E7B45D', 'FCBC4C', 'FCB54A', 'FCAA47', 'FC9F43', 'FC923F', 'FC893C', 'FD7C39', 'FC7436', 'FC6C33', 'FC6030'],
		['FEF3CD', 'FEE38F', 'FFCB52', 'FEB939', 'FEB225', 'F99B1F', 'F9920B', 'F77402', 'E36B02', 'D95408', 'BB4907', 'AF410E', '97380C', '8F350F', '81300E', '6E280C', '531E09', '451908'],
		['FFE8D1', 'FFD8B3', 'FE9F6C', 'FE7F4D', 'FE6D34', 'FE541A', 'FE480B', 'F22F03', 'E22C03', 'CC1C05', 'B81A05', 'A1180C', '93160B', '811B0E', '73180C', '65160B', '5C140A', '531209'],
		['24C56B', '45C55D', '6BC64C', '83C53E', '9BC42F', 'B4C222', 'C7BE1E', 'DDBA19', 'EAB317', 'F4A917', 'FA9D14', 'F8930D', 'F48302', 'F17501', 'EE6601', 'EC5A01', 'E84A01', 'E43403'],
		['D7F9E2', 'ACF1C7', '77E4A8', '75DBA7', '40CE85', '17C571', '15B769', '0A9F59', '099554', '09814C', '087847', '0A7044', '09623B', '095837', '084F31', '074B2F', '053320', '031C11']
	];

	public function __construct(string $color_field_name, ?string $palette_field_name = null) {
		parent::__construct('z-color-picker', true);

		$this->setAttribute('color-field-name', $color_field_name);

		if ($palette_field_name !== null) {
			$this->setAttribute('palette-field-name', $palette_field_name);
		}
	}

	public function setColor(?string $color = null): self {
		if ($color !== null) {
			$this
				->setAttribute('color', $color)
				->removeAttribute('palette');
		}

		return $this;
	}

	public function setPalette(?string $palette = null): self {
		if ($palette !== null) {
			$this
				->setAttribute('palette', $palette)
				->removeAttribute('color');
		}

		return $this;
	}

	public function setHasDefault(bool $has_default = true): self {
		if ($has_default) {
			$this->setAttribute('has-default', '');
		}
		else {
			$this->removeAttribute('has-default');
		}

		return $this;
	}

	public function setDisabled(bool $disabled = true): self {
		if ($disabled) {
			$this->setAttribute('disabled', '');
		}
		else {
			$this->removeAttribute('disabled');
		}

		return $this;
	}

	public function setReadonly(bool $readonly = true): self {
		if ($readonly) {
			$this->setAttribute('readonly', '');
		}
		else {
			$this->removeAttribute('readonly');
		}

		return $this;
	}

	public function allowEmpty(bool $allow_empty = true): self {
		if ($allow_empty) {
			$this->setAttribute('allow-empty', '');
		}
		else {
			$this->removeAttribute('allow-empty');
		}

		return $this;
	}

	/**
	 * Get array of color variations.
	 *
	 * @param string $color  Color hex code.
	 * @param int    $count  How many variations are requested.
	 *
	 * @return array  Color hex codes in format ['#FF0000', '#FF7F7F'].
	 */
	public static function getColorVariations(string $color, int $count = 1): array {
		if ($color === '') {
			return [];
		}

		if ($count <= 1) {
			return ['#'.$color];
		}

		$change = hex2rgb('#ffffff'); // Color which is increased/decreased in variations.
		$max = 50;

		$color = hex2rgb('#'.$color);
		$variations = [];

		$range = range(-1 * $max, $max, $max * 2 / $count);

		// Remove redundant values.
		while (count($range) > $count) {
			(count($range) % 2) ? array_shift($range) : array_pop($range);
		}

		// Calculate colors.
		foreach ($range as $var) {
			$r = $color[0] + ($change[0] / 100 * $var);
			$g = $color[1] + ($change[1] / 100 * $var);
			$b = $color[2] + ($change[2] / 100 * $var);

			$variations[] = '#'.rgb2hex([
					$r < 0 ? 0 : ($r > 255 ? 255 : (int) $r),
					$g < 0 ? 0 : ($g > 255 ? 255 : (int) $g),
					$b < 0 ? 0 : ($b > 255 ? 255 : (int) $b)
				]);
		}

		return $variations;
	}

	/**
	 * Get array of palette colors.
	 *
	 * @param int $row_number  Palette row number.
	 * @param int $count       How many colors from palette are requested.
	 *
	 * @return array  Color hex codes in format ['#FF0000', '#FF7F7F'].
	 */
	public static function getPaletteColors(int $row_number, int $count): array {
		$result = [];
		$row_count = count(self::PALETTE_COLORS);
		$col_count = count(self::PALETTE_COLORS[0]);

		$used = array_fill(0, $row_count, array_fill(0, $col_count, false));
		$row = $row_number;
		$col = 0;

		$total_elements = $row_count * $col_count;

		while (count($result) < $count) {
			// If color is not used, take it.
			if (!$used[$row][$col]) {
				$result[] = '#'.self::PALETTE_COLORS[$row][$col];
				$used[$row][$col] = true;
			}

			// If all colors gathered, stop.
			if (count($result) === $count) {
				break;
			}

			// If row is not fully used, continue in same row.
			if (count(array_filter($used[$row])) < $col_count) {
				// Move 3 steps ahead within the row, finding next unused color.
				$steps = 3;

				while ($steps > 0) {
					$col = ($col + 1) % $col_count;

					if (!$used[$row][$col]) {
						$steps--;
					}
				}
			}
			// If row is fully used, move to next row.
			else {
				$row = ($row + 1) % $row_count;
				$col = 0;

				// If all colors from all rows are used, reset "used" array and start over.
				if (array_sum(array_map('array_sum', $used)) === $total_elements) {
					$used = array_fill(0, $row_count, array_fill(0, $col_count, false));
					$row = $row_number;
				}
			}
		}

		return $result;
	}
}
