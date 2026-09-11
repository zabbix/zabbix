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

#include "apm_grpc.h"
#include "opentelemetry/proto/collector/logs/v1/logs_service.grpc.pb.h"
#include "opentelemetry/proto/collector/metrics/v1/metrics_service.grpc.pb.h"
#include "opentelemetry/proto/collector/trace/v1/trace_service.grpc.pb.h"
#include <grpcpp/grpcpp.h>
#include <string>
#include <cstdlib>
#include <cstring>
#include <cstdio>
#include <fstream>
#include <sstream>

extern "C" {
	#include "apm_queue.h"
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

static int grpc_initialized = 0;

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
	GrpcServerHandle(zbx_apm_queue_t *queue) : queue(queue) {}

	std::unique_ptr<Server> server;
	std::unique_ptr<TraceServiceImpl> trace_service;
	std::unique_ptr<MetricsServiceImpl> metrics_service;
	std::unique_ptr<LogsServiceImpl> logs_service;

	zbx_apm_queue_t * const queue;
};

ServerUnaryReactor *TraceServiceImpl::Export(CallbackServerContext *context,
		const otlp_trace::ExportTraceServiceRequest *request,
		otlp_trace::ExportTraceServiceResponse *response)
{
	ServerUnaryReactor *reactor = context->DefaultReactor();

	auto *heap_request = new otlp_trace::ExportTraceServiceRequest();
	heap_request->Swap(const_cast<otlp_trace::ExportTraceServiceRequest *>(request));

	if (FAIL == apm_queue_push_request(handle->queue, static_cast<zbx_apm_request_t>(heap_request), APM_TRACES))
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

	if (FAIL == apm_queue_push_request(handle->queue, static_cast<zbx_apm_request_t>(heap_request), APM_METRICS))
	{
		reactor->Finish(grpc::Status(grpc::StatusCode::RESOURCE_EXHAUSTED, "export rate limit exceeded"));
		delete heap_request;
	}
	else
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

	if (FAIL == apm_queue_push_request(handle->queue, static_cast<zbx_apm_request_t>(heap_request), APM_LOGS))
	{
		reactor->Finish(grpc::Status(grpc::StatusCode::RESOURCE_EXHAUSTED, "export rate limit exceeded"));
		delete heap_request;
	}
	else
		reactor->Finish(Status::OK);

	return reactor;
}

namespace
{
	constexpr const char *DEFAULT_APM_PORT = "4317";

	std::string build_listen_address(const char *address, const char *port)
	{
		std::string addr = (address != nullptr && address[0] != '\0') ? address : "0.0.0.0";
		std::string prt = (port != nullptr && port[0] != '\0') ? port : DEFAULT_APM_PORT;
		return addr + ":" + prt;
	}

	void set_error(char **error, const std::string &msg)
	{
		*error = static_cast<char *>(zbx_malloc(NULL, msg.length() + 1));
		memcpy(*error, msg.c_str(), msg.length() + 1);
	}

	static std::string read_pem(const char *path)
	{
		std::ifstream	f(path, std::ios::binary);

		if (!f)
			throw std::runtime_error(std::string("cannot open \"") + path + "\": " + zbx_strerror(errno));

		std::ostringstream	ss;
		ss << f.rdbuf();

		if (f.bad())
			throw std::runtime_error(std::string("cannot read \"") + path + "\"");

		return ss.str();
	}

	static std::shared_ptr<grpc::ServerCredentials>	build_credentials(const zbx_apm_config_tls_t *tls)
	{
		if (NULL == tls)
			return grpc::InsecureServerCredentials();

		grpc::SslServerCredentialsOptions	opts(NULL != tls->ca_file ?
				GRPC_SSL_REQUEST_AND_REQUIRE_CLIENT_CERTIFICATE_AND_VERIFY :
				GRPC_SSL_DONT_REQUEST_CLIENT_CERTIFICATE);

		/* private key first, then certificate chain */
		opts.pem_key_cert_pairs.push_back({read_pem(tls->key_file), read_pem(tls->cert_file)});

		if (NULL != tls->ca_file)
			opts.pem_root_certs = read_pem(tls->ca_file);

		return grpc::SslServerCredentials(opts);
	}
}

extern "C"
{
	zbx_grpc_handle_t zbx_grpc_start(const char *address, const char *port, zbx_apm_queue_t *queue,
			const zbx_apm_config_tls_t *tls, char **error)
	{
		try
		{
			auto *handle = new GrpcServerHandle(queue);
			handle->trace_service = std::make_unique<TraceServiceImpl>(handle);
			handle->metrics_service = std::make_unique<MetricsServiceImpl>(handle);
			handle->logs_service = std::make_unique<LogsServiceImpl>(handle);

			std::string listen_address = build_listen_address(address, port);

			ServerBuilder builder;

			builder.AddListeningPort(listen_address, build_credentials(tls));
			builder.RegisterService(handle->trace_service.get());
			builder.RegisterService(handle->metrics_service.get());
			builder.RegisterService(handle->logs_service.get());

			handle->server = builder.BuildAndStart();
			if (!handle->server)
			{
				set_error(error, std::string("cannot start collector"));
				delete handle;
				return nullptr;
			}

			grpc_initialized = 1;

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

	void	zbx_grpc_stop(zbx_grpc_handle_t handle)
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

		zabbix_log(LOG_LEVEL_WARNING, "Open Telemetry collector stopped");
	}

	void	zbx_grpc_shutdown(void)
	{
		if (1 == grpc_initialized)
			google::protobuf::ShutdownProtobufLibrary();
	}

	void	zbx_apm_request_free(zbx_apm_request_t request, zbx_apm_request_type_t type)
	{
		switch (type)
		{
			case APM_TRACES:
			{
				auto *req = static_cast<otlp_trace::ExportTraceServiceRequest *>(request);
				delete req;
				break;
			}
			case APM_METRICS:
			{
				auto *req = static_cast<otlp_metrics::ExportMetricsServiceRequest *>(request);
				delete req;
				break;
			}
			case APM_LOGS:
			{
				auto *req = static_cast<otlp_logs::ExportLogsServiceRequest *>(request);
				delete req;
				break;
			}
			default:
				THIS_SHOULD_NEVER_HAPPEN_MSG("unknown APM message type %d", type);
				break;
		}
	}
}
