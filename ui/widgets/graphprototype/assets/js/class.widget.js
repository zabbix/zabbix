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


class CWidgetGraphPrototype extends CWidgetIterator {

	hasCustomTimePeriod() {
		return !this.getFieldsReferredData().has('time_period');
	}

	promiseUpdate() {
		const time_period = this.getFieldsData().time_period;

		if (!this.hasBroadcast(CWidgetsData.DATA_TYPE_TIME_PERIOD) || this.isFieldsReferredDataUpdated('time_period')) {
			this.broadcast({
				[CWidgetsData.DATA_TYPE_TIME_PERIOD]: time_period
			});
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
		if (type === CWidgetsData.DATA_TYPE_TIME_PERIOD) {
			this.feedback({time_period: value});

			return true;
		}

		return false;
	}

	_updateWidget(widget) {
		widget.resize();
	}

	hasPadding() {
		return false;
	}
}
