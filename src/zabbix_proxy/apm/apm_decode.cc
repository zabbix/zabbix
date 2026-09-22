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

#include "apm_decode.h"
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

/******************************************************************************
 *                                                                            *
 * Purpose: convert a raw byte string into its lowercase hexadecimal          *
 *          representation                                                    *
 *                                                                            *
 * Parameters: b - [IN] bytes to convert                                      *
 *                                                                            *
 * Return value: hexadecimal string representation of b                       *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: recursively add an OTLP AnyValue to a JSON object or array        *
 *          being built                                                       *
 *                                                                            *
 * Parameters: j   - [IN/OUT] JSON being built                                *
 *             key - [IN] key to add the value under, or NULL to append       *
 *                         the value to an array                              *
 *             v   - [IN] value to add                                        *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: convert an OTLP AnyValue to its string representation             *
 *                                                                            *
 * Parameters: v - [IN] value to convert                                      *
 *                                                                            *
 * Return value: string representation of v; scalar values are converted      *
 *               directly, kvlist/array values are serialized as JSON         *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: check whether an attribute with the specified key is present      *
 *          in the attribute list                                             *
 *                                                                            *
 * Parameters: attrs - [IN] attributes to search                              *
 *             key   - [IN] attribute key to look for                         *
 *                                                                            *
 * Return value: SUCCEED if found, FAIL otherwise                             *
 *                                                                            *
 ******************************************************************************/
static int	attributes_contain(
		const google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> &attrs,
		const char *key)
{
	for (const auto &kv : attrs)
	{
		if (kv.key() == key)
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize OTLP attributes into a JSON object, optionally          *
 *          adding resource attributes not already present                    *
 *                                                                            *
 * Parameters: attrs          - [IN] attributes to serialize                  *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                   if not already present in attrs, or      *
 *                                   NULL                                     *
 *                                                                            *
 * Return value: newly allocated JSON string, must be freed by the caller     *
 *                                                                            *
 ******************************************************************************/
static char 	*attrs_to_json_ex(
		const google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> &attrs,
		const zbx_vector_tag_t *resource_attrs)
{
	struct zbx_json		j;
	char			*out;
	struct zbx_json_parse	jp;

	zbx_json_init(&j, 1024);

	for (const auto &kv : attrs)
	{
		zbx_json_addstring(&j, kv.key().c_str(), any_value_to_string(kv.value()).c_str(), ZBX_JSON_TYPE_STRING);
	}

	if (NULL != resource_attrs)
	{
		for (int i = 0; i < resource_attrs->values_num; i++)
		{
			const zbx_tag_t	*tag = &resource_attrs->values[i];

			if (SUCCEED != attributes_contain(attrs, tag->tag))
				zbx_json_addstring(&j, tag->tag, tag->value, ZBX_JSON_TYPE_STRING);
		}
	}

	out = zbx_strdup(NULL, j.buffer);
	zbx_json_free(&j);

	return out;
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize OTLP attributes into a JSON object                      *
 *                                                                            *
 * Parameters: attrs - [IN] attributes to serialize                           *
 *                                                                            *
 * Return value: newly allocated JSON string, must be freed by the caller     *
 *                                                                            *
 ******************************************************************************/
static char 	*attrs_to_json(
	const google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> &attrs)
{
	return attrs_to_json_ex(attrs, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize a numeric array into a JSON array                       *
 *                                                                            *
 * Parameters: arr - [IN] numeric values to serialize                         *
 *                                                                            *
 * Return value: newly allocated JSON string, must be freed by the caller     *
 *                                                                            *
 ******************************************************************************/
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
/******************************************************************************
 *                                                                            *
 * Purpose: convert a unix nanosecond timestamp to whole seconds              *
 *                                                                            *
 * Parameters: nano - [IN] timestamp in unix nanoseconds                      *
 *                                                                            *
 * Return value: timestamp in unix seconds                                    *
 *                                                                            *
 ******************************************************************************/
static inline zbx_uint64_t	unixnano_to_secs(uint64_t nano)
{
	return (zbx_uint64_t)(nano / 1000000000ULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert a unix nanosecond timestamp to a "seconds.nanoseconds"    *
 *          decimal string accepted by ClickHouse DateTime64 columns          *
 *          regardless of their configured scale                             *
 *                                                                            *
 * Parameters: nano - [IN] timestamp in unix nanoseconds                      *
 *                                                                            *
 * Return value: decimal string in the form "<seconds>.<nanoseconds>"         *
 *                                                                            *
 ******************************************************************************/
static std::string	unixnano_to_decimal_str(uint64_t nano)
{
	std::string	frac = std::to_string(nano % 1000000000ULL);

	frac.insert(0, 9 - frac.length(), '0');

	return std::to_string(nano / 1000000000ULL) + "." + frac;
}

/******************************************************************************
 *                                                                            *
 * Purpose: column value setter helpers used when filling dataset rows        *
 *                                                                            *
 * Comments: row - row being filled, idx - column index to set. SETS          *
 *           copies the string, SETC takes ownership of an already            *
 *           allocated string, SETU/SETD/SETI store a uint64/double/int32     *
 *           value respectively.                                              *
 *                                                                            *
 ******************************************************************************/
static inline void	SETS(zbx_apm_row_t &row, int idx, const std::string &s)
{
	row.cols[idx].str = zbx_strdup(NULL, s.c_str());
}

static inline void	SETC(zbx_apm_row_t &row, int idx, char *str)
{
	row.cols[idx].str = str;
}

static inline void	SETU(zbx_apm_row_t &row, int idx, zbx_uint64_t ui64)
{
	row.cols[idx].ui64 = ui64;
}

static inline void	SETD(zbx_apm_row_t &row, int idx, double dbl)
{
	row.cols[idx].dbl = dbl;
}

static inline void	SETI(zbx_apm_row_t &row, int idx, int i32)
{
	row.cols[idx].i32 = i32;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find the "service.name" resource attribute value                  *
 *                                                                            *
 * Parameters: res - [IN] resource to search                                  *
 *                                                                            *
 * Return value: service name, or an empty string if not present              *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: fill row columns common to all metric types                       *
 *                                                                            *
 * Parameters: row         - [OUT] row to populate                            *
 *             rm          - [IN] resource metrics containing the metric      *
 *             sm          - [IN] scope metrics containing the metric         *
 *             metric      - [IN] metric being converted                      *
 *             dp_attrs    - [IN] data point attributes                       *
 *             start_nano  - [IN] start time in unix nanoseconds              *
 *             time_nano   - [IN] collection time in unix nanoseconds         *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 * Return value: index of the first column after the common columns           *
 *                                                                            *
 ******************************************************************************/
static int	metrics_fill_common(zbx_apm_row_t &row,
		const opentelemetry::proto::metrics::v1::ResourceMetrics &rm,
		const opentelemetry::proto::metrics::v1::ScopeMetrics &sm,
		const opentelemetry::proto::metrics::v1::Metric &metric,
		const google::protobuf::RepeatedPtrField<opentelemetry::proto::common::v1::KeyValue> &dp_attrs,
		uint64_t start_nano, uint64_t time_nano, const zbx_vector_tag_t *resource_attrs)
{
	SETC(row, 0, attrs_to_json_ex(rm.resource().attributes(), resource_attrs));	/* ResourceAttributes */
	SETS(row, 1, rm.schema_url());					/* ResourceSchemaUrl */
	SETS(row, 2, sm.scope().name());				/* ScopeName */
	SETS(row, 3, sm.scope().version());				/* ScopeVersion */
	SETC(row, 4, attrs_to_json(sm.scope().attributes()));		/* ScopeAttributes */
	SETU(row, 5, sm.scope().dropped_attributes_count());		/* ScopeDroppedAttrCount */
	SETS(row, 6, sm.schema_url());					/* ScopeSchemaUrl */
	SETS(row, 7, service_name_of(rm.resource()));			/* ServiceName */
	SETS(row, 8, metric.name());					/* MetricName */
	SETS(row, 9, metric.description());				/* MetricDescription */
	SETS(row, 10, metric.unit());					/* MetricUnit */
	SETC(row, 11, attrs_to_json(dp_attrs));				/* Attributes */
	SETU(row, 12, unixnano_to_secs(start_nano));			/* StartTimeUnix */
	SETU(row, 13, unixnano_to_secs(time_nano));			/* TimeUnix */

	return 14;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fill the 5 Exemplars parallel-array columns starting at           *
 *          index i                                                           *
 *                                                                            *
 * Parameters: row - [OUT] row to populate                                    *
 *             i   - [IN] starting column index                               *
 *             exs - [IN] exemplars to serialize                              *
 *                                                                            *
 * Return value: number of columns filled (always 5)                          *
 *                                                                            *
 ******************************************************************************/
static int	metrics_fill_exemplars(zbx_apm_row_t &row, int i,
		const google::protobuf::RepeatedPtrField<opentelemetry::proto::metrics::v1::Exemplar> &exs)
{
	using Ex = opentelemetry::proto::metrics::v1::Exemplar;

	struct zbx_json	jattr, jtime, jval, jspan, jtrace;

	zbx_json_initarray(&jattr, 1024);
	zbx_json_initarray(&jtime, 1024);
	zbx_json_initarray(&jval, 1024);
	zbx_json_initarray(&jspan, 1024);
	zbx_json_initarray(&jtrace, 1024);

	for (const auto &ex : exs)
	{
		/* Map(String, String): values stringified, like every other attribute map */
		zbx_json_addobject(&jattr, NULL);
		for (const auto &kv : ex.filtered_attributes())
		{
			zbx_json_addstring(&jattr, kv.key().c_str(),
					any_value_to_string(kv.value()).c_str(), ZBX_JSON_TYPE_STRING);
		}
		zbx_json_close(&jattr);

		zbx_json_adduint64(&jtime, NULL, unixnano_to_secs(ex.time_unix_nano()));
		zbx_json_adddouble(&jval, NULL, Ex::kAsDouble == ex.value_case() ?
				ex.as_double() : (double)ex.as_int());
		zbx_json_addstring(&jspan, NULL, bytes_to_hex(ex.span_id()).c_str(), ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jtrace, NULL, bytes_to_hex(ex.trace_id()).c_str(), ZBX_JSON_TYPE_STRING);
	}

	SETC(row, i++, zbx_strdup(NULL, jattr.buffer));	/* Exemplars.FilteredAttributes */
	SETC(row, i++, zbx_strdup(NULL, jtime.buffer));	/* Exemplars.TimeUnix */
	SETC(row, i++,  zbx_strdup(NULL, jval.buffer));	/* Exemplars.Value */
	SETC(row, i++, zbx_strdup(NULL, jspan.buffer));	/* Exemplars.SpanId */
	SETC(row, i, zbx_strdup(NULL, jtrace.buffer));	/* Exemplars.TraceId */

	zbx_json_free(&jattr);
	zbx_json_free(&jtime);
	zbx_json_free(&jval);
	zbx_json_free(&jspan);
	zbx_json_free(&jtrace);

	return 5;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the numeric value of a NumberDataPoint as double              *
 *                                                                            *
 * Parameters: dp - [IN] data point                                           *
 *                                                                            *
 * Return value: value as double, converting from int64 if necessary          *
 *                                                                            *
 ******************************************************************************/
static double	np_value(const opentelemetry::proto::metrics::v1::NumberDataPoint &dp)
{
	using NDP = opentelemetry::proto::metrics::v1::NumberDataPoint;
	return dp.value_case() == NDP::kAsDouble ? dp.as_double() : (double)dp.as_int();
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode gauge metric data points into dataset rows                 *
 *                                                                            *
 * Parameters: rm             - [IN] resource metrics containing the metric   *
 *             sm             - [IN] scope metrics containing the metric      *
 *             metric         - [IN] gauge metric to decode                   *
 *             ds             - [OUT] dataset to append rows to               *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 ******************************************************************************/
static void	apm_decode_gauge(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_apm_dataset_t *ds, const zbx_vector_tag_t *resource_attrs)
{
	for (const auto &dp : metric.gauge().data_points())
	{
		zbx_apm_row_t	row = apm_rowset_add(&ds->metrics_gauge);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano(), resource_attrs);

		SETD(row, i++, np_value(dp));
		SETU(row, i++, dp.flags());
		(void)metrics_fill_exemplars(row, i, dp.exemplars());
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode sum metric data points into dataset rows                   *
 *                                                                            *
 * Parameters: rm             - [IN] resource metrics containing the metric   *
 *             sm             - [IN] scope metrics containing the metric      *
 *             metric         - [IN] sum metric to decode                     *
 *             ds             - [OUT] dataset to append rows to               *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 ******************************************************************************/
static void	apm_decode_sum(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_apm_dataset_t *ds, const zbx_vector_tag_t *resource_attrs)
{
	for (const auto &dp : metric.sum().data_points())
	{
		zbx_apm_row_t	row = apm_rowset_add(&ds->metrics_sum);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano(), resource_attrs);

		SETD(row, i++, np_value(dp));					/* Value */
		SETU(row, i++, dp.flags());					/* Flags */
		i += metrics_fill_exemplars(row, i, dp.exemplars());
		SETI(row, i++, metric.sum().aggregation_temporality());		/* AggregationTemporality */
		SETU(row, i++, metric.sum().is_monotonic() ? 1 : 0);		/* IsMonotonic */
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode histogram metric data points into dataset rows             *
 *                                                                            *
 * Parameters: rm             - [IN] resource metrics containing the metric   *
 *             sm             - [IN] scope metrics containing the metric      *
 *             metric         - [IN] histogram metric to decode               *
 *             ds             - [OUT] dataset to append rows to               *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 ******************************************************************************/
static void	apm_decode_histogram(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_apm_dataset_t *ds, const zbx_vector_tag_t *resource_attrs)
{
	for (const auto &dp : metric.histogram().data_points())
	{
		zbx_apm_row_t	row = apm_rowset_add(&ds->metrics_histogram);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano(), resource_attrs);

		SETU(row, i++, dp.count());					/* Count */
		SETD(row, i++, dp.sum());					/* Sum */
		SETC(row, i++, num_array_to_json(dp.bucket_counts()));		/* BucketCounts */
		SETC(row, i++, num_array_to_json(dp.explicit_bounds()));	/* ExplicitBounds */
		i += metrics_fill_exemplars(row, i, dp.exemplars());
		SETU(row, i++, dp.flags());					/* Flags */
		SETD(row, i++, dp.min());					/* Min */
		SETD(row, i++, dp.max());					/* Max */
		SETI(row, i++, metric.histogram().aggregation_temporality());	/* AggregationTemporality */
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode exponential histogram metric data points into dataset rows *
 *                                                                            *
 * Parameters: rm             - [IN] resource metrics containing the metric   *
 *             sm             - [IN] scope metrics containing the metric      *
 *             metric         - [IN] exponential histogram metric to decode   *
 *             ds             - [OUT] dataset to append rows to               *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 ******************************************************************************/
static void	apm_decode_exponential_histogram(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_apm_dataset_t *ds, const zbx_vector_tag_t *resource_attrs)
{
	for (const auto &dp : metric.exponential_histogram().data_points())
	{
		zbx_apm_row_t	row = apm_rowset_add(&ds->metrics_exponential_histogram);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano(), resource_attrs);

		SETU(row, i++, dp.count());						/* Count */
		SETD(row, i++, dp.sum());						/* Sum */
		SETI(row, i++, dp.scale());						/* Scale */
		SETU(row, i++, dp.zero_count());					/* ZeroCount */
		SETI(row, i++, dp.positive().offset());					/* PositiveOffset */
		SETC(row, i++, num_array_to_json(dp.positive().bucket_counts()));	/* PositiveBucketCounts */
		SETI(row, i++, dp.negative().offset());					/* NegativeOffset */
		SETC(row, i++, num_array_to_json(dp.negative().bucket_counts()));	/* NegativeBucketCounts */
		i += metrics_fill_exemplars(row, i, dp.exemplars());
		SETU(row, i++, dp.flags());						/* Flags */
		SETD(row, i++, dp.min());						/* Min */
		SETD(row, i++, dp.max());						/* Max */
		SETI(row, i++, metric.exponential_histogram().aggregation_temporality()); /* AggregationTemporality */
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode summary metric data points into dataset rows               *
 *                                                                            *
 * Parameters: rm             - [IN] resource metrics containing the metric   *
 *             sm             - [IN] scope metrics containing the metric      *
 *             metric         - [IN] summary metric to decode                 *
 *             ds             - [OUT] dataset to append rows to               *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 ******************************************************************************/
static void	apm_decode_summary(const otlpm::ResourceMetrics &rm, const otlpm::ScopeMetrics &sm,
		const otlpm::Metric &metric, zbx_apm_dataset_t *ds, const zbx_vector_tag_t *resource_attrs)
{
	for (const auto &dp : metric.summary().data_points())
	{
		zbx_apm_row_t	row = apm_rowset_add(&ds->metrics_summary);
		int		i = metrics_fill_common(row, rm, sm, metric, dp.attributes(),
				dp.start_time_unix_nano(), dp.time_unix_nano(), resource_attrs);

		SETU(row, i++, dp.count());	/* Count */
		SETD(row, i++, dp.sum());	/* Sum */

		/* ValueAtQuantiles: two parallel arrays */
		struct zbx_json	jq, jv;

		zbx_json_initarray(&jq, 1024);
		zbx_json_initarray(&jv, 1024);

		for (const auto &qv : dp.quantile_values())
		{
			zbx_json_adddouble(&jq, NULL, qv.quantile());
			zbx_json_adddouble(&jv, NULL, qv.value());
		}

		SETC(row, i++, zbx_strdup(NULL, jq.buffer));	/* ValueAtQuantiles.Quantile */
		SETC(row, i++, zbx_strdup(NULL, jv.buffer));	/* ValueAtQuantiles.Value */

		zbx_json_free(&jq);
		zbx_json_free(&jv);

		SETU(row, i++, dp.flags());	/* Flags */
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode an OTLP metrics export request into dataset rows           *
 *                                                                            *
 * Parameters: request        - [IN] ExportMetricsServiceRequest to decode    *
 *             ds             - [OUT] dataset to append rows to               *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 ******************************************************************************/
static void	apm_request_decode_metrics(zbx_apm_request_t request, zbx_apm_dataset_t *ds,
		const zbx_vector_tag_t *resource_attrs)
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
						apm_decode_gauge(rm, sm, metric, ds, resource_attrs);
						break;
					case otlpm::Metric::kSum:
						apm_decode_sum(rm, sm, metric, ds, resource_attrs);
						break;
					case otlpm::Metric::kHistogram:
						apm_decode_histogram(rm, sm, metric, ds, resource_attrs);
						break;
					case otlpm::Metric::kExponentialHistogram:
						apm_decode_exponential_histogram(rm, sm, metric, ds, resource_attrs);
						break;
					case otlpm::Metric::kSummary:
						apm_decode_summary(rm, sm, metric, ds, resource_attrs);
						break;
					default:
						break;
				}
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode an OTLP logs export request into dataset rows              *
 *                                                                            *
 * Parameters: request        - [IN] ExportLogsServiceRequest to decode       *
 *             ds             - [OUT] dataset to append rows to               *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 ******************************************************************************/
static void	apm_request_decode_logs(zbx_apm_request_t request, zbx_apm_dataset_t *ds,
		const zbx_vector_tag_t *resource_attrs)
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
				zbx_apm_row_t	row = apm_rowset_add(&ds->logs);
				int		i = 0;

				SETU(row, i++, lr.time_unix_nano());			/* Timestamp, DateTime64(9) */
				SETS(row, i++, bytes_to_hex(lr.trace_id()));		/* TraceId */
				SETS(row, i++, bytes_to_hex(lr.span_id()));		/* SpanId */
				SETU(row, i++, lr.flags());				/* TraceFlags */
				SETS(row, i++, lr.severity_text());			/* SeverityText */
				SETU(row, i++, lr.severity_number());			/* SeverityNumber */
				SETS(row, i++, service_name);				/* ServiceName */
				SETS(row, i++, any_value_to_string(lr.body()));		/* Body (AnyValue) */
				SETS(row, i++, res_schema_url);				/* ResourceSchemaUrl */

				/* ResourceAttributes */
				SETC(row, i++, attrs_to_json_ex(res.attributes(), resource_attrs));

				SETS(row, i++, scope_schema_url);			/* ScopeSchemaUrl */
				SETS(row, i++, scope.name());				/* ScopeName */
				SETS(row, i++, scope.version());			/* ScopeVersion */
				SETC(row, i++, attrs_to_json(scope.attributes()));	/* ScopeAttributes */
				SETC(row, i++, attrs_to_json(lr.attributes()));		/* LogAttributes */
				SETS(row, i++, lr.event_name());			/* EventName */
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: fill the 3 Events parallel-array columns starting at idx          *
 *                                                                            *
 * Parameters: row    - [OUT] row to populate                                 *
 *             idx    - [IN] starting column index                            *
 *             events - [IN] span events to serialize                         *
 *                                                                            *
 * Return value: number of columns filled (always 3)                          *
 *                                                                            *
 ******************************************************************************/
static int	apm_traces_fill_events(zbx_apm_row_t &row, int idx,
		const google::protobuf::RepeatedPtrField<otlpt::Span_Event> &events)
{
	struct zbx_json	jts, jname, jattr;

	zbx_json_initarray(&jts, 1024);
	zbx_json_initarray(&jname, 1024);
	zbx_json_initarray(&jattr, 1024);

	for (const auto &e : events)
	{
		zbx_json_addstring(&jts, NULL, unixnano_to_decimal_str(e.time_unix_nano()).c_str(),
				ZBX_JSON_TYPE_NUMBER);
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

/******************************************************************************
 *                                                                            *
 * Purpose: fill the 4 Links parallel-array columns starting at idx           *
 *                                                                            *
 * Parameters: row   - [OUT] row to populate                                  *
 *             idx   - [IN] starting column index                             *
 *             links - [IN] span links to serialize                           *
 *                                                                            *
 * Return value: number of columns filled (always 4)                          *
 *                                                                            *
 ******************************************************************************/
static int	apm_traces_fill_links(zbx_apm_row_t &row, int idx,
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

/******************************************************************************
 *                                                                            *
 * Purpose: convert an OTLP span kind enum to its string representation       *
 *                                                                            *
 * Parameters: kind - [IN] span kind                                          *
 *                                                                            *
 * Return value: span kind name                                               *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: convert an OTLP status code enum to its string representation     *
 *                                                                            *
 * Parameters: code - [IN] status code                                        *
 *                                                                            *
 * Return value: status code name                                             *
 *                                                                            *
 ******************************************************************************/
static const char	*status_code_str(otlpt::Status_StatusCode code)
{
	switch (code)
	{
		case otlpt::Status::STATUS_CODE_OK:    return "Ok";
		case otlpt::Status::STATUS_CODE_ERROR: return "Error";
		default:                               return "Unset";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode an OTLP trace export request into dataset rows             *
 *                                                                            *
 * Parameters: request        - [IN] ExportTraceServiceRequest to decode      *
 *             ds             - [OUT] dataset to append rows to               *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                                                            *
 ******************************************************************************/
static void	apm_request_decode_traces(zbx_apm_request_t request, zbx_apm_dataset_t *ds,
		const zbx_vector_tag_t *resource_attrs)
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
				zbx_apm_row_t	row = apm_rowset_add(&ds->traces);
				int		i = 0;

				SETU(row, i++, span.start_time_unix_nano());		/* Timestamp, DateTime64(9) */
				SETS(row, i++, bytes_to_hex(span.trace_id()));		/* TraceId */
				SETS(row, i++, bytes_to_hex(span.span_id()));		/* SpanId */
				SETS(row, i++, bytes_to_hex(span.parent_span_id()));	/* ParentSpanId */
				SETS(row, i++, span.trace_state());			/* TraceState */
				SETS(row, i++, span.name());				/* SpanName */
				SETS(row, i++, span_kind_str(span.kind()));		/* SpanKind */
				SETS(row, i++, service_name);				/* ServiceName */

				/* ResourceAttributes */
				SETC(row, i++, attrs_to_json_ex(res.attributes(), resource_attrs));

				SETS(row, i++, scope.name());				/* ScopeName */
				SETS(row, i++, scope.version());			/* ScopeVersion */
				SETC(row, i++, attrs_to_json(span.attributes()));	/* SpanAttributes */
				SETU(row, i++, span.end_time_unix_nano() > span.start_time_unix_nano() ? /* Duration */
						span.end_time_unix_nano() - span.start_time_unix_nano() : 0);
				SETS(row, i++, status_code_str(span.status().code()));	/* StatusCode */
				SETS(row, i++, span.status().message());		/* StatusMessage */

				i += apm_traces_fill_events(row, i, span.events());	/* 15,16,17 */
				(void)apm_traces_fill_links(row, i, span.links());	/* 18,19,20,21 */
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode an OTLP export request of the specified signal type        *
 *          into dataset rows                                                 *
 *                                                                            *
 * Parameters: request        - [IN] OTLP export request to decode            *
 *             type           - [IN] request signal type                      *
 *             ds             - [OUT] dataset to append decoded rows to       *
 *             resource_attrs - [IN] optional resource attributes to add      *
 *                                   to every row's *Attributes column        *
 *                                                                            *
 ******************************************************************************/
void	zbx_apm_request_decode(zbx_apm_request_t request, zbx_apm_request_type_t type, zbx_apm_dataset_t *ds,
		const zbx_vector_tag_t *resource_attrs)
{
	switch (type)
	{
		case APM_METRICS:
			apm_request_decode_metrics(request, ds, resource_attrs);
			break;
		case APM_LOGS:
			apm_request_decode_logs(request, ds, resource_attrs);
			break;
		case APM_TRACES:
			apm_request_decode_traces(request, ds, resource_attrs);
			break;
	}
}

