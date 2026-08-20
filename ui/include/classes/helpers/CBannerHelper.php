<?php declare(strict_types = 0);
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


class CBannerHelper {

	/**
	 * Retrieves banner data from the profile.
	 *
	 * @return array
	 */
	public static function getData(): array {
		return json_decode(CProfile::get('web.banner.data', '[]'), true) ?? [];
	}

	/**
	 * Retrieves a list of dismissed banner IDs from the profile.
	 *
	 * @return array
	 */
	public static function getDismissedIds(): array {
		return json_decode(CProfile::get('web.banner.dismissed_ids', '[]'), true) ?? [];
	}

	/**
	 * Retrieves all stored banners.
	 *
	 * @return array
	 */
	public static function getBanners(): array {
		$banner_data = self::getData();

		return $banner_data['banners'] ?? [];
	}

	/**
	 * Retrieves an active banner, if there is one.
	 *
	 * @param array  $data
	 *        array  $data['user']
	 *        string $data['user']['lang']
	 *        array  $data['dismissed_ids']
	 *
	 * @return array|null
	 */
	public static function getActiveBanner(array $data): ?array {
		$data += ['dismissed_ids' => self::getDismissedIds()];

		$active_banners = self::getActiveBanners(self::getBanners(), $data);

		self::sort($active_banners);

		return $active_banners[0] ?? null;
	}

	/**
	 * Filters and returns active banners based on time period, language availability and dismissal status.
	 *
	 * @param array  $banners
	 * @param array  $data
	 *        array  $data['user']
	 *        string $data['user']['lang']
	 *        array  $data['dismissed_ids']
	 *
	 * @return array
	 */
	public static function getActiveBanners(array $banners, array $data): array {
		return array_filter($banners, static function (array $banner) use ($data) {
			if (!array_key_exists('id', $banner) || strlen((string) $banner['id']) === 0
					|| !array_key_exists('from', $banner) || !array_key_exists('to', $banner)
					|| !is_array($banner['content'])) {
				return false;
			}

			try {
				$now = new DateTimeImmutable('now');
				$from = new DateTimeImmutable($banner['from']);
				$to = new DateTimeImmutable($banner['to']);
			} catch (Exception) {
				return false;
			}

			$not_dismissed = !in_array($banner['id'], $data['dismissed_ids']);
			$has_content = !empty($banner['content'][$data['user']['lang']]) || !empty($banner['content']['all']);

			return $not_dismissed && $has_content && $from <= $now && $now <= $to;
		});
	}

	/**
	 * Sorts banners in ascending order, prioritizing numeric IDs before alphabetical IDs.
	 *
	 * @param array $banners
	 */
	private static function sort(array &$banners): void {
		usort($banners, static function (array $a, array $b) {
			$a_numeric = is_numeric($a['id']);
			$b_numeric = is_numeric($b['id']);

			if ($a_numeric && $b_numeric) {
				$result = $a['id'] <=> $b['id'];

				if ($result === 0) {
					return self::sortByDates($a['from'], $b['from']);
				}

				return $result;
			}
			elseif ($a_numeric && !$b_numeric) {
				return -1;
			}
			elseif (!$a_numeric && $b_numeric) {
				return 1;
			}

			$result = strcasecmp($a['id'], $b['id']);

			if ($result === 0) {
				return self::sortByDates($a['from'], $b['from']);
			}

			return $result;
		});
	}

	/**
	 * Sorts by dates from "closest to now" to "furthest from now".
	 */
	private static function sortByDates(?string $a, ?string $b): int {
		$now = strtotime('now');
		$a_time = $a ? abs(strtotime($a) - $now) : PHP_INT_MAX;
		$b_time = $b ? abs(strtotime($b) - $now) : PHP_INT_MAX;

		return $a_time <=> $b_time;
	}
}
