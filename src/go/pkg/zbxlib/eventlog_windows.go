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

package zbxlib

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include -I${SRCDIR}/../../../../../build/win32/include

#include "common.h"
#include "sysinfo.h"
#include "log.h"
#include "../src/zabbix_agent/metrics.h"
#include "../src/zabbix_agent/logfiles/logfiles.h"

extern int CONFIG_EVENTLOG_MAX_LINES_PER_SECOND;

typedef ZBX_ACTIVE_METRIC* ZBX_ACTIVE_METRIC_LP;
typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;
typedef char * char_lp_t;

void metric_set_refresh(ZBX_ACTIVE_METRIC *metric, int refresh);
void metric_get_meta(ZBX_ACTIVE_METRIC *metric, zbx_uint64_t *lastlogsize, int *mtime);
void metric_set_unsupported(ZBX_ACTIVE_METRIC *metric);
int metric_set_supported(ZBX_ACTIVE_METRIC *metric, zbx_uint64_t lastlogsize_sent, int mtime_sent,
		zbx_uint64_t lastlogsize_last, int mtime_last);

int	process_eventlog_check(char *server, unsigned short port, zbx_vector_ptr_t *regexps, ZBX_ACTIVE_METRIC *metric,
		zbx_process_value_func_t process_value_cb, zbx_uint64_t *lastlogsize_sent, char **error);

typedef struct
{
	char *value;
	char *source;
	int timestamp;
	int logeventid;
	int severity;

	int state;
	zbx_uint64_t lastlogsize;
}
eventlog_value_t;

typedef struct
{
	zbx_vector_ptr_t values;
	int slots;
}
eventlog_result_t, *eventlog_result_lp_t;

static eventlog_result_t *new_eventlog_result(int slots)
{
	eventlog_result_t *result;

	result = (eventlog_result_t *)zbx_malloc(NULL, sizeof(eventlog_result_t));
	zbx_vector_ptr_create(&result->values);
	result->slots = slots;
	return result;
}

static void add_eventlog_value(eventlog_result_t *result, const char *value, const char *source, int logeventid, int severity,
	int timestamp, int state, zbx_uint64_t lastlogsize)
{
	eventlog_value_t *log;
	log = (eventlog_value_t *)zbx_malloc(NULL, sizeof(eventlog_value_t));
	log->value = zbx_strdup(NULL, value);
	log->source = zbx_strdup(NULL, source);
	log->logeventid = logeventid;
	log->severity = severity;
	log->timestamp = timestamp;
	log->state = state;
	log->lastlogsize = lastlogsize;
	zbx_vector_ptr_append(&result->values, log);
}

static int get_eventlog_value(eventlog_result_t *result, int index, char **value, char **source, int *logeventid,
	 int *severity, int *timestamp, int *state, zbx_uint64_t *lastlogsize)
{
	eventlog_value_t *log;

	if (index == result->values.values_num)
		return FAIL;

	log = (eventlog_value_t *)result->values.values[index];
	*value = log->value;
	*source = log->source;
	*logeventid = log->logeventid;
	*severity = log->severity;
	*timestamp = log->timestamp;
	*state = log->state;
	*lastlogsize = log->lastlogsize;
	return SUCCEED;
}

static void free_eventlog_value(eventlog_value_t *log)
{
	zbx_free(log->value);
	zbx_free(log->source);
	zbx_free(log);
}

static void free_eventlog_result(eventlog_result_t *result)
{
	zbx_vector_ptr_clear_ext(&result->values, (zbx_clean_func_t)free_eventlog_value);
	zbx_vector_ptr_destroy(&result->values);
	zbx_free(result);
}

int	process_eventlog_value_cb(const char *server, unsigned short port, const char *host, const char *key,
		const char *value, unsigned char state, zbx_uint64_t *lastlogsize, const int *mtime,
		unsigned long *timestamp, const char *source, unsigned short *severity, unsigned long *logeventid,
		unsigned char flags)
{
	eventlog_result_t *result = (eventlog_result_t *)server;
	if (result->values.values_num == result->slots)
		return FAIL;

	add_eventlog_value(result, value, source, *logeventid, *severity, *timestamp, state, *lastlogsize);
	return SUCCEED;
}
*/
import "C"

import (
	"errors"
	"time"
	"unsafe"

	"zabbix.com/pkg/log"
)

type EventLogItem struct {
	LastTs  time.Time // the last log value timestamp + 1ns
	Results []*EventLogResult
	Output  ResultWriter
}

type EventLogResult struct {
	Value          *string
	EventSource    *string
	EventID        *int
	EventTimestamp *int
	EventSeverity  *int
	Ts             time.Time
	Error          error
	LastLogsize    uint64
	Mtime          int
}

func ProcessEventLogCheck(data unsafe.Pointer, item *EventLogItem, refresh int, cblob unsafe.Pointer) {
	log.Tracef("Calling C function \"metric_set_refresh()\"")
	C.metric_set_refresh(C.ZBX_ACTIVE_METRIC_LP(data), C.int(refresh))

	var clastLogsizeSent, clastLogsizeLast C.zbx_uint64_t
	var cstate, cmtime C.int
	log.Tracef("Calling C function \"metric_get_meta()\"")
	C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeSent, &cmtime)
	clastLogsizeLast = clastLogsizeSent

	log.Tracef("Calling C function \"new_eventlog_result()\"")
	result := C.new_eventlog_result(C.int(item.Output.PersistSlotsAvailable()))

	var cerrmsg *C.char
	log.Tracef("Calling C function \"process_eventlog_check()\"")
	ret := C.process_eventlog_check(C.char_lp_t(unsafe.Pointer(result)), 0, C.zbx_vector_ptr_lp_t(cblob),
		C.ZBX_ACTIVE_METRIC_LP(data), C.zbx_process_value_func_t(C.process_eventlog_value_cb), &clastLogsizeSent,
		&cerrmsg)

	// add cached results
	var cvalue, csource *C.char
	var clogeventid, cseverity, ctimestamp C.int
	var clastlogsize C.zbx_uint64_t
	logTs := time.Now()
	if logTs.Before(item.LastTs) {
		logTs = item.LastTs
	}
	log.Tracef("Calling C function \"get_eventlog_value()\"")
	for i := 0; C.get_eventlog_value(result, C.int(i), &cvalue, &csource, &clogeventid, &cseverity, &ctimestamp, &cstate,
		&clastlogsize) != C.FAIL; i++ {

		var value, source string
		var logeventid, severity, timestamp int
		var r EventLogResult
		if cstate == C.ITEM_STATE_NORMAL {
			value = C.GoString(cvalue)
			source = C.GoString(csource)
			logeventid = int(clogeventid)
			severity = int(cseverity)
			timestamp = int(ctimestamp)

			r = EventLogResult{
				Value:          &value,
				EventSource:    &source,
				EventID:        &logeventid,
				EventSeverity:  &severity,
				EventTimestamp: &timestamp,
				Ts:             logTs,
				LastLogsize:    uint64(clastlogsize),
			}

		} else {
			r = EventLogResult{
				Error:       errors.New(C.GoString(cvalue)),
				Ts:          logTs,
				LastLogsize: uint64(clastlogsize),
			}

		}

		item.Results = append(item.Results, &r)
		logTs = logTs.Add(time.Nanosecond)
	}
	log.Tracef("Calling C function \"free_eventlog_result()\"")
	C.free_eventlog_result(result)

	item.LastTs = logTs

	if ret == C.FAIL {
		log.Tracef("Calling C function \"metric_set_unsupported()\"")
		C.metric_set_unsupported(C.ZBX_ACTIVE_METRIC_LP(data))

		var err error
		if cerrmsg != nil {
			err = errors.New(C.GoString(cerrmsg))
			log.Tracef("Calling C function \"free()\"")
			C.free(unsafe.Pointer(cerrmsg))
		} else {
			err = errors.New("Unknown error.")
		}
		result := &EventLogResult{
			Ts:    time.Now(),
			Error: err,
		}
		item.Results = append(item.Results, result)
	} else {
		log.Tracef("Calling C function \"metric_set_supported()\"")
		ret := C.metric_set_supported(C.ZBX_ACTIVE_METRIC_LP(data), clastLogsizeSent, 0, clastLogsizeLast, 0)

		if ret == Succeed {
			log.Tracef("Calling C function \"metric_get_meta()\"")
			C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeLast, &cmtime)
			result := EventLogResult{
				Ts:          time.Now(),
				LastLogsize: uint64(clastLogsizeLast),
			}
			item.Results = append(item.Results, &result)
		}
	}
}

func SetEventlogMaxLinesPerSecond(num int) {
	C.CONFIG_EVENTLOG_MAX_LINES_PER_SECOND = C.int(num)
}
