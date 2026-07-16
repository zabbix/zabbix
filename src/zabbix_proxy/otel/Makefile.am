## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxotel.a

# vendored .proto sources (from open-telemetry/opentelemetry-proto)
OTEL_PROTO_DIR = $(srcdir)

OTEL_PROTO_FILES = \
	opentelemetry/proto/common/v1/common.proto \
	opentelemetry/proto/resource/v1/resource.proto \
	opentelemetry/proto/trace/v1/trace.proto \
	opentelemetry/proto/metrics/v1/metrics.proto \
	opentelemetry/proto/logs/v1/logs.proto \
	opentelemetry/proto/collector/trace/v1/trace_service.proto \
	opentelemetry/proto/collector/metrics/v1/metrics_service.proto \
	opentelemetry/proto/collector/logs/v1/logs_service.proto

# message-only protos: no service defined -> .pb.cc/.pb.h only
OTEL_PROTO_MSG_SRCS = \
	opentelemetry/proto/common/v1/common.pb.cc \
	opentelemetry/proto/resource/v1/resource.pb.cc \
	opentelemetry/proto/trace/v1/trace.pb.cc \
	opentelemetry/proto/metrics/v1/metrics.pb.cc \
	opentelemetry/proto/logs/v1/logs.pb.cc

OTEL_PROTO_MSG_HDRS = \
	opentelemetry/proto/common/v1/common.pb.h \
	opentelemetry/proto/resource/v1/resource.pb.h \
	opentelemetry/proto/trace/v1/trace.pb.h \
	opentelemetry/proto/metrics/v1/metrics.pb.h \
	opentelemetry/proto/logs/v1/logs.pb.h

# collector service protos: .pb.cc/.h AND .grpc.pb.cc/.h
OTEL_PROTO_SVC_SRCS = \
	opentelemetry/proto/collector/trace/v1/trace_service.pb.cc \
	opentelemetry/proto/collector/trace/v1/trace_service.grpc.pb.cc \
	opentelemetry/proto/collector/metrics/v1/metrics_service.pb.cc \
	opentelemetry/proto/collector/metrics/v1/metrics_service.grpc.pb.cc \
	opentelemetry/proto/collector/logs/v1/logs_service.pb.cc \
	opentelemetry/proto/collector/logs/v1/logs_service.grpc.pb.cc

OTEL_PROTO_SVC_HDRS = \
	opentelemetry/proto/collector/trace/v1/trace_service.pb.h \
	opentelemetry/proto/collector/trace/v1/trace_service.grpc.pb.h \
	opentelemetry/proto/collector/metrics/v1/metrics_service.pb.h \
	opentelemetry/proto/collector/metrics/v1/metrics_service.grpc.pb.h \
	opentelemetry/proto/collector/logs/v1/logs_service.pb.h \
	opentelemetry/proto/collector/logs/v1/logs_service.grpc.pb.h

OTEL_PROTO_SRCS = $(OTEL_PROTO_MSG_SRCS) $(OTEL_PROTO_SVC_SRCS)
OTEL_PROTO_HDRS = $(OTEL_PROTO_MSG_HDRS) $(OTEL_PROTO_SVC_HDRS)

BUILT_SOURCES = $(OTEL_PROTO_SRCS) $(OTEL_PROTO_HDRS)

libzbxotel_a_CFLAGS = \
	$(TLS_CFLAGS)

libzbxotel_a_CXXFLAGS = \
	$(GRPCPP_CFLAGS) \
	$(PROTOBUF_CFLAGS) \
	-I$(builddir) \
	-I$(srcdir)

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

# --- message-only protos: --cpp_out only ---

opentelemetry/proto/common/v1/common.pb.cc opentelemetry/proto/common/v1/common.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/common/v1/common.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --cpp_out=$(builddir) $<

opentelemetry/proto/resource/v1/resource.pb.cc opentelemetry/proto/resource/v1/resource.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/resource/v1/resource.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --cpp_out=$(builddir) $<

opentelemetry/proto/trace/v1/trace.pb.cc opentelemetry/proto/trace/v1/trace.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/trace/v1/trace.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --cpp_out=$(builddir) $<

opentelemetry/proto/metrics/v1/metrics.pb.cc opentelemetry/proto/metrics/v1/metrics.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/metrics/v1/metrics.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --cpp_out=$(builddir) $<

opentelemetry/proto/logs/v1/logs.pb.cc opentelemetry/proto/logs/v1/logs.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/logs/v1/logs.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --cpp_out=$(builddir) $<

# --- collector service protos: --cpp_out AND --grpc_out ---

opentelemetry/proto/collector/trace/v1/trace_service.pb.cc opentelemetry/proto/collector/trace/v1/trace_service.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/collector/trace/v1/trace_service.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --cpp_out=$(builddir) $<

opentelemetry/proto/collector/trace/v1/trace_service.grpc.pb.cc opentelemetry/proto/collector/trace/v1/trace_service.grpc.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/collector/trace/v1/trace_service.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --grpc_out=$(builddir) --plugin=protoc-gen-grpc=$(GRPC_CPP_PLUGIN) $<

opentelemetry/proto/collector/metrics/v1/metrics_service.pb.cc opentelemetry/proto/collector/metrics/v1/metrics_service.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/collector/metrics/v1/metrics_service.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --cpp_out=$(builddir) $<

opentelemetry/proto/collector/metrics/v1/metrics_service.grpc.pb.cc opentelemetry/proto/collector/metrics/v1/metrics_service.grpc.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/collector/metrics/v1/metrics_service.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --grpc_out=$(builddir) --plugin=protoc-gen-grpc=$(GRPC_CPP_PLUGIN) $<

opentelemetry/proto/collector/logs/v1/logs_service.pb.cc opentelemetry/proto/collector/logs/v1/logs_service.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/collector/logs/v1/logs_service.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --cpp_out=$(builddir) $<

opentelemetry/proto/collector/logs/v1/logs_service.grpc.pb.cc opentelemetry/proto/collector/logs/v1/logs_service.grpc.pb.h: $(OTEL_PROTO_DIR)/opentelemetry/proto/collector/logs/v1/logs_service.proto
	$(PROTOC) -I$(OTEL_PROTO_DIR) --grpc_out=$(builddir) --plugin=protoc-gen-grpc=$(GRPC_CPP_PLUGIN) $<

EXTRA_DIST = $(OTEL_PROTO_FILES)

CLEANFILES = $(OTEL_PROTO_SRCS) $(OTEL_PROTO_HDRS)

