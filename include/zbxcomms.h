/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_ZBXCOMMS_H
#define ZABBIX_ZBXCOMMS_H

#include "zbxalgo.h"

#ifdef _WINDOWS
#	define zbx_socket_last_error()		WSAGetLastError()

#	define ZBX_PROTO_ERROR			SOCKET_ERROR
#	define ZBX_SOCKET_TO_INT(s)		((int)(s))
#else
#	define zbx_socket_last_error()		errno

#	define ZBX_PROTO_ERROR		-1
#	define ZBX_SOCKET_TO_INT(s)	(s)
#endif

#ifdef _WINDOWS
typedef SOCKET	ZBX_SOCKET;
#else
typedef int	ZBX_SOCKET;
#endif

#if defined(HAVE_IPV6)
#	define ZBX_SOCKADDR struct sockaddr_storage
#else
#	define ZBX_SOCKADDR struct sockaddr_in
#endif

typedef enum
{
	ZBX_BUF_TYPE_STAT = 0,
	ZBX_BUF_TYPE_DYN
}
zbx_buf_type_t;

#define ZBX_SOCKET_COUNT	256
#define ZBX_STAT_BUF_LEN	2048

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#if defined(HAVE_GNUTLS)
#	include <gnutls/gnutls.h>
#	include <gnutls/x509.h>
#elif defined(HAVE_OPENSSL)
#	include <openssl/ssl.h>
#	include <openssl/err.h>
#	include <openssl/rand.h>
#endif

typedef struct
{
#if defined(HAVE_GNUTLS)
	gnutls_session_t		ctx;
	gnutls_psk_client_credentials_t	psk_client_creds;
	gnutls_psk_server_credentials_t	psk_server_creds;
#elif defined(HAVE_OPENSSL)
	SSL				*ctx;
#endif
} zbx_tls_context_t;
#endif

typedef struct
{
	ZBX_SOCKET			socket;
	ZBX_SOCKET			socket_orig;
	size_t				read_bytes;
	char				*buffer;
	char				*next_line;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_context_t		*tls_ctx;
#endif
	unsigned int			connection_type;	/* type of connection actually established: */
								/* ZBX_TCP_SEC_UNENCRYPTED, ZBX_TCP_SEC_TLS_PSK or */
								/* ZBX_TCP_SEC_TLS_CERT */
	int				timeout;
	zbx_buf_type_t			buf_type;
	unsigned char			accepted;
	int				num_socks;
	ZBX_SOCKET			sockets[ZBX_SOCKET_COUNT];
	char				buf_stat[ZBX_STAT_BUF_LEN];
	ZBX_SOCKADDR			peer_info;		/* getpeername() result */
	/* Peer host DNS name or IP address for diagnostics (after TCP connection is established). */
	/* TLS connection may be shut down at any time and it will not be possible to get peer IP address anymore. */
	char				peer[ZBX_MAX_DNSNAME_LEN + 1];
	int				protocol;
}
zbx_socket_t;

const char	*zbx_socket_strerror(void);

#ifndef _WINDOWS
void	zbx_gethost_by_ip(const char *ip, char *host, size_t hostlen);
void	zbx_getip_by_host(const char *host, char *ip, size_t iplen);
#endif

int	zbx_tcp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout,
		unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2);
void	zbx_socket_timeout_set(zbx_socket_t *s, int timeout);

#define ZBX_TCP_PROTOCOL		0x01
#define ZBX_TCP_COMPRESS		0x02
#define ZBX_TCP_LARGE			0x04

#define ZBX_TCP_SEC_UNENCRYPTED		1		/* do not use encryption with this socket */
#define ZBX_TCP_SEC_TLS_PSK		2		/* use TLS with pre-shared key (PSK) with this socket */
#define ZBX_TCP_SEC_TLS_CERT		4		/* use TLS with certificate with this socket */
#define ZBX_TCP_SEC_UNENCRYPTED_TXT	"unencrypted"
#define ZBX_TCP_SEC_TLS_PSK_TXT		"psk"
#define ZBX_TCP_SEC_TLS_CERT_TXT	"cert"

const char	*zbx_tcp_connection_type_name(unsigned int type);

#define zbx_tcp_send(s, d)				zbx_tcp_send_ext((s), (d), strlen(d), 0, ZBX_TCP_PROTOCOL, 0)
#define zbx_tcp_send_to(s, d, timeout)			zbx_tcp_send_ext((s), (d), strlen(d), 0,	\
									ZBX_TCP_PROTOCOL, timeout)
#define zbx_tcp_send_bytes_to(s, d, len, timeout)	zbx_tcp_send_ext((s), (d), len, 0, ZBX_TCP_PROTOCOL, timeout)
#define zbx_tcp_send_raw(s, d)				zbx_tcp_send_ext((s), (d), strlen(d), 0, 0, 0)

int	zbx_tcp_send_ext(zbx_socket_t *s, const char *data, size_t len, size_t reserved, unsigned char flags,
		int timeout);

void	zbx_tcp_close(zbx_socket_t *s);

#ifdef HAVE_IPV6
int	get_address_family(const char *addr, int *family, char *error, int max_error_len);
#endif

int	zbx_tcp_listen(zbx_socket_t *s, const char *listen_ip, unsigned short listen_port);
void	zbx_tcp_unlisten(zbx_socket_t *s);

int	zbx_tcp_accept(zbx_socket_t *s, unsigned int tls_accept);
void	zbx_tcp_unaccept(zbx_socket_t *s);

#define ZBX_TCP_READ_UNTIL_CLOSE 0x01

#define	zbx_tcp_recv(s)				SUCCEED_OR_FAIL(zbx_tcp_recv_ext(s, 0, 0))
#define	zbx_tcp_recv_large(s)			SUCCEED_OR_FAIL(zbx_tcp_recv_ext(s, 0, ZBX_TCP_LARGE))
#define	zbx_tcp_recv_to(s, timeout)		SUCCEED_OR_FAIL(zbx_tcp_recv_ext(s, timeout, 0))
#define	zbx_tcp_recv_raw(s)			SUCCEED_OR_FAIL(zbx_tcp_recv_raw_ext(s, 0))

ssize_t		zbx_tcp_recv_ext(zbx_socket_t *s, int timeout, unsigned char flags);
ssize_t		zbx_tcp_recv_raw_ext(zbx_socket_t *s, int timeout);
const char	*zbx_tcp_recv_line(zbx_socket_t *s);

int	zbx_ip_cmp(unsigned int prefix_size, const struct addrinfo *current_ai, ZBX_SOCKADDR name, int ipv6v4_mode);
int	zbx_validate_peer_list(const char *peer_list, char **error);
int	zbx_tcp_check_allowed_peers(const zbx_socket_t *s, const char *peer_list);
int	validate_cidr(const char *ip, const char *cidr, void *value);

int	zbx_udp_connect(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port, int timeout);
int	zbx_udp_send(zbx_socket_t *s, const char *data, size_t data_len, int timeout);
int	zbx_udp_recv(zbx_socket_t *s, int timeout);
void	zbx_udp_close(zbx_socket_t *s);

#define ZBX_DEFAULT_FTP_PORT		21
#define ZBX_DEFAULT_SSH_PORT		22
#define ZBX_DEFAULT_TELNET_PORT		23
#define ZBX_DEFAULT_SMTP_PORT		25
#define ZBX_DEFAULT_DNS_PORT		53
#define ZBX_DEFAULT_HTTP_PORT		80
#define ZBX_DEFAULT_POP_PORT		110
#define ZBX_DEFAULT_NNTP_PORT		119
#define ZBX_DEFAULT_NTP_PORT		123
#define ZBX_DEFAULT_IMAP_PORT		143
#define ZBX_DEFAULT_LDAP_PORT		389
#define ZBX_DEFAULT_HTTPS_PORT		443
#define ZBX_DEFAULT_AGENT_PORT		10050
#define ZBX_DEFAULT_SERVER_PORT		10051
#define ZBX_DEFAULT_GATEWAY_PORT	10052

#define ZBX_DEFAULT_AGENT_PORT_STR	"10050"
#define ZBX_DEFAULT_SERVER_PORT_STR	"10051"

#ifdef HAVE_IPV6
#	define zbx_getnameinfo(sa, host, hostlen, serv, servlen, flags)		\
			getnameinfo(sa, AF_INET == (sa)->sa_family ?		\
					sizeof(struct sockaddr_in) :		\
					sizeof(struct sockaddr_in6),		\
					host, hostlen, serv, servlen, flags)
#endif

#ifdef _WINDOWS
int	zbx_socket_start(char **error);
#endif

int	zbx_telnet_test_login(ZBX_SOCKET socket_fd);
int	zbx_telnet_login(ZBX_SOCKET socket_fd, const char *username, const char *password, AGENT_RESULT *result);
int	zbx_telnet_execute(ZBX_SOCKET socket_fd, const char *command, AGENT_RESULT *result, const char *encoding);

/* TLS BLOCK */
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#if defined(HAVE_OPENSSL) && OPENSSL_VERSION_NUMBER < 0x1010000fL || defined(LIBRESSL_VERSION_NUMBER)
#	if !defined(LIBRESSL_VERSION_NUMBER)
#		define OPENSSL_INIT_LOAD_SSL_STRINGS			0
#		define OPENSSL_INIT_LOAD_CRYPTO_STRINGS		0
#		define OPENSSL_VERSION					SSLEAY_VERSION
#	endif
#	define OpenSSL_version					SSLeay_version
#	define TLS_method					TLSv1_2_method
#	define TLS_client_method				TLSv1_2_client_method
#	define SSL_CTX_get_ciphers(ciphers)			((ciphers)->cipher_list)
#	if !defined(LIBRESSL_VERSION_NUMBER)
#		define SSL_CTX_set_min_proto_version(ctx, TLSv)	1
#	endif
#endif

#if defined(_WINDOWS)

/* Typical thread is long-running, if necessary, it initializes TLS for itself. Zabbix sender is an exception. If */
/* data is sent from a file or in real time then sender's 'main' thread starts the 'send_value' thread for each   */
/* 250 values to be sent. To avoid TLS initialization on every start of 'send_value' thread we initialize TLS in  */
/* 'main' thread and use this structure for passing minimum TLS variables into 'send_value' thread. */

struct zbx_thread_sendval_tls_args
{
#if defined(HAVE_GNUTLS)
	gnutls_certificate_credentials_t	my_cert_creds;
	gnutls_psk_client_credentials_t		my_psk_client_creds;
	gnutls_priority_t			ciphersuites_cert;
	gnutls_priority_t			ciphersuites_psk;
#elif defined(HAVE_OPENSSL)
	SSL_CTX			*ctx_cert;
#ifdef HAVE_OPENSSL_WITH_PSK
	SSL_CTX			*ctx_psk;
	const char		*psk_identity_for_cb;
	size_t			psk_identity_len_for_cb;
	char			*psk_for_cb;
	size_t			psk_len_for_cb;
#endif
#endif
};

typedef struct zbx_thread_sendval_tls_args ZBX_THREAD_SENDVAL_TLS_ARGS;

void	zbx_tls_pass_vars(ZBX_THREAD_SENDVAL_TLS_ARGS *args);
void	zbx_tls_take_vars(ZBX_THREAD_SENDVAL_TLS_ARGS *args);

#endif	/* #if defined(_WINDOWS) */

void	zbx_tls_validate_config(void);
void	zbx_tls_library_deinit(void);
void	zbx_tls_init_parent(void);
void	zbx_tls_init_child(void);
void	zbx_tls_free(void);
void	zbx_tls_free_on_signal(void);
void	zbx_tls_version(void);

#endif	/* #if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL) */
typedef struct
{
	const char	*psk_identity;
	size_t		psk_identity_len;
	char		issuer[HOST_TLS_ISSUER_LEN_MAX];
	char		subject[HOST_TLS_SUBJECT_LEN_MAX];
}
zbx_tls_conn_attr_t;

int		zbx_tls_get_attr_cert(const zbx_socket_t *s, zbx_tls_conn_attr_t *attr);
int		zbx_tls_get_attr_psk(const zbx_socket_t *s, zbx_tls_conn_attr_t *attr);
int		zbx_check_server_issuer_subject(zbx_socket_t *sock, char **error);
unsigned int	zbx_tls_get_psk_usage(void);

/* TLS BLOCK END */

#endif /* ZABBIX_ZBXCOMMS_H */
