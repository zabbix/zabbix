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

#include "zbx_sysinfo_kstat.h"
#include "zbxcommon.h"

#ifdef HAVE_KSTAT_H
zbx_uint64_t	get_kstat_numeric_value(const kstat_named_t *kn)
{
	switch (kn->data_type)
	{
		case KSTAT_DATA_INT32:
			return kn->value.i32;
		case KSTAT_DATA_UINT32:
			return kn->value.ui32;
		case KSTAT_DATA_INT64:
			return kn->value.i64;
		case KSTAT_DATA_UINT64:
			return kn->value.ui64;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return 0;
	}
}
#endif /* HAVE_KSTAT_H */
