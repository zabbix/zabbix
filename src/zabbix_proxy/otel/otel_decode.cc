/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "otel_decode.h"
#include "opentelemetry/proto/collector/logs/v1/logs_service.grpc.pb.h"
#include "opentelemetry/proto/collector/metrics/v1/metrics_service.grpc.pb.h"
#include "opentelemetry/proto/collector/trace/v1/trace_service.grpc.pb.h"
#include <string>

extern "C" {
#include "zbxjson.h"
}

namespace otlpm = opentelemetry::proto::metrics::v1;
namespace otlpmc = opentelemetry::proto::collector::metrics::v1;
namespace otlplc = opentelemetry::proto::collector::logs::v1;
namespace otlpt = opentelemetry::proto::trace::v1;

static std::string	bytes_to_hex(const std::string &b)
{
	static const char	*hex = "0123456789abcdef";
	std::string		out;

	out.reserve(b.size() * 2);
	for (unsigned char c : b)
	{
		out += hex[c >> 4];
		out += hex[c & 0xf];
	}
	return out;
}

static void	any_value_add_to_json(struct zbx_json *j, const char *key,
		const opentelemetry::proto::common::v1::AnyValue &v)
{
	using AV = opentelemetry::proto::common::v1::AnyValue;

	switch (v.value_case())
	{
		case AV::kStringValue:
			zbx_json_addstring(j, key, v.string_value().c_str(), ZBX_JSON_TYPE_STRING);
			break;
		case AV::kBoolValue:
			zbx_json_addstring(j, key, v.bool_value() ? "true" : "false", ZBX_JSON_TYPE_INT);
			break;
		case AV::kIntValue:
			zbx_json_addint64(j, key, v.int_value());
			break;
		case AV::kDoubleValue:
			zbx_json_adddouble(j, key, v.double_value());
			break;
		case AV::kBytesValue:
			zbx_json_addstring(j, key, bytes_to_hex(v.bytes_value()).c_str(), ZBX_JSON_TYPE_STRING);
			break;
		case AV::kArrayValue:
			zbx_json_addarray(j, key);
			for (const auto &e : v.array_value().values())
				any_value_add_to_json(j, NULL, e);
			zbx_json_close(j);
			break;
		case AV::kKvlistValue:
			zbx_json_addobject(j, key);
			for (const auto &kv : v.kvlist_value().values())
				any_value_add_to_json(j, kv.key().c_str(), kv.value());
			zbx_json_close(j);
			break;
		default:
			zbx_json_addstring(j, key, "", ZBX_JSON_TYPE_STRING);
			break;
	}
}

static std::string	any_value_to_string(const opentelemetry::proto::common::v1::AnyValue &v)
{
	using AV = opentelemetry::proto::common::v1::AnyValue;

	switch (v.value_case())
	{
		case AV::kStringValue:
			return v.string_value();
		case AV::kBoolValue:
			return v.bool_value() ? "true" : "false";
		case AV::kIntValue:
			return std::to_string(v.int_value());
		case AV::kDoubleValue:
			return std::to_string(v.double_value());
		case AV::kBytesValue:
			return bytes_to_hex(v.bytes_value());
		case AV::kKvlistValue:
		{
			struct zbx_json	j;
			std::string	out;

			zbx_json_init(&j, 1024);			/* object root */
			for (const auto &kv : v.kvlist_value().values())
				any_value_add_to_json(&j, kv.key().c_str(), kv.value());
			out = j.buffer;
			zbx_json_free(&j);
			return out;
		}
		case AV::kArrayValue:
		{
			struct zbx_json	j;
			std::string	out;

			zbx_json_initarray(&j, 1024);			/* array root */
			for (const auto &e : v.array_value().values())
				any_value_add_to_json(&j, NULL, e);
			out = j.buffer;
			zbx_json_free(&j);
			return out;
		}
		default:	/* VALUE_NOT_SET */
			return "";
	}
}

static char 	*attrs_to_json(
		const google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> &attrs)
{
	struct zbx_json	j;
	char		*out;

	zbx_json_init(&j, 1024);

	for (const auto &kv : attrs)
	{
		zbx_json_addstring(&j, kv.key().c_str(), any_value_to_string(kv.value()).c_str(), ZBX_JSON_TYPE_STRING);
	}

	out = zbx_strdup(NULL, j.buffer);
	zbx_json_free(&j);

	return out;
}

template <typename Repeated>
static char	*num_array_to_json(const Repeated &arr)
{
	struct zbx_json	j;
	char		*out;

	zbx_json_initarray(&j, 1024);

	for (const auto &n : arr)
	{
		zbx_json_addstring(&j, NULL, std::to_string(n).c_str(), ZBX_JSON_TYPE_NUMBER);
	}

	out = zbx_strdup(NULL, j.buffer);
	zbx_json_free(&j);

	return out;
}

static std::string	unixnano_to_secs(uint64_t nano)
{
	return std::to_string(nano / 1000000000ULL);	/* DateTime column = seconds */
}


static inline void	SET(zbx_otel_row_t &row, int idx, const std::string &s)
{
	row.cols[idx] = zbx_strdup(NULL, s.c_str());
}

static inline void	SETC(zbx_otel_row_t &row, int idx, char *str)
{
	row.cols[idx] = str;
}

/* ---- shared column fills ---- */

static std::string	service_name_of(
		const opentelemetry::proto::resource::v1::Resource &res)
{
	for (const auto &kv : res.attributes())
	{
		if ("service.name" == kv.key())
			return any_value_to_string(kv.value());
	}
	return "";
}

/* fills cols[0..13], common to all five metric types; returns next index (14) */
static int	metrics_fill_common(zbx_otel_row_t &row,
		const opentelemetry::proto::metrics::v1::ResourceMetrics &rm,
		const opentelemetry::proto::metrics::v1::ScopeMetrics &sm,
		const opentelemetry::proto::metrics::v1::Metric &metric,
		const google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> &dp_attrs,
		uint64_t start_nano, uint64_t time_nano)
{
	SET(row, 0, attrs_to_json(rm.resource().attributes()));
	SET(row, 1, rm.schema_url());
	SET(row, 2, sm.scope().name());
	SET(row, 3, sm.scope().version());
	SET(row, 4, attrs_to_json(sm.scope().attributes()));
	SET(row, 5, std::to_string(sm.scope().dropped_attributes_count()));
	SET(row, 6, sm.schema_url());
	SET(row, 7, service_name_of(rm.resource()));
	SET(row, 8, metric.name());
	SET(row, 9, metric.description());
	SET(row, 10, metric.unit());
	SET(row, 11, attrs_to_json(dp_attrs));
	SET(row, 12, unixnano_to_secs(start_nano));
	SET(row, 13, unixnano_to_secs(time_nano));

	return 14;
}

static int	metrics_fill_exemplars(zbx_otel_row_t &row, int idx,
		const google::protobuf::RepeatedPtrField<opentelemetry::proto::metrics::v1::Exemplar> &exs)
{
	using Ex = opentelemetry::proto::metrics::v1::Exemplar;

	std::string	f_attrs = "[", f_time = "[", f_val = "[", f_span = "[", f_trace = "[";
	bool		first = true;

	for (const auto &ex : exs)
	{
		if (!first)
		{
			f_attrs += ","; f_time += ","; f_val += ",";
			f_span += ","; f_trace += ",";
		}
		first = false;

		f_attrs += attrs_to_json(ex.filtered_attributes());
		f_time  += unixnano_to_secs(ex.time_unix_nano());
		f_val   += std::to_string(ex.value_case() == Ex::kAsDouble ?
				ex.as_double() : (double)ex.as_int());
		f_span  += "\"" + bytes_to_hex(ex.span_id()) + "\"";
		f_trace += "\"" + bytes_to_hex(ex.trace_id()) + "\"";
	}

	f_attrs += "]"; f_time += "]"; f_val += "]"; f_span += "]"; f_trace += "]";

	SET(row, idx + 0, f_attrs);
	SET(row, idx + 1, f_time);
	SET(row, idx + 2, f_val);
	SET(row, idx + 3, f_span);
	SET(row, idx + 4, f_trace);

	return idx + 5;
}

static double	np_value(const opentelemetry::proto::metrics::v1::NumberDataPoint &dp)
{
	using NDP = opentelemetry::proto::metrics::v1::NumberDataPoint;
	return dp.value_case() == NDP::kAsDouble ? dp.as_double() : (double)dp.as_int();
}

static void	otel_decode_gauge(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.gauge().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->gauge);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(np_value(dp)));
		SET(row, i++, std::to_string(dp.flags()));
		metrics_fill_exemplars(row, i, dp.exemplars());
	}
}

static void	otel_decode_sum(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.sum().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->sum);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(np_value(dp)));	/* Value */
		SET(row, i++, std::to_string(dp.flags()));	/* Flags */
		i = metrics_fill_exemplars(row, i, dp.exemplars());
		SET(row, i++, std::to_string(metric.sum().aggregation_temporality()));
		SET(row, i, metric.sum().is_monotonic() ? "1" : "0");
	}
}

static void	otel_decode_histogram(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.histogram().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->histogram);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(dp.count()));		/* Count */
		SET(row, i++, std::to_string(dp.sum()));		/* Sum */
		SETC(row, i++, num_array_to_json(dp.bucket_counts()));
		SETC(row, i++, num_array_to_json(dp.explicit_bounds()));
		i = metrics_fill_exemplars(row, i, dp.exemplars());
		SET(row, i++, std::to_string(dp.flags()));		/* Flags */
		SET(row, i++, std::to_string(dp.min()));		/* Min */
		SET(row, i++, std::to_string(dp.max()));		/* Max */
		SET(row, i, std::to_string(metric.histogram().aggregation_temporality()));
	}
}

static void	otel_decode_exponential_histogram(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.exponential_histogram().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->exponential_histogram);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(dp.count()));		/* Count */
		SET(row, i++, std::to_string(dp.sum()));		/* Sum */
		SET(row, i++, std::to_string(dp.scale()));		/* Scale */
		SET(row, i++, std::to_string(dp.zero_count()));		/* ZeroCount */
		SET(row, i++, std::to_string(dp.positive().offset()));
		SETC(row, i++, num_array_to_json(dp.positive().bucket_counts()));
		SET(row, i++, std::to_string(dp.negative().offset()));
		SETC(row, i++, num_array_to_json(dp.negative().bucket_counts()));
		i = metrics_fill_exemplars(row, i, dp.exemplars());
		SET(row, i++, std::to_string(dp.flags()));		/* Flags */
		SET(row, i++, std::to_string(dp.min()));		/* Min */
		SET(row, i++, std::to_string(dp.max()));		/* Max */
		SET(row, i, std::to_string(metric.exponential_histogram().aggregation_temporality()));
	}
}

static void	otel_decode_summary(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.summary().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->summary);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(dp.count()));	/* Count */
		SET(row, i++, std::to_string(dp.sum()));	/* Sum */

		/* ValueAtQuantiles: two parallel arrays */
		struct zbx_json	jq, jv;

		zbx_json_initarray(&jq, 1024);
		zbx_json_initarray(&jv, 1024);

		for (const auto &qv : dp.quantile_values())
		{
			zbx_json_addstring(&jq, NULL, std::to_string(qv.quantile()).c_str(), ZBX_JSON_TYPE_NUMBER);
			zbx_json_addstring(&jv, NULL, std::to_string(qv.value()).c_str(), ZBX_JSON_TYPE_NUMBER);
		}

		SETC(row, i++, zbx_strdup(NULL, jq.buffer));
		SETC(row, i++, zbx_strdup(NULL, jv.buffer));

		zbx_json_free(&jq);
		zbx_json_free(&jv);

		SET(row, i, std::to_string(dp.flags()));	/* Flags */
	}
}

static void	otel_request_decode_metrics(zbx_otel_request_t request, zbx_otel_dataset_t *ds)
{
	const auto	*req = reinterpret_cast<const otlpmc::ExportMetricsServiceRequest *>(request);

	if (NULL == req)
		return;

	for (const auto &rm : req->resource_metrics())
	{
		const auto	&res = rm.resource();
		const auto	res_schema_url = rm.schema_url();
		const auto	service_name = service_name_of(res);

		for (const auto &sm : rm.scope_metrics())
		{
			const auto	&scope = sm.scope();
			const auto	scope_schema_url = sm.schema_url();

			for (const auto &metric : sm.metrics())
			{
				switch (metric.data_case())
				{
					case otlpm::Metric::kGauge:
						otel_decode_gauge(rm, sm, metric, ds);
						break;
					case otlpm::Metric::kSum:
						otel_decode_sum(rm, sm, metric, ds);
						break;
					case otlpm::Metric::kHistogram:
						otel_decode_histogram(rm, sm, metric, ds);
						break;
					case otlpm::Metric::kExponentialHistogram:
						otel_decode_exponential_histogram(rm, sm, metric, ds);
						break;
					case otlpm::Metric::kSummary:
						otel_decode_summary(rm, sm, metric, ds);
						break;
					default:	/* DATA_NOT_SET */
						break;
				}
			}
		}
	}
}

static void	otel_request_decode_logs(zbx_otel_request_t request, zbx_otel_dataset_t *ds)
{
	const auto	*req = reinterpret_cast<const otlplc::ExportLogsServiceRequest *>(request);

	if (NULL == req)
		return;

	for (const auto &rl : req->resource_logs())
	{
		const auto	&res = rl.resource();
		const auto	res_schema_url = rl.schema_url();
		const auto	service_name = service_name_of(res);

		for (const auto &sl : rl.scope_logs())
		{
			const auto	&scope = sl.scope();
			const auto	scope_schema_url = sl.schema_url();

			for (const auto &lr : sl.log_records())
			{
				zbx_otel_row_t	row = otel_rowset_add(&ds->logs);
				int		i = 0;

				SET(row, i++, std::to_string(lr.time_unix_nano()));	/* Timestamp, DateTime64(9) */
				SET(row, i++, bytes_to_hex(lr.trace_id()));		/* TraceId */
				SET(row, i++, bytes_to_hex(lr.span_id()));		/* SpanId */
				SET(row, i++, std::to_string(lr.flags()));		/* TraceFlags */
				SET(row, i++, lr.severity_text());			/* SeverityText */
				SET(row, i++, std::to_string(lr.severity_number()));	/* SeverityNumber */
				SET(row, i++, service_name);				/* ServiceName */
				SET(row, i++, any_value_to_string(lr.body()));		/* Body (AnyValue) */
				SET(row, i++, res_schema_url);				/* ResourceSchemaUrl */
				SETC(row, i++, attrs_to_json(res.attributes()));	/* ResourceAttributes */
				SET(row, i++, scope_schema_url);			/* ScopeSchemaUrl */
				SET(row, i++, scope.name());				/* ScopeName */
				SET(row, i++, scope.version());				/* ScopeVersion */
				SETC(row, i++, attrs_to_json(scope.attributes()));	/* ScopeAttributes */
				SETC(row, i++, attrs_to_json(lr.attributes()));		/* LogAttributes */
				SET(row, i, lr.event_name());				/* EventName */
			}
		}
	}
}

/* fills the 3 Events parallel-array cells at [idx..idx+2]; returns idx+3 */
static int	otel_traces_fill_events(zbx_otel_row_t &row, int idx,
		const google::protobuf::RepeatedPtrField<otlpt::Span_Event> &events)
{
	struct zbx_json	jts, jname, jattr;

	zbx_json_initarray(&jts, 1024);
	zbx_json_initarray(&jname, 1024);
	zbx_json_initarray(&jattr, 1024);

	for (const auto &e : events)
	{
		zbx_json_addstring(&jts, NULL, std::to_string(e.time_unix_nano()).c_str(), ZBX_JSON_TYPE_NUMBER);
		zbx_json_addstring(&jname, NULL, e.name().c_str(), ZBX_JSON_TYPE_STRING);

		/* each element is itself a JSON object of that event's attributes */
		zbx_json_addobject(&jattr, NULL);
		for (const auto &kv : e.attributes())
		{
			zbx_json_addstring(&jattr, kv.key().c_str(), any_value_to_string(kv.value()).c_str(),
					 ZBX_JSON_TYPE_STRING);
		}
		zbx_json_close(&jattr);
	}

	SETC(row, idx + 0, zbx_strdup(NULL, jts.buffer));
	SETC(row, idx + 1, zbx_strdup(NULL, jname.buffer));
	SETC(row, idx + 2, zbx_strdup(NULL, jattr.buffer));

	zbx_json_free(&jts);
	zbx_json_free(&jname);
	zbx_json_free(&jattr);

	return 3;
}

/* fills the 4 Links parallel-array cells at [idx..idx+3]; returns idx+4 */
static int	otel_traces_fill_links(zbx_otel_row_t &row, int idx,
		const google::protobuf::RepeatedPtrField<otlpt::Span_Link> &links)
{
	struct zbx_json	jtid, jsid, jstate, jattr;

	zbx_json_initarray(&jtid, 1024);
	zbx_json_initarray(&jsid, 1024);
	zbx_json_initarray(&jstate, 1024);
	zbx_json_initarray(&jattr, 1024);

	for (const auto &l : links)
	{
		zbx_json_addstring(&jtid, NULL, bytes_to_hex(l.trace_id()).c_str(), ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jsid, NULL, bytes_to_hex(l.span_id()).c_str(), ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jstate, NULL, l.trace_state().c_str(), ZBX_JSON_TYPE_STRING);

		zbx_json_addobject(&jattr, NULL);
		for (const auto &kv : l.attributes())
		{
			zbx_json_addstring(&jattr, kv.key().c_str(), any_value_to_string(kv.value()).c_str(),
					 ZBX_JSON_TYPE_STRING);
		}
		zbx_json_close(&jattr);
	}

	SETC(row, idx + 0, zbx_strdup(NULL, jtid.buffer));
	SETC(row, idx + 1, zbx_strdup(NULL, jsid.buffer));
	SETC(row, idx + 2, zbx_strdup(NULL, jstate.buffer));
	SETC(row, idx + 3, zbx_strdup(NULL, jattr.buffer));

	zbx_json_free(&jtid);
	zbx_json_free(&jsid);
	zbx_json_free(&jstate);
	zbx_json_free(&jattr);

	return 4;
}

static const char	*span_kind_str(otlpt::Span_SpanKind kind)
{
	switch (kind)
	{
		case otlpt::Span::SPAN_KIND_INTERNAL: return "Internal";
		case otlpt::Span::SPAN_KIND_SERVER:   return "Server";
		case otlpt::Span::SPAN_KIND_CLIENT:   return "Client";
		case otlpt::Span::SPAN_KIND_PRODUCER: return "Producer";
		case otlpt::Span::SPAN_KIND_CONSUMER: return "Consumer";
		default:                              return "Unspecified";
	}
}

static const char	*status_code_str(otlpt::Status_StatusCode code)
{
	switch (code)
	{
		case otlpt::Status::STATUS_CODE_OK:    return "Ok";
		case otlpt::Status::STATUS_CODE_ERROR: return "Error";
		default:                               return "Unset";
	}
}

static void	otel_request_decode_traces(zbx_otel_request_t request, zbx_otel_dataset_t *ds)
{
	namespace otlptc = opentelemetry::proto::collector::trace::v1;

	const auto	*req = reinterpret_cast<const otlptc::ExportTraceServiceRequest *>(request);

	if (NULL == req)
		return;

	for (const auto &rs : req->resource_spans())
	{
		const auto	&res = rs.resource();
		const auto	service_name = service_name_of(res);

		for (const auto &ss : rs.scope_spans())
		{
			const auto	&scope = ss.scope();

			for (const auto &span : ss.spans())
			{
				zbx_otel_row_t	row = otel_rowset_add(&ds->traces);
				int		i = 0;

				SET(row, i++, std::to_string(span.start_time_unix_nano()));	/* Timestamp */
				SET(row, i++, bytes_to_hex(span.trace_id()));			/* TraceId */
				SET(row, i++, bytes_to_hex(span.span_id()));			/* SpanId */
				SET(row, i++, bytes_to_hex(span.parent_span_id()));		/* ParentSpanId */
				SET(row, i++, span.trace_state());				/* TraceState */
				SET(row, i++, span.name());					/* SpanName */
				SET(row, i++, span_kind_str(span.kind()));			/* SpanKind */
				SET(row, i++, service_name);					/* ServiceName */
				SETC(row, i++, attrs_to_json(res.attributes()));		/* ResourceAttributes */
				SET(row, i++, scope.name());					/* ScopeName */
				SET(row, i++, scope.version());					/* ScopeVersion */
				SETC(row, i++, attrs_to_json(span.attributes()));		/* SpanAttributes */
				SET(row, i++, std::to_string(					/* Duration, nanos */
						span.end_time_unix_nano() - span.start_time_unix_nano()));
				SET(row, i++, status_code_str(span.status().code()));		/* StatusCode */
				SET(row, i++, span.status().message());				/* StatusMessage */

				i += otel_traces_fill_events(row, i, span.events());		/* 15,16,17 */
				(void)otel_traces_fill_links(row, i, span.links());		/* 18,19,20,21 */
			}
		}
	}
}

void	zbx_otel_request_decode(zbx_otel_request_t request, zbx_otel_request_type_t type, zbx_otel_dataset_t *ds)
{
	switch (type)
	{
		case OTEL_METRICS:
			otel_request_decode_metrics(request, ds);
			break;
		case OTEL_LOGS:
			otel_request_decode_logs(request, ds);
			break;
		case OTEL_TRACES:
			otel_request_decode_traces(request, ds);
			break;
	}
}

