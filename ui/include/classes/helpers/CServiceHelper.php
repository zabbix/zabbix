<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CServiceHelper {

	public static function getAlgorithmNames(): array {
		return [
			SERVICE_ALGORITHM_MAX => _('Most critical of child nodes'),
			SERVICE_ALGORITHM_MIN => _('Most critical if all children have problems'),
			SERVICE_ALGORITHM_NONE => _('Set status to OK')
		];
	}

	public static function getRuleConditionNames(): array {
		return [
			// TODO: Need to synchronize constants with serverside
			SERVICE_CALC_STATUS_MORE => _s('if at least %1$s child nodes are %2$s or greater',
				new CTag('b', true, 'N'), new CTag('b', true, _('Status'))
			),
			SERVICE_CALC_STATUS_MORE_PERC => _s('if at least %1$s child nodes are %2$s or greater',
				new CTag('b', true, 'N%'), new CTag('b', true, _('Status'))
			),
			SERVICE_CALC_STATUS_LESS => _s('if less than %1$s child nodes are %2$s or less',
				new CTag('b', true, 'N'), new CTag('b', true, _('Status'))
			),
			SERVICE_CALC_STATUS_LESS_PERC => _s('if less than %1$s child nodes are %2$s or less',
				new CTag('b', true, 'N%'), new CTag('b', true, _('Status'))
			),
			SERVICE_CALC_WEIGHT_MORE => _s('if at least %1$s of child nodes weight is in %2$s or greater',
				new CTag('b', true, 'W'), new CTag('b', true, _('Status'))
			),
			SERVICE_CALC_WEIGHT_MORE_PERC => _s('if at least %1$s of child nodes weight is in %2$s or greater',
				new CTag('b', true, 'N%'), new CTag('b', true, _('Status'))
			),
			SERVICE_CALC_WEIGHT_LESS => _s('if less than %1$s of child nodes weight is in %2$s or less',
				new CTag('b', true, 'W'), new CTag('b', true, _('Status'))
			),
			SERVICE_CALC_WEIGHT_LESS_PERC => _s('if less than %1$s of child nodes weight is in %2$s or less',
				new CTag('b', true, 'N%'), new CTag('b', true, _('Status'))
			)
		];
	}

	public static function getRuleByCondition(int $new_status, int $condition, int $number, int $status): string {
		$status = self::getRuleStatusNames()[$status];

		switch ($condition) {
			case SERVICE_CALC_STATUS_MORE:
			case SERVICE_CALC_STATUS_MORE_PERC:
				$rule = _s('if at least %1$s child nodes are %2$s or greater', (string) $number, $status);
				break;
			case SERVICE_CALC_STATUS_LESS:
			case SERVICE_CALC_STATUS_LESS_PERC:
				$rule = _s('if less than %1$s child nodes are %2$s or less', (string) $number, $status);
				break;
			case SERVICE_CALC_WEIGHT_MORE:
			case SERVICE_CALC_WEIGHT_MORE_PERC:
				$rule = _s('if at least %1$s of child nodes weight is in %2$s or greater', (string) $number, $status);
				break;
			case SERVICE_CALC_WEIGHT_LESS:
			case SERVICE_CALC_WEIGHT_LESS_PERC:
				$rule = _s('if less than %1$s of child nodes weight is in %2$s or less', (string) $number, $status);
				break;
			default:
				$rule = null;
		}

		return $rule !== null ? self::getRuleStatusNames()[$new_status].' - '.$rule : '';
	}

	public static function getRuleStatusNames(): array {
		$status_names = [-1 => _('OK')];

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$status_names[$severity] = getSeverityName($severity);
		}

		return $status_names;
	}

	public static function getPropagationRuleNames(): array {
		return [
			SERVICE_PROPAGATION_STATUS_AS_IS => _('As is'),
			SERVICE_PROPAGATION_STATUS_INC => _('Increase'),
			SERVICE_PROPAGATION_STATUS_DEC => _('Decrease'),
			SERVICE_PROPAGATION_STATUS_IGNORE => _('Ignore this service'),
			SERVICE_PROPAGATION_STATUS_FIXED => _('Fixed status')
		];
	}
}
