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
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxlog/libzbxlog.a
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

extern int CONFIG_MAX_LINES_PER_SECOND;

typedef ZBX_ACTIVE_METRIC* ZBX_ACTIVE_METRIC_LP;
typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;
typedef char * char_lp_t;

int	EXECUTE_USER_PARAMETER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return FAIL;
}

void zbx_on_exit(int ret)
{
}

ZBX_ACTIVE_METRIC *new_metric(char *key, zbx_uint64_t lastlogsize, int mtime, int flags)
{
	ZBX_ACTIVE_METRIC *metric = malloc(sizeof(ZBX_ACTIVE_METRIC));
	memset(metric, 0, sizeof(ZBX_ACTIVE_METRIC));
	metric->key = key;
	metric->lastlogsize = lastlogsize;
	metric->mtime = mtime;
	metric->flags = flags;
	return metric;
}

void metric_set_refresh(ZBX_ACTIVE_METRIC *metric, int refresh)
{
	metric->refresh = refresh;
}

void metric_get_meta(ZBX_ACTIVE_METRIC *metric, zbx_uint64_t *lastlogsize, int *mtime)
{
	*lastlogsize = metric->lastlogsize;
	*mtime = metric->mtime;
}

void metric_set_unsupported(ZBX_ACTIVE_METRIC *metric)
{
	metric->state = ITEM_STATE_NOTSUPPORTED;
	metric->refresh_unsupported = 0;
	metric->error_count = 0;
	metric->start_time = 0.0;
	metric->processed_bytes = 0;
}

int metric_set_supported(ZBX_ACTIVE_METRIC *metric, zbx_uint64_t lastlogsize_sent, int mtime_sent,
		zbx_uint64_t lastlogsize_last, int mtime_last)
{
	int	ret = FAIL;

	if (0 == metric->error_count)
	{
		unsigned char	old_state = metric->state;
		if (ITEM_STATE_NOTSUPPORTED == metric->state)
		{
			metric->state = ITEM_STATE_NORMAL;
			metric->refresh_unsupported = 0;
		}

		if (lastlogsize_sent != metric->lastlogsize || mtime_sent != metric->mtime ||
				(lastlogsize_last == lastlogsize_sent && mtime_last == mtime_sent &&
						(old_state != metric->state || 0 != (ZBX_METRIC_FLAG_NEW & metric->flags))))
		{
			ret = SUCCEED;
		}
		metric->flags &= ~ZBX_METRIC_FLAG_NEW;
	}
	return ret;
}

void	metric_free(ZBX_ACTIVE_METRIC *metric)
{
	int	i;

	zbx_free(metric->key);
	zbx_free(metric->key_orig);

	for (i = 0; i < metric->logfiles_num; i++)
		zbx_free(metric->logfiles[i].filename);

	zbx_free(metric->logfiles);
	zbx_free(metric);
}

void processValue(void *server, const char *value, int state, zbx_uint64_t lastlogsize, int mtime);

int	process_value_cb(const char *server, unsigned short port, const char *host, const char *key,
		const char *value, unsigned char state, zbx_uint64_t *lastlogsize, const int *mtime,
		unsigned long *timestamp, const char *source, unsigned short *severity, unsigned long *logeventid,
		unsigned char flags)
{
	processValue((void *)server, value, (int)state, *lastlogsize, *mtime);
	return SUCCEED;
}

*/
import "C"

import (
	"errors"
	"fmt"
	"time"
	"unsafe"
	"zabbix/internal/plugin"
	"zabbix/pkg/itemutil"
)

const (
	MetricFlagPersistent  = 0x01
	MetricFlagNew         = 0x02
	MetricFlagLogLog      = 0x04
	MetricFlagLogLogrt    = 0x08
	MetricFlagLogEventlog = 0x10
	MetricFlagLogCount    = 0x20
	MetricFlagLog         = MetricFlagLogLog | MetricFlagLogLogrt | MetricFlagLogEventlog
)

type LogItem struct {
	Itemid uint64
	Output plugin.ResultWriter
}

func NewActiveMetric(metric string, lastLogsize uint64, mtime int) (data unsafe.Pointer, err error) {
	ckey := C.CString(metric)
	var key string
	key, _, err = itemutil.ParseKey(metric)
	if err != nil {
		return
	}
	flags := MetricFlagNew | MetricFlagPersistent
	switch key {
	case "log":
		flags |= MetricFlagLogLog
	case "logrt":
		flags |= MetricFlagLogLogrt
	case "log.count":
		flags |= MetricFlagLogCount | MetricFlagLogLog
	case "logrt.count":
		flags |= MetricFlagLogCount | MetricFlagLogLogrt
	default:
		return nil, fmt.Errorf("Unsupported item key: %s", key)
	}
	return unsafe.Pointer(C.new_metric(ckey, C.ulong(lastLogsize), C.int(mtime), C.int(flags))), nil
}

func FreeActiveMetric(data unsafe.Pointer) {
	C.metric_free(C.ZBX_ACTIVE_METRIC_LP(data))
}

// TODO: add global regexp parameter
func ProcessLogCheck(data unsafe.Pointer, item *LogItem, refresh int) {
	C.metric_set_refresh(C.ZBX_ACTIVE_METRIC_LP(data), C.int(refresh))

	var clastLogsizeSent, clastLogsizeLast C.ulong
	var cmtimeSent, cmtimeLast C.int
	C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeSent, &cmtimeSent)
	clastLogsizeLast = clastLogsizeSent
	cmtimeLast = cmtimeSent

	var cerrmsg *C.char
	ret := C.process_log_check(C.char_lp_t(unsafe.Pointer(item)), 0, C.zbx_vector_ptr_lp_t(nil),
		C.ZBX_ACTIVE_METRIC_LP(data), C.zbx_process_value_func_t(C.process_value_cb), &clastLogsizeSent, &cmtimeSent,
		&cerrmsg)

	if ret == Fail {
		C.metric_set_unsupported(C.ZBX_ACTIVE_METRIC_LP(data))

		var err error
		if cerrmsg != nil {
			err = errors.New(C.GoString(cerrmsg))
			C.free(unsafe.Pointer(cerrmsg))
		} else {
			err = errors.New("Unknown error.")
		}
		result := &plugin.Result{
			Itemid: item.Itemid,
			Ts:     time.Now(),
			Error:  err,
		}
		item.Output.Write(result)
	} else {
		ret := C.metric_set_supported(C.ZBX_ACTIVE_METRIC_LP(data), clastLogsizeSent, cmtimeSent, clastLogsizeLast,
			cmtimeLast)

		if ret == Succeed {
			C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeLast, &cmtimeLast)
			lastLogsize := uint64(clastLogsizeLast)
			mtime := int(cmtimeLast)
			result := &plugin.Result{
				Itemid:      item.Itemid,
				Ts:          time.Now(),
				LastLogsize: &lastLogsize,
				Mtime:       &mtime,
			}
			item.Output.Write(result)
		}
	}
}

func SetMaxLinesPerSecond(num int) {
	C.CONFIG_MAX_LINES_PER_SECOND = C.int(num)
}
