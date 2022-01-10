<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
			ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE => _('Most critical of child services'),
			ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL => _('Most critical if all children have problems'),
			ZBX_SERVICE_STATUS_CALC_SET_OK => _('Set status to OK')
		];
	}

	public static function getStatusRuleTypeOptions(): array {
		return [
			ZBX_SERVICE_STATUS_RULE_TYPE_N_GE => _s(
				'If at least %2$s child services have %1$s status or above',
				(new CSpan(_('Status')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER),
				(new CSpan(_('N')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER)
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE => _s(
				'If at least %2$s of child services have %1$s status or above',
				(new CSpan(_('Status')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER),
				(new CSpan(_('N%')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER)
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_N_L => _s(
				'If less than %2$s child services have %1$s status or below',
				(new CSpan(_('Status')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER),
				(new CSpan(_('N')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER)
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_NP_L => _s(
				'If less than %2$s of child services have %1$s status or below',
				(new CSpan(_('Status')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER),
				(new CSpan(_('N%')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER)
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_W_GE => _s(
				'If weight of child services with %1$s status or above is at least %2$s',
				(new CSpan(_('Status')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER),
				(new CSpan(_('W')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER)
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE => _s(
				'If weight of child services with %1$s status or above is at least %2$s',
				(new CSpan(_('Status')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER),
				(new CSpan(_('N%')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER)
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_W_L => _s(
				'If weight of child services with %1$s status or below is less than %2$s',
				(new CSpan(_('Status')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER),
				(new CSpan(_('W')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER)
			),
			ZBX_SERVICE_STATUS_RULE_TYPE_WP_L => _s(
				'If weight of child services with %1$s status or below is less than %2$s',
				(new CSpan(_('Status')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER),
				(new CSpan(_('N%')))->addClass(ZBX_STYLE_TEXT_PLACEHOLDER)
			)
		];
	}

	public static function formatStatusRuleType(int $type, int $new_status, int $number, int $status): string {
		$status = self::getStatusNames()[$status];

		switch ($type) {
			case ZBX_SERVICE_STATUS_RULE_TYPE_N_GE:
				$rule = _n(
					'If at least %2$s child service has %1$s status or above',
					'If at least %2$s child services have %1$s status or above',
					new CTag('em', true, $status), new CTag('em', true, $number), $number
				);
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE:
				$rule = _s('If at least %2$s of child services have %1$s status or above',
					new CTag('em', true, $status), new CTag('em', true, [$number, '%']), $number
				);
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_N_L:
				$rule = _n(
					'If less than %2$s child service has %1$s status or below',
					'If less than %2$s child services have %1$s status or below',
					new CTag('em', true, $status), new CTag('em', true, $number), $number
				);
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_NP_L:
				$rule = _s('If less than %2$s of child services have %1$s status or below',
					new CTag('em', true, $status), new CTag('em', true, [$number, '%']), $number
				);
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_W_GE:
				$rule = _s('If weight of child services with %1$s status or above is at least %2$s',
					new CTag('em', true, $status), new CTag('em', true, $number));
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE:
				$rule = _s('If weight of child services with %1$s status or above is at least %2$s',
					new CTag('em', true, $status), new CTag('em', true, $number).'%');
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_W_L:
				$rule = _s('If weight of child services with %1$s status or below is less than %2$s',
					new CTag('em', true, $status), new CTag('em', true, $number));
				break;
			case ZBX_SERVICE_STATUS_RULE_TYPE_WP_L:
				$rule = _s('If weight of child services with %1$s status or below is less than %2$s',
					new CTag('em', true, $status), new CTag('em', true, $number).'%');
				break;
			default:
				$rule = null;
		}

		return $rule !== null
			? (new CObject(
				[new CTag('em', true, self::getProblemStatusNames()[$new_status]), ' - ', $rule]
			))->toString()
			: '';
	}

	public static function getStatusNames(): array {
		return [ZBX_SEVERITY_OK => _('OK')] + self::getProblemStatusNames();
	}

	public static function getProblemStatusNames(): array {
		$status_names = [];

		foreach (CSeverityHelper::getSeverities() as $severity) {
			$status_names[$severity['value']] = $severity['name'];
		}

		return $status_names;
	}

	public static function getStatusPropagationNames(): array {
		return [
			ZBX_SERVICE_STATUS_PROPAGATION_AS_IS => _('As is'),
			ZBX_SERVICE_STATUS_PROPAGATION_INCREASE => _('Increase by'),
			ZBX_SERVICE_STATUS_PROPAGATION_DECREASE => _('Decrease by'),
			ZBX_SERVICE_STATUS_PROPAGATION_IGNORE => _('Ignore this service'),
			ZBX_SERVICE_STATUS_PROPAGATION_FIXED => _('Fixed status')
		];
	}
}
