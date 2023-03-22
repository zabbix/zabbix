/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

package zbxlib

/* cspell:disable */

/*
#cgo LDFLAGS: -Wl,--start-group
#cgo LDFLAGS: ${SRCDIR}/../../../zabbix_agent/logfiles/libzbxlogfiles.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxnum/libzbxnum.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxstr/libzbxstr.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxparam/libzbxparam.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxexpr/libzbxexpr.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxip/libzbxip.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxcomms/libzbxcomms.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxcommon/libzbxcommon.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxcrypto/libzbxcrypto.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxthreads/libzbxthreads.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxmutexs/libzbxmutexs.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxnix/libzbxnix.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxhttp/libzbxhttp.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxcompress/libzbxcompress.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxregexp/libzbxregexp.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/libzbxagentsysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/common/libcommonsysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/simple/libsimplesysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/linux/libspechostnamesysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxsysinfo/linux/libspecsysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxexec/libzbxexec.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxalgo/libzbxalgo.a
#cgo LDFLAGS: ${SRCDIR}/../../../libs/zbxjson/libzbxjson.a
#cgo pcre  LDFLAGS: -lz -lpcre -lresolv
#cgo pcre2 LDFLAGS: -lz -lpcre2-8 -lresolv
#cgo LDFLAGS: -Wl,--end-group

#include "zbxsysinfo.h"
#include "zbxcomms.h"
#include "log.h"
#include "../src/zabbix_agent/metrics.h"
#include "../src/zabbix_agent/logfiles/logfiles.h"

typedef ZBX_ACTIVE_METRIC* ZBX_ACTIVE_METRIC_LP;
typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;
typedef zbx_vector_expression_t * zbx_vector_expression_lp_t;

int CONFIG_MAX_LINES_PER_SECOND = 20;
char ZBX_THREAD_LOCAL *CONFIG_HOSTNAME = NULL;
int	CONFIG_UNSAFE_USER_PARAMETERS= 0;
int	CONFIG_ENABLE_REMOTE_COMMANDS= 0;
int	CONFIG_LOG_REMOTE_COMMANDS= 0;
char	*CONFIG_SOURCE_IP= NULL;

int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

const char	*progname = NULL;
const char	title_message[] = "agent";
const char	syslog_app_name[] = "agent";
const char	*usage_message[] = {};
unsigned char	program_type	= 0x80;
const char	*help_message[] = {};

ZBX_METRIC	parameters_agent[] = {NULL};
ZBX_METRIC	parameters_specific[] = {NULL};

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

char	*strerror_from_system(unsigned long error)
{
	return zbx_strerror(errno);
}

*/
import "C"
