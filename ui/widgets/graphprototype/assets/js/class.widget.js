/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


class CWidgetGraphPrototype extends CWidgetIterator {

	hasCustomTimePeriod() {
		return !this.getFieldsReferredData().has('time_period');
	}

	promiseUpdate() {
		const time_period = this.getFieldsData().time_period;

		if (!this.hasBroadcast('_timeperiod') || this.isFieldsReferredDataUpdated('time_period')) {
			this.broadcast({_timeperiod: time_period});
		}

		if (time_period === null) {
			super.processUpdateResponse({
				messages: [
					t('Invalid parameter "%1$s": %2$s.')
						.replace('%1$s', t('Time period'))
						.replace('%2$s', t('no data received'))
				]
			});

			return Promise.resolve();
		}

		return super.promiseUpdate();
	}

	getUpdateRequestData() {
		return {
			...super.getUpdateRequestData(),
			has_custom_time_period: this.hasCustomTimePeriod() ? 1 : undefined
		};
	}

	onFeedback({type, value}) {
		if (type === '_timeperiod') {
			this.feedback({time_period: value});

			return true;
		}

		return super.onFeedback({type, value});
	}

	_updateWidget(widget) {
		widget.resize();
	}

	hasPadding() {
		return false;
	}
}
