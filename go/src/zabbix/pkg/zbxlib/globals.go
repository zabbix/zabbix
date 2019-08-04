/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include
#cgo LDFLAGS: -Wl,--start-group
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/zabbix_agent/logs/libzbxlogs.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxcomms/libzbxcomms.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxcommon/libzbxcommon.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxcrypto/libzbxcrypto.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxsys/libzbxsys.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxnix/libzbxnix.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxconf/libzbxconf.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxcompress/libzbxcompress.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxregexp/libzbxregexp.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxsysinfo/libzbxagentsysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxsysinfo/linux/libspechostnamesysinfo.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxalgo/libzbxalgo.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxjson/libzbxjson.a
#cgo LDFLAGS: -Wl,--end-group
#cgo LDFLAGS: -lz -lcurl -lresolv -lpcre

#include "common.h"
#include "sysinfo.h"
#include "../src/zabbix_agent/metrics.h"
#include "../src/zabbix_agent/logs/logfiles.h"

typedef ZBX_ACTIVE_METRIC* ZBX_ACTIVE_METRIC_LP;
typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;

int CONFIG_MAX_LINES_PER_SECOND = 20;
char *CONFIG_HOSTNAME = NULL;
int	CONFIG_UNSAFE_USER_PARAMETERS	= 0;
int	CONFIG_ENABLE_REMOTE_COMMANDS	= 0;

const char	*progname = NULL;
const char	title_message[] = "agent";
const char	syslog_app_name[] = "agent";
const char	*usage_message[] = {};
unsigned char	program_type	= 0x80;
const char	*help_message[] = {};

ZBX_METRIC	parameters_agent[] = {NULL};
ZBX_METRIC	parameters_common[] = {NULL};
ZBX_METRIC	parameters_specific[] = {NULL};
ZBX_METRIC	parameters_simple[] = {NULL};
*/
import "C"

const (
	ItemStateNormal       = 0
	ItemStateNotsupported = 1
)

const (
	Succeed = 0
	Fail    = -1
)
