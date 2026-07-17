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

namespace otlpm = opentelemetry::proto::metrics::v1;			/* data types */
namespace otlpc = opentelemetry::proto::collector::metrics::v1;		/* ExportMetricsServiceRequest */

static std::string	any_value_to_string(const opentelemetry::proto::common::v1::AnyValue &v)
{
	using AV = opentelemetry::proto::common::v1::AnyValue;

	switch (v.value_case())
	{
		case AV::kStringValue: return v.string_value();
		case AV::kBoolValue:   return v.bool_value() ? "true" : "false";
		case AV::kIntValue:    return std::to_string(v.int_value());
		case AV::kDoubleValue: return std::to_string(v.double_value());
		/* bytes/array/kvlist: simplified — refine if you need pdata AsString parity */
		case AV::kBytesValue:  return v.bytes_value();
		default:               return "";
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
static int	fill_common(zbx_otel_row_t &row,
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

static int	fill_exemplars(zbx_otel_row_t &row, int idx,
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
		int		i = fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(np_value(dp)));
		SET(row, i++, std::to_string(dp.flags()));
		fill_exemplars(row, i, dp.exemplars());
	}
}

static void	otel_decode_sum(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.sum().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->sum);
		int		i = fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(np_value(dp)));	/* Value */
		SET(row, i++, std::to_string(dp.flags()));	/* Flags */
		i = fill_exemplars(row, i, dp.exemplars());
		SET(row, i++, std::to_string(metric.sum().aggregation_temporality()));
		SET(row, i++, metric.sum().is_monotonic() ? "1" : "0");
	}
}

static void	otel_decode_histogram(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.histogram().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->histogram);
		int		i = fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(dp.count()));		/* Count */
		SET(row, i++, std::to_string(dp.sum()));		/* Sum */
		SETC(row, i++, num_array_to_json(dp.bucket_counts()));
		SETC(row, i++, num_array_to_json(dp.explicit_bounds()));
		i = fill_exemplars(row, i, dp.exemplars());
		SET(row, i++, std::to_string(dp.flags()));		/* Flags */
		SET(row, i++, std::to_string(dp.min()));		/* Min */
		SET(row, i++, std::to_string(dp.max()));		/* Max */
		SET(row, i++, std::to_string(metric.histogram().aggregation_temporality()));
	}
}

static void	otel_decode_exponential_histogram(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.exponential_histogram().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->exponential_histogram);
		int		i = fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano());

		SET(row, i++, std::to_string(dp.count()));		/* Count */
		SET(row, i++, std::to_string(dp.sum()));		/* Sum */
		SET(row, i++, std::to_string(dp.scale()));		/* Scale */
		SET(row, i++, std::to_string(dp.zero_count()));		/* ZeroCount */
		SET(row, i++, std::to_string(dp.positive().offset()));
		SETC(row, i++, num_array_to_json(dp.positive().bucket_counts()));
		SET(row, i++, std::to_string(dp.negative().offset()));
		SETC(row, i++, num_array_to_json(dp.negative().bucket_counts()));
		i = fill_exemplars(row, i, dp.exemplars());
		SET(row, i++, std::to_string(dp.flags()));		/* Flags */
		SET(row, i++, std::to_string(dp.min()));		/* Min */
		SET(row, i++, std::to_string(dp.max()));		/* Max */
		SET(row, i++, std::to_string(metric.exponential_histogram().aggregation_temporality()));
	}
}

static void	otel_decode_summary(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_otel_dataset_t *ds)
{
	for (const auto &dp : metric.summary().data_points())
	{
		zbx_otel_row_t	row = otel_rowset_add(&ds->summary);
		int		i = fill_common(row, rm, sm, metric, dp.attributes(),
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

		SET(row, i++, std::to_string(dp.flags()));	/* Flags */
	}
}

static void	otel_request_decode_metrics(zbx_otel_request_t request, zbx_otel_dataset_t *ds)
{
	const auto	*req = reinterpret_cast<const otlpc::ExportMetricsServiceRequest *>(request);

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

static void	otel_request_decode_traces(zbx_otel_request_t request, zbx_otel_dataset_t *ds)
{
}

static void	otel_request_decode_logs(zbx_otel_request_t request, zbx_otel_dataset_t *ds)
{
}

void	zbx_otel_request_decode(zbx_otel_request_t request, zbx_otel_request_type_t type, zbx_otel_dataset_t *ds)
{
	switch (type)
	{
		case OTEL_METRICS:
			otel_request_decode_metrics(request, ds);
			break;
		case OTEL_TRACES:
			otel_request_decode_traces(request, ds);
			break;
		case OTEL_LOGS:
			otel_request_decode_logs(request, ds);
			break;
	}
}

