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
#cgo CFLAGS: -I${SRCDIR}/../../../../include

#include "common.h"
#include "sysinfo.h"
#include "log.h"
#include "../src/zabbix_agent/metrics.h"
#include "../src/zabbix_agent/logfiles/logfiles.h"

extern int CONFIG_MAX_LINES_PER_SECOND;

typedef ZBX_ACTIVE_METRIC* ZBX_ACTIVE_METRIC_LP;
typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;
typedef char * char_lp_t;
typedef zbx_vector_pre_persistent_t * zbx_vector_pre_persistent_lp_t;

ZBX_ACTIVE_METRIC *new_metric(char *key, zbx_uint64_t lastlogsize, int mtime, int flags)
{
	ZBX_ACTIVE_METRIC *metric = malloc(sizeof(ZBX_ACTIVE_METRIC));
	memset(metric, 0, sizeof(ZBX_ACTIVE_METRIC));
	metric->key = key;
	// key_orig is used in error messages, consider using "itemid: <itemid>" instead of the key
	metric->key_orig = zbx_strdup(NULL, key);
	metric->lastlogsize = lastlogsize;
	metric->mtime = mtime;
	metric->flags = (unsigned char)flags;
	metric->skip_old_data = (0 != metric->lastlogsize ? 0 : 1);
	metric->persistent_file_name = NULL;	// initialized but not used in Agent2
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

	if (NULL == metric)
		return;

	zbx_free(metric->key);
	zbx_free(metric->key_orig);

	for (i = 0; i < metric->logfiles_num; i++)
		zbx_free(metric->logfiles[i].filename);

	zbx_free(metric->logfiles);
	zbx_free(metric->persistent_file_name);
	zbx_free(metric);
}

typedef struct
{
	char *value;
	int state;
	zbx_uint64_t lastlogsize;
	int mtime;
}
log_value_t;

typedef struct
{
	zbx_vector_ptr_t values;
	int slots;
}
log_result_t, *log_result_lp_t;

static log_result_t *new_log_result(int slots)
{
	log_result_t *result;

	result = (log_result_t *)zbx_malloc(NULL, sizeof(log_result_t));
	zbx_vector_ptr_create(&result->values);
	result->slots = slots;
	return result;
}

static void add_log_value(log_result_t *result, const char *value, int state, zbx_uint64_t lastlogsize, int mtime)
{
	log_value_t *log;
	log = (log_value_t *)zbx_malloc(NULL, sizeof(log_value_t));
	log->value = zbx_strdup(NULL, value);
	log->state = state;
	log->lastlogsize = lastlogsize;
	log->mtime = mtime;
	zbx_vector_ptr_append(&result->values, log);
}

static int get_log_value(log_result_t *result, int index, char **value, int *state, zbx_uint64_t *lastlogsize, int *mtime)
{
	log_value_t *log;

	if (index == result->values.values_num)
		return FAIL;

	log = (log_value_t *)result->values.values[index];
	*value = log->value;
	*state = log->state;
	*lastlogsize = log->lastlogsize;
	*mtime = log->mtime;
	return SUCCEED;
}

static void free_log_value(log_value_t *log)
{
	zbx_free(log->value);
	zbx_free(log);
}

static void free_log_result(log_result_t *result)
{
	zbx_vector_ptr_clear_ext(&result->values, (zbx_clean_func_t)free_log_value);
	zbx_vector_ptr_destroy(&result->values);
	zbx_free(result);
}

int	process_value_cb(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, const char *host, const char *key,
		const char *value, unsigned char state, zbx_uint64_t *lastlogsize, const int *mtime,
		unsigned long *timestamp, const char *source, unsigned short *severity, unsigned long *logeventid,
		unsigned char flags)
{
	ZBX_UNUSED(addrs);

	log_result_t *result = (log_result_t *)agent2_result;
	if (result->values.values_num == result->slots)
		return FAIL;

	add_log_value(result, value, state, *lastlogsize, *mtime);
	return SUCCEED;
}

static zbx_vector_pre_persistent_lp_t new_prep_vec(void)
{
	zbx_vector_pre_persistent_lp_t vect;

	vect = (zbx_vector_pre_persistent_lp_t)zbx_malloc(NULL, sizeof(zbx_vector_pre_persistent_t));
	zbx_vector_pre_persistent_create(vect);
	return vect;
}

static void free_prep_vec(zbx_vector_pre_persistent_lp_t vect)
{
	// In Agent2 this vector is expected to be empty because 'persistent directory' parameter is not allowed.
	// Therefore a simplified cleanup is used.
	zbx_vector_pre_persistent_destroy(vect);
	zbx_free(vect);
}
*/
import "C"

import (
	"errors"
	"time"
	"unsafe"

	"zabbix.com/pkg/itemutil"
	"git.zabbix.com/ap/plugin-support/log"
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

type ResultWriter interface {
	PersistSlotsAvailable() int
}

type LogItem struct {
	LastTs  time.Time // the last log value timestamp + 1ns
	Results []*LogResult
	Output  ResultWriter
}

type LogResult struct {
	Value       *string
	Ts          time.Time
	Error       error
	LastLogsize uint64
	Mtime       int
}

func NewActiveMetric(key string, params []string, lastLogsize uint64, mtime int32) (data unsafe.Pointer, err error) {
	flags := MetricFlagNew | MetricFlagPersistent
	switch key {
	case "log":
		if len(params) >= 9 && params[8] != "" {
			return nil, errors.New("The ninth parameter (persistent directory) is not supported by Agent2.")
		}
		flags |= MetricFlagLogLog
	case "logrt":
		if len(params) >= 9 && params[8] != "" {
			return nil, errors.New("The ninth parameter (persistent directory) is not supported by Agent2.")
		}
		flags |= MetricFlagLogLogrt
	case "log.count":
		if len(params) >= 8 && params[7] != "" {
			return nil, errors.New("The eighth parameter (persistent directory) is not supported by Agent2.")
		}
		flags |= MetricFlagLogCount | MetricFlagLogLog
	case "logrt.count":
		if len(params) >= 8 && params[7] != "" {
			return nil, errors.New("The eighth parameter (persistent directory) is not supported by Agent2.")
		}
		flags |= MetricFlagLogCount | MetricFlagLogLogrt
	case "eventlog":
		flags |= MetricFlagLogEventlog
	default:
		return nil, errors.New("Unsupported item key.")
	}
	ckey := C.CString(itemutil.MakeKey(key, params))
	log.Tracef("Calling C function \"new_metric()\"")
	return unsafe.Pointer(C.new_metric(ckey, C.zbx_uint64_t(lastLogsize), C.int(mtime), C.int(flags))), nil
}

func FreeActiveMetric(data unsafe.Pointer) {
	log.Tracef("Calling C function \"metric_free()\"")
	C.metric_free(C.ZBX_ACTIVE_METRIC_LP(data))
}

func ProcessLogCheck(data unsafe.Pointer, item *LogItem, refresh int, cblob unsafe.Pointer) {
	log.Tracef("Calling C function \"metric_set_refresh()\"")
	C.metric_set_refresh(C.ZBX_ACTIVE_METRIC_LP(data), C.int(refresh))

	var clastLogsizeSent, clastLogsizeLast C.zbx_uint64_t
	var cmtimeSent, cmtimeLast C.int
	log.Tracef("Calling C function \"metric_get_meta()\"")
	C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeSent, &cmtimeSent)
	clastLogsizeLast = clastLogsizeSent
	cmtimeLast = cmtimeSent

	log.Tracef("Calling C function \"new_log_result()\"")
	result := C.new_log_result(C.int(item.Output.PersistSlotsAvailable()))

	var cerrmsg *C.char
	log.Tracef("Calling C function \"new_prep_vec()\"")
	cprepVec := C.new_prep_vec() // In Agent2 it is always empty vector. Not used but required for linking.
	log.Tracef("Calling C function \"process_log_check()\"")
	ret := C.process_log_check(nil, C.zbx_vector_ptr_lp_t(unsafe.Pointer(result)), C.zbx_vector_ptr_lp_t(cblob),
		C.ZBX_ACTIVE_METRIC_LP(data), C.zbx_process_value_func_t(C.process_value_cb), &clastLogsizeSent,
		&cmtimeSent, &cerrmsg, cprepVec)
	log.Tracef("Calling C function \"free_prep_vec()\"")
	C.free_prep_vec(cprepVec)

	// add cached results
	var cvalue *C.char
	var clastlogsize C.zbx_uint64_t
	var cstate, cmtime C.int
	logTs := time.Now()
	if logTs.Before(item.LastTs) {
		logTs = item.LastTs
	}
	log.Tracef("Calling C function \"get_log_value()\"")
	for i := 0; C.get_log_value(result, C.int(i), &cvalue, &cstate, &clastlogsize, &cmtime) != C.FAIL; i++ {
		var value string
		var err error
		if cstate == C.ITEM_STATE_NORMAL {
			value = C.GoString(cvalue)
		} else {
			err = errors.New(C.GoString(cvalue))
		}

		r := &LogResult{
			Value:       &value,
			Ts:          logTs,
			Error:       err,
			LastLogsize: uint64(clastlogsize),
			Mtime:       int(cmtime),
		}

		item.Results = append(item.Results, r)
		logTs = logTs.Add(time.Nanosecond)
	}
	log.Tracef("Calling C function \"free_log_result()\"")
	C.free_log_result(result)

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
		result := &LogResult{
			Ts:    time.Now(),
			Error: err,
		}
		item.Results = append(item.Results, result)
	} else {
		log.Tracef("Calling C function \"metric_set_supported()\"")
		ret := C.metric_set_supported(C.ZBX_ACTIVE_METRIC_LP(data), clastLogsizeSent, cmtimeSent, clastLogsizeLast,
			cmtimeLast)

		if ret == Succeed {
			log.Tracef("Calling C function \"metric_get_meta()\"")
			C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeLast, &cmtimeLast)
			result := &LogResult{
				Ts:          time.Now(),
				LastLogsize: uint64(clastLogsizeLast),
				Mtime:       int(cmtimeLast),
			}
			item.Results = append(item.Results, result)
		}
	}
}

func SetMaxLinesPerSecond(num int) {
	C.CONFIG_MAX_LINES_PER_SECOND = C.int(num)
}
