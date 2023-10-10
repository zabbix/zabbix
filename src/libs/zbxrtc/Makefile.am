## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = \
	libzbxrtc.a \
	libzbxrtc_service.a

# common rtc functionality

libzbxrtc_a_CFLAGS = \
	$(TLS_CFLAGS)

libzbxrtc_a_SOURCES = \
	rtc.c \
	rtc.h

# common rtc service functionality

libzbxrtc_service_a_CFLAGS = \
	$(TLS_CFLAGS)

libzbxrtc_service_a_SOURCES = \
	rtc_service.c \
	rtc_client.c \
	rtc.h

