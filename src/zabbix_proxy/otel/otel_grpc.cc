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

#include "otel_grpc.h"
#include "opentelemetry/proto/collector/logs/v1/logs_service.grpc.pb.h"
#include "opentelemetry/proto/collector/metrics/v1/metrics_service.grpc.pb.h"
#include "opentelemetry/proto/collector/trace/v1/trace_service.grpc.pb.h"
#include <grpcpp/grpcpp.h>
#include <string>
#include <cstdlib>
#include <cstring>
#include <cstdio>

extern "C" {
	#include "otel_queue.h"
	#include "zbxmw.h"
	#include "zbxlog.h"
}

using grpc::CallbackServerContext;
using grpc::Server;
using grpc::ServerBuilder;
using grpc::ServerUnaryReactor;
using grpc::Status;

namespace otlp_trace = opentelemetry::proto::collector::trace::v1;
namespace otlp_metrics = opentelemetry::proto::collector::metrics::v1;
namespace otlp_logs = opentelemetry::proto::collector::logs::v1;

struct GrpcServerHandle;

class TraceServiceImpl final : public otlp_trace::TraceService::CallbackService
{
public:
	explicit TraceServiceImpl(GrpcServerHandle *handle) : handle(handle) {}

	ServerUnaryReactor *Export(CallbackServerContext *context,
			const otlp_trace::ExportTraceServiceRequest *request,
			otlp_trace::ExportTraceServiceResponse *response);

private:
	GrpcServerHandle *handle;
};

class MetricsServiceImpl final : public otlp_metrics::MetricsService::CallbackService
{
	public:
	explicit MetricsServiceImpl(GrpcServerHandle *handle) : handle(handle) {}

	ServerUnaryReactor *Export(CallbackServerContext *context,
			const otlp_metrics::ExportMetricsServiceRequest *request,
			otlp_metrics::ExportMetricsServiceResponse *response);
private:
	GrpcServerHandle *handle;
};

class LogsServiceImpl final : public otlp_logs::LogsService::CallbackService
{
public:
	explicit LogsServiceImpl(GrpcServerHandle *handle) : handle(handle) {}

	ServerUnaryReactor *Export(CallbackServerContext *context,
			const otlp_logs::ExportLogsServiceRequest *request,
			otlp_logs::ExportLogsServiceResponse *response);
private:
	GrpcServerHandle *handle;
};

struct GrpcServerHandle
{
	GrpcServerHandle(zbx_otel_queue_t *queue) : queue(queue) {}

	std::unique_ptr<Server> server;
	std::unique_ptr<TraceServiceImpl> trace_service;
	std::unique_ptr<MetricsServiceImpl> metrics_service;
	std::unique_ptr<LogsServiceImpl> logs_service;

	zbx_otel_queue_t * const queue;
};

ServerUnaryReactor *TraceServiceImpl::Export(CallbackServerContext *context,
		const otlp_trace::ExportTraceServiceRequest *request,
		otlp_trace::ExportTraceServiceResponse *response)
{
	ServerUnaryReactor *reactor = context->DefaultReactor();

	auto *heap_request = new otlp_trace::ExportTraceServiceRequest();
	heap_request->Swap(const_cast<otlp_trace::ExportTraceServiceRequest *>(request));

	if (FAIL == otel_queue_push_request(handle->queue, static_cast<zbx_otel_request_t>(heap_request), OTEL_TRACE))
	{
		reactor->Finish(grpc::Status(grpc::StatusCode::RESOURCE_EXHAUSTED, "export rate limit exceeded"));
		delete heap_request;
	}
	else
		reactor->Finish(Status::OK);

	return reactor;
}

ServerUnaryReactor *MetricsServiceImpl::Export(CallbackServerContext *context,
		const otlp_metrics::ExportMetricsServiceRequest *request,
		otlp_metrics::ExportMetricsServiceResponse *response)
{
	ServerUnaryReactor *reactor = context->DefaultReactor();

	auto *heap_request = new otlp_metrics::ExportMetricsServiceRequest();
	heap_request->Swap(const_cast<otlp_metrics::ExportMetricsServiceRequest *>(request));

	if (FAIL == otel_queue_push_request(handle->queue, static_cast<zbx_otel_request_t>(heap_request), OTEL_METRIC))
	{
		reactor->Finish(grpc::Status(grpc::StatusCode::RESOURCE_EXHAUSTED, "export rate limit exceeded"));
		delete heap_request;
	}
	else
		reactor->Finish(Status::OK);

	reactor->Finish(Status::OK);
	return reactor;
}

ServerUnaryReactor *LogsServiceImpl::Export(CallbackServerContext *context,
		const otlp_logs::ExportLogsServiceRequest *request,
		otlp_logs::ExportLogsServiceResponse *response)
{
	ServerUnaryReactor *reactor = context->DefaultReactor();

	auto *heap_request = new otlp_logs::ExportLogsServiceRequest();
	heap_request->Swap(const_cast<otlp_logs::ExportLogsServiceRequest *>(request));

	if (FAIL == otel_queue_push_request(handle->queue, static_cast<zbx_otel_request_t>(heap_request), OTEL_LOG))
	{
		reactor->Finish(grpc::Status(grpc::StatusCode::RESOURCE_EXHAUSTED, "export rate limit exceeded"));
		delete heap_request;
	}
	else
		reactor->Finish(Status::OK);

	reactor->Finish(Status::OK);
	return reactor;
}

namespace
{
	constexpr const char *DEFAULT_OTEL_PORT = "4317";

	std::string build_listen_address(const char *address, const char *port)
	{
		std::string addr = (address != nullptr && address[0] != '\0') ? address : "0.0.0.0";
		std::string prt = (port != nullptr && port[0] != '\0') ? port : DEFAULT_OTEL_PORT;
		return addr + ":" + prt;
	}

	void set_error(char **error, const std::string &msg)
	{
		*error = static_cast<char *>(zbx_malloc(NULL, msg.length() + 1));
		memcpy(*error, msg.c_str(), msg.length() + 1);
	}
}

extern "C"
{
	zbx_grpc_handle_t zbx_grpc_start(const char *address, const char *port, zbx_otel_queue_t *queue, char **error)
	{
		try
		{
			auto *handle = new GrpcServerHandle(queue);
			handle->trace_service = std::make_unique<TraceServiceImpl>(handle);
			handle->metrics_service = std::make_unique<MetricsServiceImpl>(handle);
			handle->logs_service = std::make_unique<LogsServiceImpl>(handle);

			std::string listen_address = build_listen_address(address, port);

			ServerBuilder builder;

			/* TODO: TLS support */
			builder.AddListeningPort(listen_address, grpc::InsecureServerCredentials());
			builder.RegisterService(handle->trace_service.get());
			builder.RegisterService(handle->metrics_service.get());
			builder.RegisterService(handle->logs_service.get());

			handle->server = builder.BuildAndStart();
			if (!handle->server)
			{
				delete handle;
				return nullptr;
			}

			zabbix_log(LOG_LEVEL_WARNING, "Open Telemetry collector listening on %s",
					listen_address.c_str());

			return handle;
		}
		catch (const std::exception &e)
		{
			set_error(error, std::string("cannot start collector: ") + e.what());
			return nullptr;
		}
	}

	void zbx_grpc_stop(zbx_grpc_handle_t handle)
	{
		if (handle == nullptr)
			return;

		GrpcServerHandle *server_handle = static_cast<GrpcServerHandle *>(handle);

		if (server_handle->server)
		{
			auto deadline = std::chrono::system_clock::now() + std::chrono::seconds(5);
			server_handle->server->Shutdown(deadline);
		}

		delete server_handle;

		google::protobuf::ShutdownProtobufLibrary();

		zabbix_log(LOG_LEVEL_WARNING, "Open Telemetry collector stopped");
	}

	int	zbx_otel_decode_request(zbx_otel_request_t request, zbx_otel_request_type_t type, char **output,
			char **error)
	{

		try
		{
			std::string debug_string;

			switch (type)
			{
				case OTEL_TRACE:
				{
					auto *req = static_cast<otlp_trace::ExportTraceServiceRequest *>(request);
					debug_string = req->DebugString();
					break;
				}
				case OTEL_METRIC:
				{
					auto *req = static_cast<otlp_metrics::ExportMetricsServiceRequest *>(request);
					debug_string = req->DebugString();
					break;
				}
				case OTEL_LOG:
				{
					auto *req = static_cast<otlp_logs::ExportLogsServiceRequest *>(request);
					debug_string = req->DebugString();
					break;
				}
				default:
					set_error(error, "unknown otel request type: " +
							std::to_string(static_cast<int>(type)));
					return FAIL;
			}

			size_t len = debug_string.length() + 1;
			*output = static_cast<char *>(zbx_malloc(NULL, len));
			memcpy(*output, debug_string.c_str(), len);

			return SUCCEED;
		}
		catch (const std::exception &e)
		{
			set_error(error, std::string("error decoding otel request: ") + e.what());
			return FAIL;
		}
	}

	void	zbx_otel_request_free(zbx_otel_request_t request, zbx_otel_request_type_t type)
	{
		switch (type)
		{
			case OTEL_TRACE:
			{
				auto *req = static_cast<otlp_trace::ExportTraceServiceRequest *>(request);
				delete req;
				break;
			}
			case OTEL_METRIC:
			{
				auto *req = static_cast<otlp_metrics::ExportMetricsServiceRequest *>(request);
				delete req;
				break;
			}
			case OTEL_LOG:
			{
				auto *req = static_cast<otlp_logs::ExportLogsServiceRequest *>(request);
				delete req;
				break;
			}
			default:
				THIS_SHOULD_NEVER_HAPPEN_MSG("unknown otel message type %d", type);
				break;
		}
	}
}
