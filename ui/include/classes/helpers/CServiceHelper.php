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
			ZBX_SERVICE_STATUS_RULE_TYPE_N_GE => _s('if at least %1$s child nodes are %2$s or greater',
				new CTag('b', true, 'N'), new CTag('b', true, _('Status'))
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE => _s('if at least %1$s child nodes are %2$s or greater',
				new CTag('b', true, 'N%'), new CTag('b', true, _('Status'))
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_N_L => _s('if less than %1$s child nodes are %2$s or less',
				new CTag('b', true, 'N'), new CTag('b', true, _('Status'))
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_NP_L => _s('if less than %1$s child nodes are %2$s or less',
				new CTag('b', true, 'N%'), new CTag('b', true, _('Status'))
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_W_GE => _s('if at least %1$s of child nodes weight is in %2$s or greater',
				new CTag('b', true, 'W'), new CTag('b', true, _('Status'))
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE => _s('if at least %1$s of child nodes weight is in %2$s or greater',
				new CTag('b', true, 'N%'), new CTag('b', true, _('Status'))
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_W_L => _s('if less than %1$s of child nodes weight is in %2$s or less',
				new CTag('b', true, 'W'), new CTag('b', true, _('Status'))
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_WP_L => _s('if less than %1$s of child nodes weight is in %2$s or less',
				new CTag('b', true, 'N%'), new CTag('b', true, _('Status'))
			)
		];
	}

	public static function getRuleByCondition(int $new_status, int $condition, int $number, int $status): string {
		$status = self::getRuleStatusNames()[$status];

		switch ($condition) {
			case ZBX_SERVICE_STATUS_RULE_TYPE_N_GE:
			case ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE:
				$rule = _s('if at least %1$s child nodes are %2$s or greater', (string) $number, $status);
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_N_L:
			case ZBX_SERVICE_STATUS_RULE_TYPE_NP_L:
				$rule = _s('if less than %1$s child nodes are %2$s or less', (string) $number, $status);
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_W_GE:
			case ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE:
				$rule = _s('if at least %1$s of child nodes weight is in %2$s or greater', (string) $number, $status);
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_W_L:
			case ZBX_SERVICE_STATUS_RULE_TYPE_WP_L:
				$rule = _s('if less than %1$s of child nodes weight is in %2$s or less', (string) $number, $status);
				break;
			default:
				$rule = null;
		}

		return $rule !== null ? self::getRuleStatusNames()[$new_status].' - '.$rule : '';
	}

	public static function getRuleStatusNames(): array {
		$status_names = [-1 => _('OK')];

		foreach (CSeverityHelper::getSeverities() as $severity) {
			$status_names[$severity['value']] = $severity['name'];
		}

		return $status_names;
	}

	public static function getPropagationRuleNames(): array {
		return [
			ZBX_SERVICE_STATUS_AS_IS => _('As is'),
			ZBX_SERVICE_STATUS_INCREASE => _('Increase by'),
			ZBX_SERVICE_STATUS_DECREASE => _('Decrease by'),
			ZBX_SERVICE_STATUS_IGNORE => _('Ignore this service'),
			ZBX_SERVICE_STATUS_FIXED => _('Fixed status')
		];
	}
}
