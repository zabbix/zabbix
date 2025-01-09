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

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include -I${SRCDIR}/../../../../../build/win32/include

#include "zbxsysinfo.h"
#include "zbxlog.h"
#include "../src/zabbix_agent/metrics/metrics.h"
#include "../src/zabbix_agent/logfiles/logfiles.h"
#include "zbx_item_constants.h"

void	zbx_config_tls_init_for_agent2(zbx_config_tls_t *config_tls, unsigned int accept, unsigned int connect,
		char *PSKIdentity, char *PSKKey, char *CAFile, char *CRLFile, char *CertFile, char *KeyFile,
		char *ServerCertIssuer, char *ServerCertSubject);

int	zbx_config_eventlog_max_lines_per_second = 20;
typedef zbx_active_metric_t* ZBX_ACTIVE_METRIC_LP;
typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;
typedef zbx_vector_expression_t * zbx_vector_expression_lp_t;
typedef char * char_lp_t;

void metric_set_nextcheck(zbx_active_metric_t *metric, int nextcheck);
void metric_get_meta(zbx_active_metric_t *metric, zbx_uint64_t *lastlogsize, int *mtime);
void metric_set_unsupported(zbx_active_metric_t *metric);
int metric_set_supported(zbx_active_metric_t *metric, zbx_uint64_t lastlogsize_sent, int mtime_sent,
		zbx_uint64_t lastlogsize_last, int mtime_last);

int	process_eventlog_check(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
		zbx_vector_expression_t *regexps, zbx_active_metric_t *metric, zbx_process_value_func_t process_value_cb,
		zbx_uint64_t *lastlogsize_sent, const zbx_config_tls_t *config_tls, int config_timeout,
		const char *config_source_ip, const char *config_hostname, int config_buffer_send,
		int config_buffer_size, int config_eventlog_max_lines_per_second, char **error);

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

static void add_eventlog_value(eventlog_result_t *result, const char *value, const char *source, int logeventid,
		int severity, int timestamp, int state, zbx_uint64_t lastlogsize)
{
	eventlog_value_t *log;
	log = (eventlog_value_t *)zbx_malloc(NULL, sizeof(eventlog_value_t));
	log->value = zbx_strdup(NULL, value);

	if (NULL != source)
		log->source = zbx_strdup(NULL, source);
	else
		log->source = NULL;

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

int	process_eventlog_value_cb(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, zbx_uint64_t itemid,
		const char *host, const char *key, const char *value, unsigned char state, zbx_uint64_t *lastlogsize,
		const int *mtime, const unsigned long *timestamp, const char *source, const unsigned short *severity,
		const unsigned long *logeventid, unsigned char flags, const zbx_config_tls_t *config_tls,
		int config_timeout, const char *config_source_ip)
{
	ZBX_UNUSED(addrs);
	ZBX_UNUSED(itemid);
	ZBX_UNUSED(host);
	ZBX_UNUSED(key);
	ZBX_UNUSED(mtime);
	ZBX_UNUSED(flags);
	ZBX_UNUSED(config_tls);
	ZBX_UNUSED(config_timeout);
	ZBX_UNUSED(config_source_ip);

	eventlog_result_t *result = (eventlog_result_t *)agent2_result;
	if (result->values.values_num == result->slots)
		return FAIL;

	add_eventlog_value(result, value, source, *logeventid, *severity, *timestamp, state, *lastlogsize);

	return SUCCEED;
}
int	process_eventlog_count_value_cb(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, zbx_uint64_t itemid,
		const char *host, const char *key, const char *value, unsigned char state, zbx_uint64_t *lastlogsize,
		const int *mtime, const unsigned long *timestamp, const char *source, const unsigned short *severity,
		const unsigned long *logeventid, unsigned char flags, const zbx_config_tls_t *config_tls,
		int config_timeout, const char *config_source_ip)
{
	ZBX_UNUSED(addrs);
	ZBX_UNUSED(itemid);
	ZBX_UNUSED(host);
	ZBX_UNUSED(key);
	ZBX_UNUSED(mtime);
	ZBX_UNUSED(flags);
	ZBX_UNUSED(config_tls);
	ZBX_UNUSED(config_timeout);
	ZBX_UNUSED(config_source_ip);

	ZBX_UNUSED(source);
	ZBX_UNUSED(logeventid);
	ZBX_UNUSED(severity);
	ZBX_UNUSED(timestamp);
	ZBX_UNUSED(state);

	eventlog_result_t *result = (eventlog_result_t *)agent2_result;
	if (result->values.values_num == result->slots)
		return FAIL;

	add_eventlog_value(result, value, NULL, 0, 0, 0, 0, *lastlogsize);

	return SUCCEED;
}
*/
import "C"

import (
	"errors"
	"time"
	"unsafe"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/tls"
	"golang.zabbix.com/sdk/log"
)

type EventLogItem struct {
	Itemid  uint64
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

func ProcessEventLogCheck(data unsafe.Pointer, item *EventLogItem, nextcheck int, cblob unsafe.Pointer, isCountItem bool) {
	log.Tracef("Calling C function \"metric_set_nextcheck()\"")
	C.metric_set_nextcheck(C.ZBX_ACTIVE_METRIC_LP(data), C.int(nextcheck))

	var clastLogsizeSent, clastLogsizeLast C.zbx_uint64_t
	var cstate, cmtime C.int
	log.Tracef("Calling C function \"metric_get_meta()\"")
	C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeSent, &cmtime)
	clastLogsizeLast = clastLogsizeSent

	log.Tracef("Calling C function \"new_eventlog_result()\"")
	result := C.new_eventlog_result(C.int(item.Output.PersistSlotsAvailable()))

	var tlsConfig *tls.Config
	var err error
	var ctlsConfig C.zbx_config_tls_t
	var ctlsConfig_p *C.zbx_config_tls_t

	if tlsConfig, err = agent.GetTLSConfig(&agent.Options); err != nil {
		res := &EventLogResult{
			Ts:    time.Now(),
			Error: err,
		}
		item.Results = append(item.Results, res)

		log.Tracef("Calling C function \"free_eventlog_result()\"")
		C.free_eventlog_result(result)

		return
	}

	if nil != tlsConfig {
		cPSKIdentity := (C.CString)(tlsConfig.PSKIdentity)
		cPSKKey := (C.CString)(tlsConfig.PSKKey)
		cCAFile := (C.CString)(tlsConfig.CAFile)
		cCRLFile := (C.CString)(tlsConfig.CRLFile)
		cCertFile := (C.CString)(tlsConfig.CertFile)
		cKeyFile := (C.CString)(tlsConfig.KeyFile)
		cServerCertIssuer := (C.CString)(tlsConfig.ServerCertIssuer)
		cServerCertSubject := (C.CString)(tlsConfig.ServerCertSubject)

		defer func() {
			log.Tracef("Calling C function \"free(cPSKIdentity)\"")
			C.free(unsafe.Pointer(cPSKIdentity))
			log.Tracef("Calling C function \"free(cPSKKey)\"")
			C.free(unsafe.Pointer(cPSKKey))
			log.Tracef("Calling C function \"free(cCAFile)\"")
			C.free(unsafe.Pointer(cCAFile))
			log.Tracef("Calling C function \"free(cCRLFile)\"")
			C.free(unsafe.Pointer(cCRLFile))
			log.Tracef("Calling C function \"free(cCertFile)\"")
			C.free(unsafe.Pointer(cCertFile))
			log.Tracef("Calling C function \"free(cKeyFile)\"")
			C.free(unsafe.Pointer(cKeyFile))
			log.Tracef("Calling C function \"free(cServerCertIssuer)\"")
			C.free(unsafe.Pointer(cServerCertIssuer))
			log.Tracef("Calling C function \"free(cServerCertSubject)\"")
			C.free(unsafe.Pointer(cServerCertSubject))
		}()

		log.Tracef("Calling C function \"zbx_config_tls_init_for_agent2()\"")
		C.zbx_config_tls_init_for_agent2(&ctlsConfig, (C.uint)(tlsConfig.Accept), (C.uint)(tlsConfig.Connect),
			cPSKIdentity, cPSKKey, cCAFile, cCRLFile, cCertFile, cKeyFile, cServerCertIssuer,
			cServerCertSubject)
		ctlsConfig_p = &ctlsConfig
	}

	procValueFunc := C.process_eventlog_value_cb
	if isCountItem {
		procValueFunc = C.process_eventlog_count_value_cb
	}

	var cerrmsg *C.char
	log.Tracef("Calling C function \"process_eventlog_check()\"")

	cSourceIP := (C.CString)(agent.Options.SourceIP)
	cHostname := (C.CString)(agent.Options.Hostname)

	defer func() {
		log.Tracef("Calling C function \"free(cSourceIP)\"")
		C.free(unsafe.Pointer(cSourceIP))
		log.Tracef("Calling C function \"free(cHostname)\"")
		C.free(unsafe.Pointer(cHostname))
	}()

	ret := C.process_eventlog_check(nil, C.zbx_vector_ptr_lp_t(unsafe.Pointer(result)),
		C.zbx_vector_expression_lp_t(cblob), C.ZBX_ACTIVE_METRIC_LP(data),
		C.zbx_process_value_func_t(procValueFunc), &clastLogsizeSent, ctlsConfig_p,
		(C.int)(agent.Options.Timeout), cSourceIP, cHostname, (C.int)(agent.Options.BufferSend),
		(C.int)(agent.Options.BufferSize), (C.int)(C.zbx_config_eventlog_max_lines_per_second), &cerrmsg)

	// add cached results
	var cvalue, csource *C.char
	var clogeventid, cseverity, ctimestamp C.int
	var clastlogsize C.zbx_uint64_t
	logTs := time.Now()
	if logTs.Before(item.LastTs) {
		logTs = item.LastTs
	}
	log.Tracef("Calling C function \"get_eventlog_value()\"")
	for i := 0; C.get_eventlog_value(result, C.int(i), &cvalue, &csource, &clogeventid, &cseverity, &ctimestamp,
		&cstate, &clastlogsize) != C.FAIL; i++ {

		var value, source string
		var logeventid, severity, timestamp int
		var r EventLogResult
		if cstate == C.ITEM_STATE_NORMAL {
			if !isCountItem {
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
				value = C.GoString(cvalue)

				r = EventLogResult{
					Value:          &value,
					EventSource:    nil,
					EventID:        nil,
					EventSeverity:  nil,
					EventTimestamp: nil,
					Ts:             logTs,
					LastLogsize:    uint64(clastlogsize),
				}
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
	C.zbx_config_eventlog_max_lines_per_second = C.int(num)
}
