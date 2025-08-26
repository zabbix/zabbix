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

package zbxlib

/* cspell:disable */

/*
#cgo pcre2 LDFLAGS: -lz -lpcre2-8 -lresolv

#include "zbxsysinfo.h"
#include "zbxcomms.h"
#include "zbxlog.h"
#include "../src/zabbix_agent/metrics/metrics.h"
#include "../src/zabbix_agent/logfiles/logfiles.h"

typedef zbx_active_metric_t* ZBX_ACTIVE_METRIC_LP;
typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;
typedef zbx_vector_expression_t * zbx_vector_expression_lp_t;

zbx_metric_t	parameters_agent[] = {NULL};
zbx_metric_t	parameters_specific[] = {NULL};

int	zbx_procstat_collector_started(void)
{
	return FAIL;
}

int	zbx_procstat_get_util(const char *procname, const char *username, const char *cmdline, zbx_uint64_t flags,
		int period, int type, double *value, char **errmsg)
{
	return FAIL;
}

int	get_cpustat(AGENT_RESULT *result, int cpu_num, int state, int mode)
{
	return SYSINFO_RET_FAIL;
}

char	*zbx_strerror_from_system(zbx_syserror_t error)
{
	return zbx_strerror(errno);
}

*/
import "C"
