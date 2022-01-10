## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = \
	libzbxrtc.a \
	libzbxrtc_service.a \
	libzbxrtc_server.a \
	libzbxrtc_proxy.a

# common rtc functionality

libzbxrtc_a_SOURCES = \
	rtc.c \
	rtc.h

# common rtc service functionality

libzbxrtc_service_a_SOURCES = \
	rtc_service.c \
	rtc_client.c \
	rtc.h 

# server specific functionality
 
libzbxrtc_server_a_SOURCES = \
	rtc_server.c \
	rtc.h

# proxy specific functionality

libzbxrtc_proxy_a_SOURCES = \
	rtc_proxy.c \
	rtc.h

