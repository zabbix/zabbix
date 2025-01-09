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
#cgo CFLAGS: -I${SRCDIR}/../../../../include

#include "zbxsysinfo.h"
#include "zbxlog.h"
#include "../src/zabbix_agent/metrics/metrics.h"
#include "../src/zabbix_agent/logfiles/logfiles.h"
#include "zbx_item_constants.h"
#include "../src/libs/zbxnix/fatal.h"

typedef zbx_active_metric_t* ZBX_ACTIVE_METRIC_LP;
typedef zbx_vector_ptr_t * zbx_vector_ptr_lp_t;
typedef zbx_vector_expression_t * zbx_vector_expression_lp_t;
typedef char * char_lp_t;
typedef zbx_vector_pre_persistent_t * zbx_vector_pre_persistent_lp_t;

zbx_active_metric_t *new_metric(zbx_uint64_t itemid, char *key, zbx_uint64_t lastlogsize, int mtime, int flags)
{
	zbx_active_metric_t *metric = malloc(sizeof(zbx_active_metric_t));
	memset(metric, 0, sizeof(zbx_active_metric_t));
	metric->itemid = itemid;
	metric->key = key;
	metric->lastlogsize = lastlogsize;
	metric->mtime = mtime;
	metric->flags = (unsigned char)flags;
	metric->skip_old_data = (0 != metric->lastlogsize ? 0 : 1);
	metric->persistent_file_name = NULL;	// initialized but not used in Agent2

	return metric;
}

void metric_set_nextcheck(zbx_active_metric_t *metric, int nextcheck)
{
	metric->nextcheck = nextcheck;
}

void metric_get_meta(zbx_active_metric_t *metric, zbx_uint64_t *lastlogsize, int *mtime)
{
	*lastlogsize = metric->lastlogsize;
	*mtime = metric->mtime;
}

void metric_set_unsupported(zbx_active_metric_t *metric)
{
	metric->state = ITEM_STATE_NOTSUPPORTED;
	metric->error_count = 0;
	metric->start_time = 0.0;
	metric->processed_bytes = 0;
}

int metric_set_supported(zbx_active_metric_t *metric, zbx_uint64_t lastlogsize_sent, int mtime_sent,
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

void	metric_free(zbx_active_metric_t *metric)
{
	int	i;

	if (NULL == metric)
		return;

	zbx_free(metric->key);
	zbx_free(metric->delay);

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

static int	get_log_value(log_result_t *result, int index, char **value, int *state, zbx_uint64_t *lastlogsize,
		int *mtime)
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

int	process_value_cb(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, zbx_uint64_t itemid,
		const char *host, const char *key, const char *value, unsigned char state, zbx_uint64_t *lastlogsize,
		const int *mtime, unsigned long *timestamp, const char *source, unsigned short *severity,
		unsigned long *logeventid, unsigned char flags)
{
	ZBX_UNUSED(addrs);

	log_result_t *result = (log_result_t *)agent2_result;
	if (result->values.values_num == result->slots)
		return FAIL;

	add_log_value(result, value, state, *lastlogsize, *mtime);

	return SUCCEED;
}

#if !defined(__MINGW32__)

static ZBX_THREAD_LOCAL struct sigaction sa_old;

static void	fatal_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	zbx_log_fatal_info(context, ZBX_FATAL_LOG_FULL_INFO);

	sigaction(SIGSEGV, &sa_old, NULL);
	raise(sig);
}
#endif

static int	invoke_process_log_check(zbx_vector_ptr_t *agent2_result, zbx_vector_expression_t *regexps,
		zbx_active_metric_t *metric, zbx_process_value_func_t process_value_cb, zbx_uint64_t *lastlogsize_sent,
		int *mtime_sent, char **error, const zbx_config_tls_t *config_tls, int config_timeout,
		const char *config_source_ip, const char *config_hostname, int config_buffer_send, int config_buffer_size,
		 int config_max_lines_per_second)
{
	int	ret;
	zbx_vector_pre_persistent_t	vect;

#if !defined(__MINGW32__)
	struct sigaction	sa_new;
	sigemptyset(&sa_new.sa_mask);
	sa_new.sa_flags = SA_SIGINFO;
	sa_new.sa_sigaction = fatal_signal_handler;
	sigaction(SIGSEGV, &sa_new, &sa_old);
#endif

	zbx_vector_pre_persistent_create(&vect);

	ret = process_log_check(NULL, agent2_result, regexps, metric, process_value_cb, lastlogsize_sent, mtime_sent, error,
		&vect, config_tls, config_timeout, config_source_ip, config_hostname, config_buffer_send, config_buffer_size,
		config_max_lines_per_second);

#if !defined(__MINGW32__)
	sigaction(SIGSEGV, &sa_old, NULL);
#endif

	zbx_vector_pre_persistent_destroy(&vect);
	return ret;
}

void	zbx_config_tls_init_for_agent2(zbx_config_tls_t *config_tls, unsigned int accept, unsigned int connect,
		char *PSKIdentity, char *PSKKey, char *CAFile, char *CRLFile, char *CertFile, char *KeyFile,
		char *ServerCertIssuer, char *ServerCertSubject)
{
	config_tls->connect_mode	= connect;
	config_tls->accept_modes	= accept;

	config_tls->connect		= NULL;
	config_tls->accept		= NULL;
	config_tls->ca_file		= CAFile;
	config_tls->crl_file		= CRLFile;
	config_tls->server_cert_issuer	= ServerCertIssuer;
	config_tls->server_cert_subject	= ServerCertSubject;
	config_tls->cert_file		= CertFile;
	config_tls->key_file		= KeyFile;
	config_tls->psk_identity	= PSKIdentity;
	config_tls->psk_file		= PSKKey;
	config_tls->cipher_cert13	= NULL;
	config_tls->cipher_cert		= NULL;
	config_tls->cipher_psk13	= NULL;
	config_tls->cipher_psk		= NULL;
	config_tls->cipher_all13	= NULL;
	config_tls->cipher_all		= NULL;
	config_tls->cipher_cmd13	= NULL;
	config_tls->cipher_cmd		= NULL;

	return;
}

int	zbx_config_max_lines_per_second = 20;
*/
import "C"

import (
	"errors"
	"time"
	"unsafe"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/agent2/pkg/tls"
	"golang.zabbix.com/sdk/log"
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

func NewActiveMetric(
	itemid uint64,
	key string,
	params []string,
	lastLogsize uint64,
	mtime int32,
) (data unsafe.Pointer, err error) {
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
			return nil, errors.New("The eighth parameter (persistent directory) is not supported by " +
				"Agent2.")
		}
		flags |= MetricFlagLogCount | MetricFlagLogLog
	case "logrt.count":
		if len(params) >= 8 && params[7] != "" {
			return nil, errors.New("The eighth parameter (persistent directory) is not supported by " +
				"Agent2.")
		}
		flags |= MetricFlagLogCount | MetricFlagLogLogrt
	case "eventlog":
		flags |= MetricFlagLogEventlog
	case "eventlog.count":
		flags |= MetricFlagLogCount | MetricFlagLogEventlog
	default:
		return nil, errors.New("Unsupported item key.")
	}

	/* will be freed in FreeActiveMetric */
	ckey := C.CString(itemutil.MakeKey(key, params))

	log.Tracef("Calling C function \"new_metric()\"")
	return unsafe.Pointer(C.new_metric(C.zbx_uint64_t(itemid), ckey, C.zbx_uint64_t(lastLogsize), C.int(mtime),
		C.int(flags))), nil
}

func FreeActiveMetric(data unsafe.Pointer) {
	log.Tracef("Calling C function \"metric_free()\"")
	C.metric_free(C.ZBX_ACTIVE_METRIC_LP(data))
}

func ProcessLogCheck(data unsafe.Pointer, item *LogItem, nextcheck int, cblob unsafe.Pointer, itemid uint64) {
	log.Tracef("Calling C function \"metric_set_nextcheck()\"")
	C.metric_set_nextcheck(C.ZBX_ACTIVE_METRIC_LP(data), C.int(nextcheck))

	var clastLogsizeSent, clastLogsizeLast C.zbx_uint64_t
	var cmtimeSent, cmtimeLast C.int
	log.Tracef("Calling C function \"metric_get_meta()\"")
	C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeSent, &cmtimeSent)
	clastLogsizeLast = clastLogsizeSent
	cmtimeLast = cmtimeSent

	var tlsConfig *tls.Config
	var err error
	var ctlsConfig C.zbx_config_tls_t
	var ctlsConfig_p *C.zbx_config_tls_t

	if tlsConfig, err = agent.GetTLSConfig(&agent.Options); err != nil {
		r := &LogResult{
			Ts:    time.Now(),
			Error: err,
		}
		item.Results = append(item.Results, r)

		return
	}

	log.Tracef("Calling C function \"new_log_result()\"")
	result := C.new_log_result(C.int(item.Output.PersistSlotsAvailable()))

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
			cPSKIdentity, cPSKKey, cCAFile, cCRLFile, cCertFile, cKeyFile, cServerCertIssuer, cServerCertSubject)
		ctlsConfig_p = &ctlsConfig
	}

	var cerrmsg *C.char

	cSourceIP := (C.CString)(agent.Options.SourceIP)
	cHostname := (C.CString)(agent.Options.Hostname)

	defer func() {
		log.Tracef("Calling C function \"free(cSourceIP)\"")
		C.free(unsafe.Pointer(cSourceIP))
		log.Tracef("Calling C function \"free(cHostname)\"")
		C.free(unsafe.Pointer(cHostname))
	}()

	log.Tracef("Calling C function \"invoke_process_log_check()\"")
	ret := C.invoke_process_log_check(C.zbx_vector_ptr_lp_t(unsafe.Pointer(result)),
		C.zbx_vector_expression_lp_t(cblob), C.ZBX_ACTIVE_METRIC_LP(data),
		C.zbx_process_value_func_t(C.process_value_cb), &clastLogsizeSent,
		&cmtimeSent, &cerrmsg, ctlsConfig_p, (C.int)(agent.Options.Timeout),
		cSourceIP, cHostname, (C.int)(agent.Options.BufferSend),
		(C.int)(agent.Options.BufferSize), (C.zbx_config_max_lines_per_second))

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
		r := &LogResult{
			Ts:    time.Now(),
			Error: err,
		}
		item.Results = append(item.Results, r)
	} else {
		log.Tracef("Calling C function \"metric_set_supported()\"")
		ret := C.metric_set_supported(C.ZBX_ACTIVE_METRIC_LP(data), clastLogsizeSent, cmtimeSent,
			clastLogsizeLast, cmtimeLast)

		if ret == Succeed {
			log.Tracef("Calling C function \"metric_get_meta()\"")
			C.metric_get_meta(C.ZBX_ACTIVE_METRIC_LP(data), &clastLogsizeLast, &cmtimeLast)
			r := &LogResult{
				Ts:          time.Now(),
				LastLogsize: uint64(clastLogsizeLast),
				Mtime:       int(cmtimeLast),
			}
			item.Results = append(item.Results, r)
		}
	}
}

func SetMaxLinesPerSecond(num int) {
	C.zbx_config_max_lines_per_second = C.int(num)
}
