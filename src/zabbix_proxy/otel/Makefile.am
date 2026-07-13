## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxotel.a

libzbxotel_a_CFLAGS = \
	$(TLS_CFLAGS)

libzbxotel_a_CXXFLAGS = \
	$(GRPCPP_CFLAGS) \
	$(PROTOBUF_CFLAGS) \
	-I$(srcdir)

OTEL_PROTO_SRCS = \
	opentelemetry/proto/collector/logs/v1/logs_service.grpc.pb.cc \
	opentelemetry/proto/collector/logs/v1/logs_service.pb.cc \
	opentelemetry/proto/collector/metrics/v1/metrics_service.grpc.pb.cc \
	opentelemetry/proto/collector/metrics/v1/metrics_service.pb.cc \
	opentelemetry/proto/collector/trace/v1/trace_service.grpc.pb.cc \
	opentelemetry/proto/collector/trace/v1/trace_service.pb.cc \
	opentelemetry/proto/common/v1/common.grpc.pb.cc \
	opentelemetry/proto/common/v1/common.pb.cc \
	opentelemetry/proto/logs/v1/logs.grpc.pb.cc \
	opentelemetry/proto/logs/v1/logs.pb.cc \
	opentelemetry/proto/metrics/v1/metrics.grpc.pb.cc \
	opentelemetry/proto/metrics/v1/metrics.pb.cc \
	opentelemetry/proto/resource/v1/resource.grpc.pb.cc \
	opentelemetry/proto/resource/v1/resource.pb.cc \
	opentelemetry/proto/trace/v1/trace.grpc.pb.cc \
	opentelemetry/proto/trace/v1/trace.pb.cc


libzbxotel_a_SOURCES = \
	zbx_otel.h \
	otel_manager.c \
	otel_worker.c \
	otel_worker.h \
	otel_grpc.cc \
	otel_grpc.h \
	otel_task.c \
	otel_task.h \
	$(OTEL_PROTO_SRCS)
