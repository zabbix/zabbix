/*
** Copyright (C) 2001-2024 Zabbix SIA
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

package tls

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../include -I${SRCDIR}/../../../../build/win32/include

#cgo openssl LDFLAGS: -lssl -lcrypto -lwsock32 -lws2_32

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <unistd.h>
#include <ctype.h>
#include "common/config.h"

#define TLS_UNUSED(var)	(void)(var)

const char	*tls_crypto_init_msg;

#ifdef HAVE_OPENSSL
#include <openssl/ssl.h>
#include <openssl/err.h>
#include <openssl/bio.h>
#include <openssl/rand.h>

#if defined(LIBRESSL_VERSION_NUMBER)
#	error package golang.zabbix.com/agent2/pkg/tls cannot be compiled with LibreSSL. Encryption is supported with OpenSSL.
#elif !defined(HAVE_OPENSSL_WITH_PSK)
#	error package golang.zabbix.com/agent2/pkg/tls cannot be compiled with OpenSSL which has excluded PSK support.
#elif defined(_WINDOWS) && OPENSSL_VERSION_NUMBER < 0x1010100fL	// On MS Windows OpenSSL 1.1.1 is required
#	error on Microsoft Windows the package golang.zabbix.com/agent2/pkg/tls requires OpenSSL 1.1.1 or newer.
#elif OPENSSL_VERSION_NUMBER < 0x1000100fL
	// OpenSSL before 1.0.1
#	error package golang.zabbix.com/agent2/pkg/tls cannot be compiled with this OpenSSL version.\
		Supported versions are 1.0.1 and newer.
#endif

#if OPENSSL_VERSION_NUMBER < 0x1010000fL
	// OpenSSL 1.0.1/1.0.2 (before 1.1.0)
#include <openssl/x509v3.h>	// string_to_hex()
#	define OPENSSL_hexstr2buf			string_to_hex
#	define TLS_method				TLSv1_2_method
#	define SSL_CTX_get_ciphers(ciphers)		((ciphers)->cipher_list)
#	define OPENSSL_VERSION				SSLEAY_VERSION
#	define OpenSSL_version				SSLeay_version
#	define SSL_CTX_set_min_proto_version(ctx, TLSv)	1
#endif

#define TLS_EX_DATA_ERRBIO	0
#define TLS_EX_DATA_IDENTITY	1
#define TLS_EX_DATA_KEY		2

typedef SSL_CTX * SSL_CTX_LP;

typedef struct {
	SSL *ssl;
	BIO *in;
	BIO *out;
	BIO *err;
	int ready;
	char *psk_identity;
	char *psk_key;
} tls_t;

#if OPENSSL_VERSION_NUMBER < 0x1010000fL
        // OpenSSL 1.0.1/1.0.2 (before 1.1.0)
#include <pthread.h>

// exit codes
#define ZBX_EXIT_LOCK_FAILED	2
#define ZBX_EXIT_UNLOCK_FAILED	3

static pthread_mutex_t	*mutexes = NULL;	// Mutexes for multi-threaded OpenSSL (see "man 3ssl threads"
						// and example in crypto/threads/mttest.c).

static void	zbx_mutex_lock(const char *filename, int line, int idx)
{
	if (0 != pthread_mutex_lock(mutexes + idx))
	{
		fprintf(stderr, "[file:'%s',line:%d] lock failed: [%d] %s\n", filename, line, errno, strerror(errno));
		exit(ZBX_EXIT_LOCK_FAILED);
	}
}

static void	zbx_mutex_unlock(const char *filename, int line, int idx)
{
	if (0 != pthread_mutex_unlock(mutexes + idx))
	{
		fprintf(stderr, "[file:'%s',line:%d] unlock failed: [%d] %s\n", filename, line, errno, strerror(errno));
		exit(ZBX_EXIT_UNLOCK_FAILED);
	}
}

static void	zbx_openssl_locking_cb(int mode, int n, const char *file, int line)
{
	if (0 != (mode & CRYPTO_LOCK))
		zbx_mutex_lock(file, line, n);
	else
		zbx_mutex_unlock(file, line, n);
}

static int	zbx_allocate_mutexes(const char **error_msg)
{
	int	num_locks, i;

	num_locks = CRYPTO_num_locks();

	if (NULL == (mutexes = malloc((size_t)num_locks * sizeof(pthread_mutex_t))))
	{
		*error_msg = strdup("cannot allocate mutexes for OpenSSL library: out of memory");
		return -1;
	}

	for (i = 0; i < num_locks; i++)
	{
		int	res;

		if (0 != (res = pthread_mutex_init(mutexes + i, NULL)))
		{
			char	buf[128];

			snprintf(buf, sizeof(buf), "cannot initialize mutex %d (out of %d) for OpenSSL library:"
					" pthread_mutex_init() returned %d", i, num_locks, res);

			*error_msg = strdup(buf);
			return -1;
		}
	}

	return 0;
}
#endif

static int tls_init(void)
{
#if OPENSSL_VERSION_NUMBER >= 0x1010000fL
	// OpenSSL 1.1.0 or newer
	if (1 != OPENSSL_init_ssl(OPENSSL_INIT_LOAD_SSL_STRINGS | OPENSSL_INIT_LOAD_CRYPTO_STRINGS, NULL))
	{
		tls_crypto_init_msg = "cannot initialize OpenSSL library";
		return -1;
	}
#else	// OpenSSL 1.0.1/1.0.2 (before 1.1.0)
	SSL_load_error_strings();
	ERR_load_BIO_strings();
	SSL_library_init();

	if (0 != zbx_allocate_mutexes(&tls_crypto_init_msg))
		return -1;

	CRYPTO_set_locking_callback((void (*)(int, int, const char *, int))zbx_openssl_locking_cb);

	// do not register our own threadid_func() callback, use OpenSSL default one
#endif
	if (1 != RAND_status())		// protect against not properly seeded PRNG
	{
		tls_crypto_init_msg = "cannot initialize PRNG";
		return -1;
	}

	tls_crypto_init_msg = "OpenSSL library successfully initialized";
	return 0;
}

static unsigned int tls_psk_client_cb(SSL *ssl, const char *hint, char *identity,
	unsigned int max_identity_len, unsigned char *psk, unsigned int max_psk_len)
{
	size_t		sz;
	const char	*psk_identity, *psk_key;
	BIO		*err;
	unsigned char 	*key;
	long		key_len;

	TLS_UNUSED(hint);

	if (NULL == (err = (BIO *)SSL_get_ex_data(ssl, TLS_EX_DATA_ERRBIO)))
		return 0;

	if (NULL == (psk_identity = (const char *)SSL_get_ex_data(ssl, TLS_EX_DATA_IDENTITY)))
	{
		BIO_printf(err, "no PSK identity configured");
		return 0;
	}

	if (NULL == (psk_key = (const char *)SSL_get_ex_data(ssl, TLS_EX_DATA_KEY)))
	{
		BIO_printf(err, "no PSK key configured");
		return 0;
	}

	sz = strlen(psk_identity) + 1;
	if (sz > max_identity_len)
	{
		BIO_printf(err, "PSK identity too large");
		return 0;
	}

	memcpy(identity, psk_identity, sz);

	key = OPENSSL_hexstr2buf(psk_key, &key_len);
	if (key == NULL)
	{
		BIO_printf(err, "invalid PSK key");
		return 0;
	}

	if (key_len > (long)max_psk_len)
	{
		BIO_printf(err, "PSK key is too large");
		OPENSSL_free(key);
		return 0;
	}

	memcpy(psk, key, (size_t)key_len);
	OPENSSL_free(key);
	return (unsigned int)key_len;
}

static unsigned int tls_psk_server_cb(SSL *ssl, const char *identity, unsigned char *psk, unsigned int max_psk_len)
{
	const char	*psk_identity, *psk_key;
	BIO		*err;
	unsigned char	*key;
	long		key_len;

	if (NULL == (err = (BIO *)SSL_get_ex_data(ssl, TLS_EX_DATA_ERRBIO)))
		return 0;

	if (NULL == (psk_identity = (const char *)SSL_get_ex_data(ssl, TLS_EX_DATA_IDENTITY)))
	{
		BIO_printf(err, "no PSK identity configured");
		return 0;
	}

	if (0 != strcmp(psk_identity, identity))
	{
		BIO_printf(err, "invalid PSK identity");
		return 0;
	}

	if (NULL == (psk_key = (const char *)SSL_get_ex_data(ssl, TLS_EX_DATA_KEY)))
	{
		BIO_printf(err, "no PSK key configured");
		return 0;
	}

	key = OPENSSL_hexstr2buf(psk_key, &key_len);
	if (key == NULL)
	{
		BIO_printf(err, "invalid PSK key");
		return 0;
	}

	if (key_len > (long)max_psk_len)
	{
		BIO_printf(err, "PSK key is too large");
		return 0;
	}

	memcpy(psk, key, (size_t)key_len);
	OPENSSL_free(key);
	return (unsigned int)key_len;
}

static int	zbx_set_ecdhe_parameters(SSL_CTX *ctx)
{
	long	res;
	int	ret = 0;
#if defined(OPENSSL_VERSION_MAJOR) && OPENSSL_VERSION_NUMBER >= 3	// OpenSSL 3.0.0 or newer
#define ARRSIZE(a)	(sizeof(a) / sizeof(*a))

	int	grp_list[1] = { NID_X9_62_prime256v1 };	// use curve secp256r1/prime256v1/NIST P-256

	if (1 != (res = SSL_CTX_set1_groups(ctx, grp_list, ARRSIZE(grp_list))))
		ret = -1;
#undef ARRSIZE
#else
	EC_KEY	*ecdh;

	// use curve secp256r1/prime256v1/NIST P-256

	if (NULL == (ecdh = EC_KEY_new_by_curve_name(NID_X9_62_prime256v1)))
		return -1;

	SSL_CTX_set_options(ctx, SSL_OP_SINGLE_ECDH_USE);

	if (1 != (res = SSL_CTX_set_tmp_ecdh(ctx, ecdh)))
		ret = -1;

	EC_KEY_free(ecdh);
#endif
	return ret;
}

static void *tls_new_context(const char *ca_file, const char *crl_file, const char *cert_file, const char *key_file,
		const char *cipher, const char *cipher13, char **error)
{
#define TLS_CIPHER_CERT_ECDHE		"EECDH+aRSA+AES128:"
#define TLS_CIPHER_CERT			"RSA+aRSA+AES128"

#if defined(HAVE_OPENSSL_WITH_PSK)
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	// OpenSSL 1.1.1 or newer
	// TLS_AES_256_GCM_SHA384 is excluded from client ciphersuite list for PSK based connections.
	// By default, in TLS 1.3 only *-SHA256 ciphersuites work with PSK.
#	define TLS_1_3_CIPHERSUITES	"TLS_CHACHA20_POLY1305_SHA256:TLS_AES_128_GCM_SHA256"
#endif
#if OPENSSL_VERSION_NUMBER >= 0x1010000fL	// OpenSSL 1.1.0 or newer
#	define TLS_CIPHER_PSK_ECDHE	"kECDHEPSK+AES128:"
#	define TLS_CIPHER_PSK		"kPSK+AES128"
#else						// OpenSSL 1.0.1/1.0.2 (before 1.1.0)
#	define TLS_CIPHER_PSK_ECDHE	""
#	define TLS_CIPHER_PSK		"PSK-AES128-CBC-SHA"
#endif
#endif
	SSL_CTX		*ctx;
	int		ret = -1;
	const char	*ciphers;

	if (NULL == (ctx = SSL_CTX_new(TLS_method())))
		goto out;

	if (1 != SSL_CTX_set_min_proto_version(ctx, TLS1_2_VERSION))
		goto out;

	if (NULL != ca_file)
	{
		if (1 != SSL_CTX_load_verify_locations(ctx, ca_file, NULL))
			goto out;

		SSL_CTX_set_verify(ctx, SSL_VERIFY_PEER | SSL_VERIFY_FAIL_IF_NO_PEER_CERT, NULL);

		if (NULL != crl_file)
		{
			X509_STORE	*store_cert;
			X509_LOOKUP	*lookup_cert;
			int		count_cert;

			store_cert = SSL_CTX_get_cert_store(ctx);

			if (NULL == (lookup_cert = X509_STORE_add_lookup(store_cert, X509_LOOKUP_file())))
				goto out;

			if (0 >= (count_cert = X509_load_crl_file(lookup_cert, crl_file, X509_FILETYPE_PEM)))
				goto out;

			if (1 != X509_STORE_set_flags(store_cert, X509_V_FLAG_CRL_CHECK | X509_V_FLAG_CRL_CHECK_ALL))
				goto out;
		}
	}

	if (NULL != cert_file && 1 != SSL_CTX_use_certificate_chain_file(ctx, cert_file))
		goto out;

	if (NULL != key_file && 1 != SSL_CTX_use_PrivateKey_file(ctx, key_file, SSL_FILETYPE_PEM))
		goto out;

	SSL_CTX_set_mode(ctx, SSL_MODE_AUTO_RETRY);
	SSL_CTX_set_options(ctx, SSL_OP_CIPHER_SERVER_PREFERENCE | SSL_OP_NO_TICKET);
	SSL_CTX_clear_options(ctx, SSL_OP_LEGACY_SERVER_CONNECT);
	SSL_CTX_set_session_cache_mode(ctx, SSL_SESS_CACHE_OFF);

	// try to enable ECDH ciphersuites
	if (0 == zbx_set_ecdhe_parameters(ctx))
	{
		if (NULL != ca_file)
			ciphers = TLS_CIPHER_CERT_ECDHE TLS_CIPHER_CERT ":" TLS_CIPHER_PSK_ECDHE TLS_CIPHER_PSK;
		else
			ciphers = TLS_CIPHER_PSK_ECDHE TLS_CIPHER_PSK;
	}
	else
	{
		if (NULL != ca_file)
			ciphers = TLS_CIPHER_CERT ":" TLS_CIPHER_PSK;
		else
			ciphers = TLS_CIPHER_PSK;
	}

#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	// OpenSSL 1.1.1
	if (1 != SSL_CTX_set_ciphersuites(ctx, (NULL != cipher13) ? cipher13 : TLS_1_3_CIPHERSUITES))
		goto out;
#else
	if (NULL != cipher13)
	{
		*error = strdup("cannot set list of TLS 1.3"
				" certificate ciphersuites: compiled with OpenSSL version older than 1.1.1,"
				" consider not using parameters \"TLSCipherCert13\"");
		goto out;
	}
#endif
	if (NULL != cipher)
		ciphers = cipher;

	if (1 != SSL_CTX_set_cipher_list(ctx, ciphers))
		goto out;

	ret = 0;
out:
	if (-1 == ret)
	{
		if (NULL == *error)
		{
			int	sz;
			BIO	*err;

			err = BIO_new(BIO_s_mem());
			BIO_set_nbio(err, 1);
			ERR_print_errors(err);

			sz = (int)BIO_ctrl_pending(err);
			if (sz != 0)
			{
				*error = malloc((size_t)sz + 1);
				BIO_read(err, *error, sz);
				(*error)[sz] = '\0';
			}
			else
				*error = strdup("unknown openssl error");

			BIO_vfree(err);
		}

		if (NULL != ctx)
		{
			SSL_CTX_free(ctx);
			ctx = NULL;
		}
	}
	return ctx;
}

static void tls_free_context(SSL_CTX_LP ctx)
{
	SSL_CTX_free(ctx);
}

static int tls_new(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key, tls_t **ptls)
{
	tls_t	*tls;

	*ptls = tls = malloc(sizeof(tls_t));
	memset(tls, 0, sizeof(tls_t));

	if (NULL != psk_identity)
		tls->psk_identity = strdup(psk_identity);
	if (NULL != psk_key)
		tls->psk_key = strdup(psk_key);

	tls->err = BIO_new(BIO_s_mem());
	BIO_set_nbio(tls->err, 1);

	if (NULL == (tls->ssl = SSL_new(ctx)))
		return -1;

	if (1 != SSL_set_ex_data(tls->ssl, TLS_EX_DATA_ERRBIO, (void *)tls->err))
		return -1;

	if (1 != SSL_set_ex_data(tls->ssl, TLS_EX_DATA_IDENTITY, (void *)tls->psk_identity))
		return -1;

	if (1 != SSL_set_ex_data(tls->ssl, TLS_EX_DATA_KEY, (void *)tls->psk_key))
		return -1;

	tls->in = BIO_new(BIO_s_mem());
	tls->out = BIO_new(BIO_s_mem());
	BIO_set_nbio(tls->in, 1);
	BIO_set_nbio(tls->out, 1);
	SSL_set_bio(tls->ssl, tls->in, tls->out);

	return 0;
}

static tls_t *tls_new_client(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key, const char *servername)
{
	tls_t	*tls;
	int	ret;

	if (0 == tls_new(ctx, psk_identity, psk_key, &tls))
	{
		if (psk_identity != NULL && psk_key != NULL)
			SSL_set_psk_client_callback(tls->ssl, tls_psk_client_cb);

		if (NULL != servername && '\0' != *servername)
			SSL_set_tlsext_host_name(tls->ssl, servername);

		SSL_set_connect_state(tls->ssl);
		if (1 == (ret = SSL_connect(tls->ssl)) || SSL_ERROR_WANT_READ == SSL_get_error(tls->ssl, ret))
			tls->ready = 1;
	}
	return tls;
}

static tls_t *tls_new_server(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key)
{
	tls_t	*tls;
	int	ret;

	if (0 == tls_new(ctx, psk_identity, psk_key, &tls))
	{
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	// OpenSSL 1.1.1 or newer, or LibreSSL
		if (1 != SSL_set_session_id_context(tls->ssl, (const unsigned char *)"Zbx", sizeof("Zbx") - 1))
			return tls;
#endif
		if (psk_identity != NULL && psk_key != NULL)
			SSL_set_psk_server_callback(tls->ssl, tls_psk_server_cb);

		SSL_set_accept_state(tls->ssl);

		if (1 == (ret = SSL_accept(tls->ssl)) || SSL_ERROR_WANT_READ == SSL_get_error(tls->ssl, ret))
			tls->ready = 1;
	}
	return tls;
}

static int tls_recv(tls_t *tls, char *buf, int size)
{
	if (BIO_ctrl_pending(tls->out))
		return BIO_read(tls->out, buf, size);
	return 0;
}

static int tls_send(tls_t *tls, char *buf, int size)
{
	return BIO_write(tls->in, buf, size);
}

static int tls_connected(tls_t *tls)
{
	return SSL_is_init_finished(tls->ssl);
}

static int tls_write(tls_t *tls, char *buf, int len)
{
	return SSL_write(tls->ssl, buf, len);
}

static int tls_read(tls_t *tls, char *buf, int len)
{
	int ret;
	ret = SSL_read(tls->ssl, buf, len);
	if (0 > ret) {
		if (SSL_ERROR_WANT_READ == SSL_get_error(tls->ssl, ret))
			return 0;
		return ret;
	}
	return ret;
}

static int tls_handshake(tls_t *tls)
{
	int ret;
	ret = SSL_do_handshake(tls->ssl);
	if (0 > ret) {
		if (SSL_ERROR_WANT_READ == SSL_get_error(tls->ssl, ret))
			return 1;
		return ret;
	}
	return 0;
}

static int tls_accept(tls_t *tls)
{
	int ret;
	ret = SSL_accept(tls->ssl);
	if (0 > ret) {
		if (SSL_ERROR_WANT_READ == SSL_get_error(tls->ssl, ret))
			return 1;
		return ret;
	}
	return 0;
}

static size_t tls_error(tls_t *tls, char **buf)
{
	size_t	sz;

	sz = BIO_ctrl_pending(tls->err);
	if (sz == 0)
	{
		long	verify_result;

		if (X509_V_OK != (verify_result = SSL_get_verify_result(tls->ssl)))
			BIO_printf(tls->err, "%s: ", X509_verify_cert_error_string(verify_result));

		ERR_print_errors(tls->err);
		sz = BIO_ctrl_pending(tls->err);
	}

	if (sz != 0)
	{
		*buf = malloc(sz + 1);
		BIO_read(tls->err, *buf, (int)sz);
		(*buf)[sz] = '\0';
	}
	else
		*buf = strdup("unknown error");

	BIO_reset(tls->err);
	return sz;
}

static int tls_ready(tls_t *tls)
{
	return tls->ready;
}

static int tls_close(tls_t *tls)
{
	int	ret;

	if (0 != (SSL_get_shutdown(tls->ssl) & SSL_RECEIVED_SHUTDOWN))
		return 0;

	if (0 > (ret = SSL_shutdown(tls->ssl)) && SSL_ERROR_WANT_READ == SSL_get_error(tls->ssl, ret))
		return 0;

	return ret;
}

static void tls_free(tls_t *tls)
{
	if (NULL == tls)
		return;
	if (NULL != tls->ssl)
		SSL_free(tls->ssl);
	if (NULL != tls->err)
		BIO_vfree(tls->err);
	if (NULL != tls->psk_identity)
		free(tls->psk_identity);
	if (NULL != tls->psk_key)
		free(tls->psk_key);
	free(tls);
}

static int	tls_get_x509_name(tls_t *tls, X509_NAME *dn, char **name)
{
	BIO		*bio;
	const char	*data;
	size_t		len;
	int		ret = -1;

	if (NULL == (bio = BIO_new(BIO_s_mem())))
	{
		BIO_printf(tls->err, "cannot create OpenSSL BIO");
		return -1;
	}

	if (0 > X509_NAME_print_ex(bio, dn, 0, XN_FLAG_RFC2253 & ~ASN1_STRFLGS_ESC_MSB))
	{
		BIO_printf(tls->err, "cannot print distinguished name");
	}
	else
	{
		len = (size_t)BIO_get_mem_data(bio, &data);
		*name = malloc(len + 1);
		memcpy(*name, data, len);
		(*name)[len] = '\0';
		ret = 0;
	}
	BIO_vfree(bio);

	return ret;
}

static int tls_validate_issuer_and_subject(tls_t *tls, const char *issuer, const char *subject)
{
	X509	*cert;
	char *peer_issuer = NULL, *peer_subject = NULL;
	int ret = -1;

	if (NULL == (cert = SSL_get_peer_certificate(tls->ssl)))
	{
		BIO_printf(tls->err, "cannot obtain peer certificate");
		goto out;
	}

	if (NULL != issuer)
	{
		if (0 != tls_get_x509_name(tls, X509_get_issuer_name(cert), &peer_issuer))
			goto out;
		if (0 != strcmp(issuer, peer_issuer))
		{
			BIO_printf(tls->err, "invalid certificate issuer %s", peer_issuer);
			goto out;
		}
	}

	if (NULL != subject)
	{
		if (0 != tls_get_x509_name(tls, X509_get_subject_name(cert), &peer_subject))
			goto out;
		if (0 != strcmp(subject, peer_subject))
		{
			BIO_printf(tls->err, "invalid certificate subject %s", peer_subject);
			goto out;
		}
	}
	ret = 0;
out:
	free(peer_issuer);
	free(peer_subject);
	X509_free(cert);
	return ret;
}

#define TLS_MAX_BUF_LEN	2048

static void tls_description(tls_t *tls, char **desc)
{
	X509	*cert;
	char	buf[TLS_MAX_BUF_LEN], *ptr = buf;

	ptr += snprintf(ptr, sizeof(buf), "%s %s", SSL_get_version(tls->ssl), SSL_get_cipher(tls->ssl));

	if ((sizeof(buf) - 1 > (size_t)(ptr - buf)) && NULL != (cert = SSL_get_peer_certificate(tls->ssl)))
	{
		char	*peer_issuer = NULL, *peer_subject = NULL;

		if (0 == tls_get_x509_name(tls, X509_get_issuer_name(cert), &peer_issuer) &&
			0 == tls_get_x509_name(tls, X509_get_subject_name(cert), &peer_subject))
		{
			// ensure buffer length for writing at least ', peer certificate issuer:" " subject:" "'
			if (sizeof(buf) - (size_t)(ptr - buf) > 41)
			{
				snprintf(ptr, sizeof(buf) - (size_t)(ptr - buf),
						", peer certificate issuer:\"%s\" subject:\"%s\"",
						peer_issuer, peer_subject);
			}
		}

		free(peer_issuer);
		free(peer_subject);
		X509_free(cert);
	}

	*desc = strdup(buf);
}

//*****************************************************************************
//                                                                           //
// Function: tls_describe_ciphersuites                                       //
//                                                                           //
// Purpose: write names of enabled OpenSSL ciphersuites into dynamically     //
//          allocated string                                                 //
//                                                                           //
//*****************************************************************************
static void tls_describe_ciphersuites(SSL_CTX_LP ctx, char **desc)
{
#define TLS_CIPHERS_BUF_LEN	8192

	int			i, num;
	size_t			offset = 0;
	STACK_OF(SSL_CIPHER)	*cipher_list;
	char			buf[TLS_CIPHERS_BUF_LEN];

	buf[0] = '\0';
	cipher_list = SSL_CTX_get_ciphers(ctx);
	num = sk_SSL_CIPHER_num(cipher_list);

	for (i = 0; i < num; i++)
	{
		offset += (size_t)snprintf(buf + offset, sizeof(buf) - offset, " %s",
				SSL_CIPHER_get_name(sk_SSL_CIPHER_value(cipher_list, i)));

		if (sizeof(buf) - 2 <= offset)
		{
			const char	*msg = "...(truncated)";

			snprintf(buf + sizeof(buf) - strlen(msg) - 1, strlen(msg) + 1, "%s", msg);
			break;
		}
	}
	*desc = strdup(buf);

#undef TLS_CIPHERS_BUF_LEN
}

static const char	*tls_version(void)
{
	return OpenSSL_version(OPENSSL_VERSION);
}

static const char	*tls_version_static(void)
{
	return OPENSSL_VERSION_TEXT;
}

static int	tls_has_peer_certificate(tls_t *tls)
{
	X509	*cert;

	if (NULL == (cert = SSL_get_peer_certificate(tls->ssl)))
		return 0;

	X509_free(cert);
	return 1;
}

#elif defined(HAVE_GNUTLS)
#	error zabbix_agent2 does not support GnuTLS library. Compile with OpenSSL\
		(configure parameter --with-openssl) or without encryption support.
#else // no crypto library requested, compile without encryption support

typedef void * SSL_CTX_LP;

typedef struct {
} tls_t;

static int tls_init(void)
{
	tls_crypto_init_msg = "encryption support was not compiled in";
	return -1;
}

static void *tls_new_context(const char *ca_file, const char *crl_file, const char *cert_file, const char *key_file,
		const char *cipher, const char *cipher13, char **error)
{
	TLS_UNUSED(ca_file);
	TLS_UNUSED(crl_file);
	TLS_UNUSED(cert_file);
	TLS_UNUSED(key_file);
	TLS_UNUSED(cipher);
	TLS_UNUSED(cipher13);
	*error = strdup("built without OpenSSL");
	return NULL;
}

static void tls_free_context(SSL_CTX_LP ctx)
{
	TLS_UNUSED(ctx);
}

static tls_t *tls_new_client(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key, const char *servername)
{
	TLS_UNUSED(ctx);
	TLS_UNUSED(psk_identity);
	TLS_UNUSED(psk_key);
	TLS_UNUSED(servername);
	return NULL;
}

static tls_t *tls_new_server(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key)
{
	TLS_UNUSED(ctx);
	TLS_UNUSED(psk_identity);
	TLS_UNUSED(psk_key);
	return NULL;
}

static int tls_recv(tls_t *tls, char *buf, int size)
{
	TLS_UNUSED(tls);
	TLS_UNUSED(buf);
	TLS_UNUSED(size);
	return 0;
}

static int tls_send(tls_t *tls, char *buf, int size)
{
	TLS_UNUSED(tls);
	TLS_UNUSED(buf);
	TLS_UNUSED(size);
	return 0;
}

static int tls_connected(tls_t *tls)
{
	TLS_UNUSED(tls);
	return 0;
}

static int tls_write(tls_t *tls, char *buf, int len)
{
	TLS_UNUSED(tls);
	TLS_UNUSED(buf);
	TLS_UNUSED(len);
	return 0;
}

static int tls_read(tls_t *tls, char *buf, int len)
{
	TLS_UNUSED(tls);
	TLS_UNUSED(buf);
	TLS_UNUSED(len);
	return 0;
}

static int tls_handshake(tls_t *tls)
{
	TLS_UNUSED(tls);
	return 0;
}

static int tls_accept(tls_t *tls)
{
	TLS_UNUSED(tls);
	return 0;
}

static size_t tls_error(tls_t *tls, char **buf)
{
	TLS_UNUSED(tls);
	TLS_UNUSED(buf);
	return 0;
}

static int tls_ready(tls_t *tls)
{
	TLS_UNUSED(tls);
	return 0;
}

static int tls_close(tls_t *tls)
{
	TLS_UNUSED(tls);
	return 0;
}

static void tls_free(tls_t *tls)
{
	TLS_UNUSED(tls);
}

static int tls_validate_issuer_and_subject(tls_t *tls, const char *issuer, const char *subject)
{
	TLS_UNUSED(tls);
	TLS_UNUSED(issuer);
	TLS_UNUSED(subject);
	return 0;
}

static void tls_description(tls_t *tls, char **desc)
{
	TLS_UNUSED(tls);
	TLS_UNUSED(desc);
}

static void tls_describe_ciphersuites(SSL_CTX_LP ciphers, char **desc)
{
	TLS_UNUSED(ciphers);
	TLS_UNUSED(desc);
}

static const char	*tls_version(void)
{
	return NULL;
}

static const char	*tls_version_static(void)
{
	return NULL;
}

static int	tls_has_peer_certificate(tls_t *tls)
{
	TLS_UNUSED(tls);
	return 0;
}

#endif

*/
import "C"

import (
	"errors"
	"fmt"
	"net"
	"runtime"
	"time"
	"unsafe"

	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/uri"
)

// TLS initialization
var supported bool      // is TLS compiled in and successfully initialized
var supportedMsg string // reason why TLS is not supported

func Supported() bool {
	return supported
}

func SupportedErrMsg() string {
	return supportedMsg
}

func init() {
	log.Tracef("Calling C function \"tls_init()\"")
	supported = C.tls_init() != -1

	if !supported {
		supportedMsg = C.GoString(C.tls_crypto_init_msg)
	}
}

func describeCiphersuites(context unsafe.Pointer) (desc string) {
	var cDesc *C.char
	log.Tracef("Calling C function \"tls_describe_ciphersuites()\"")
	C.tls_describe_ciphersuites(C.SSL_CTX_LP(context), &cDesc)
	desc = C.GoString(cDesc)
	log.Tracef("Calling C function \"free()\"")
	C.free(unsafe.Pointer(cDesc))
	return
}

type tlsConn struct {
	conn          net.Conn
	tls           unsafe.Pointer
	buf           []byte
	timeout       time.Duration
	shiftDeadline bool
}

func (c *tlsConn) Error() (err error) {
	var cBuf *C.char
	var errmsg string
	log.Tracef("Calling C function \"tls_error()\"")
	if c.tls != nil && 0 != C.tls_error((*C.tls_t)(c.tls), &cBuf) {
		errmsg = C.GoString(cBuf)
		log.Tracef("Calling C function \"free()\"")
		C.free(unsafe.Pointer(cBuf))
	} else {
		errmsg = "unknown openssl error"
	}
	return errors.New(errmsg)
}

func (c *tlsConn) ready() bool {
	log.Tracef("Calling C function \"tls_ready()\"")
	return C.tls_ready((*C.tls_t)(c.tls)) == 1
}

// Note, don't use flushTLS() and recvTLS() concurrently
func (c *tlsConn) flushTLS() (err error) {
	for {
		log.Tracef("Calling C function \"tls_recv()\"")
		if cn := C.tls_recv((*C.tls_t)(c.tls), (*C.char)(unsafe.Pointer(&c.buf[0])), C.int(len(c.buf))); cn > 0 {
			if c.shiftDeadline {
				if err = c.conn.SetWriteDeadline(time.Now().Add(c.timeout)); err != nil {
					return
				}
			}

			if _, err = c.conn.Write(c.buf[:cn]); err != nil {
				return
			}
		} else {
			return
		}
	}
}

// Note, don't use flushTLS() and recvTLS() concurrently
func (c *tlsConn) recvTLS() (err error) {
	var n int
	if c.shiftDeadline {
		if err = c.conn.SetReadDeadline(time.Now().Add(c.timeout)); err != nil {
			return
		}
	}
	if n, err = c.conn.Read(c.buf); err != nil {
		return
	}
	log.Tracef("Calling C function \"tls_send()\"")
	C.tls_send((*C.tls_t)(c.tls), (*C.char)(unsafe.Pointer(&c.buf[0])), C.int(n))
	return
}

func (c *tlsConn) LocalAddr() net.Addr {
	return c.conn.LocalAddr()
}

func (c *tlsConn) RemoteAddr() net.Addr {
	return c.conn.RemoteAddr()
}

func (c *tlsConn) SetDeadline(t time.Time) error {
	return c.conn.SetDeadline(t)
}

func (c *tlsConn) SetReadDeadline(t time.Time) error {
	return c.conn.SetReadDeadline(t)
}

func (c *tlsConn) SetWriteDeadline(t time.Time) error {
	return c.conn.SetWriteDeadline(t)
}

func (c *tlsConn) Close() (err error) {
	log.Tracef("Calling C function \"tls_close()\"")
	cr := C.tls_close((*C.tls_t)(c.tls))
	c.flushTLS()
	c.conn.Close()

	log.Tracef("Calling C function \"tls_free()\"")
	C.tls_free((*C.tls_t)(c.tls))
	c.tls = nil

	if cr < 0 {
		return c.Error()
	}
	return
}

func (c *tlsConn) verifyIssuerSubject(cfg *Config) (err error) {
	if cfg.Connect == ConnCert && (cfg.ServerCertIssuer != "" || cfg.ServerCertSubject != "") {
		var cSubject, cIssuer *C.char
		if cfg.ServerCertIssuer != "" {
			cIssuer = C.CString(cfg.ServerCertIssuer)
			defer func() {
				log.Tracef("Calling C function \"free(cIssuer)\"")
				C.free(unsafe.Pointer(cIssuer))
			}()
		}
		if cfg.ServerCertSubject != "" {
			cSubject = C.CString(cfg.ServerCertSubject)
			defer func() {
				log.Tracef("Calling C function \"free(cSubject)\"")
				C.free(unsafe.Pointer(cSubject))
			}()
		}
		log.Tracef("Calling C function \"tls_validate_issuer_and_subject()\"")
		if 0 != C.tls_validate_issuer_and_subject((*C.tls_t)(c.tls), cIssuer, cSubject) {
			return c.Error()
		}
	}
	return
}

func (c *tlsConn) String() (desc string) {
	var cDesc *C.char
	log.Tracef("Calling C function \"tls_description()\"")
	C.tls_description((*C.tls_t)(c.tls), &cDesc)
	desc = C.GoString(cDesc)
	log.Tracef("Calling C function \"free()\"")
	C.free(unsafe.Pointer(cDesc))
	return
}

// TLS connection client
type Client struct {
	tlsConn
}

func (c *Client) checkConnection() (err error) {
	log.Tracef("Calling C function \"tls_connected()\"")
	if C.tls_connected((*C.tls_t)(c.tls)) == C.int(1) {
		return
	}
	log.Tracef("Calling C function \"tls_connected()\"")
	for C.tls_connected((*C.tls_t)(c.tls)) != C.int(1) {
		log.Tracef("Calling C function \"tls_handshake()\"")
		cRet := C.tls_handshake((*C.tls_t)(c.tls))
		if cRet == 0 {
			break
		}
		if cRet < 0 {
			return c.Error()
		}
		if err = c.flushTLS(); err != nil {
			return
		}
		if err = c.recvTLS(); err != nil {
			return
		}
	}
	err = c.flushTLS()
	return
}

func (c *Client) Write(b []byte) (n int, err error) {
	if err = c.checkConnection(); err != nil {
		return
	}
	log.Tracef("Calling C function \"tls_write()\"")
	cRet := C.tls_write((*C.tls_t)(c.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
	if cRet <= 0 {
		return 0, c.Error()
	}
	if err = c.flushTLS(); err != nil {
		return
	}
	return len(b), nil
}

func (c *Client) Read(b []byte) (n int, err error) {
	for {
		if err = c.checkConnection(); err != nil {
			return
		}
		log.Tracef("Calling C function \"tls_read()\"")
		cRet := C.tls_read((*C.tls_t)(c.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
		if cRet > 0 {
			return int(cRet), nil
		}
		if cRet < 0 {
			return 0, c.Error()
		}
		if err = c.recvTLS(); err != nil {
			return
		}
	}
}

func NewClient(nc net.Conn, cfg *Config, timeout time.Duration, shiftDeadline bool, address string) (conn net.Conn, err error) {
	if !supported {
		return nil, errors.New(SupportedErrMsg())
	}

	if cfg.Connect == ConnUnencrypted {
		return nc, nil
	}

	var cUser, cSecret, cHostname *C.char
	context := defaultContext
	if cfg.Connect == ConnPSK {
		cUser = C.CString(cfg.PSKIdentity)
		cSecret = C.CString(cfg.PSKKey)

		defer func() {
			log.Tracef("Calling C function \"free()\"")
			C.free(unsafe.Pointer(cUser))
			log.Tracef("Calling C function \"free()\"")
			C.free(unsafe.Pointer(cSecret))
		}()
		context = pskContext
	}

	if url, err := uri.New(address, nil); err == nil {
		hostname := url.Host()
		if nil == net.ParseIP(hostname) {
			cHostname = C.CString(hostname)
			defer func() {
				log.Tracef("Calling C function \"free()\"")
				C.free(unsafe.Pointer(cHostname))
			}()
		}
	}

	// for TLS we overwrite the timeoutMode and force it to move on every read or write
	log.Tracef("Calling C function \"tls_new_client()\"")
	c := &Client{
		tlsConn: tlsConn{
			conn:          nc,
			buf:           make([]byte, 4096),
			tls:           unsafe.Pointer(C.tls_new_client(C.SSL_CTX_LP(context), cUser, cSecret, cHostname)),
			timeout:       timeout,
			shiftDeadline: shiftDeadline,
		},
	}
	log.Tracef("Calling C function \"tls_free()\"")
	runtime.SetFinalizer(c, func(c *Client) { C.tls_free((*C.tls_t)(c.tls)) })

	if !c.ready() {
		return nil, c.Error()
	}
	if err = c.checkConnection(); err != nil {
		c.conn.Close()
		return
	}
	if err = c.verifyIssuerSubject(cfg); err != nil {
		c.Close()
		return
	}

	// explicit conversion needed to avoid nested calls to logging
	log.Debugf("connection established using %s", c.String())

	return c, nil
}

// TLS connection server
type Server struct {
	tlsConn
}

func (s *Server) checkConnection() (err error) {
	log.Tracef("Calling C function \"tls_connected()\"")
	if C.tls_connected((*C.tls_t)(s.tls)) == C.int(1) {
		return
	}
	for {
		log.Tracef("Calling C function \"tls_accept()\"")
		cRet := C.tls_accept((*C.tls_t)(s.tls))
		if cRet == 0 {
			break
		}
		if cRet < 0 {
			return s.Error()
		}
		if err = s.flushTLS(); err != nil {
			return
		}
		if err = s.recvTLS(); err != nil {
			return
		}
	}
	err = s.flushTLS()
	return
}

func (s *Server) Write(b []byte) (n int, err error) {
	if err = s.checkConnection(); err != nil {
		return
	}
	log.Tracef("Calling C function \"tls_write()\"")
	cRet := C.tls_write((*C.tls_t)(s.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
	if cRet <= 0 {
		return 0, s.Error()
	}

	return len(b), s.flushTLS()
}

func (s *Server) Read(b []byte) (n int, err error) {
	for {
		if err = s.checkConnection(); err != nil {
			return
		}
		log.Tracef("Calling C function \"tls_read()\"")
		cRet := C.tls_read((*C.tls_t)(s.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
		if cRet > 0 {
			return int(cRet), nil
		}
		if cRet < 0 {
			return 0, s.Error()
		}
		if err = s.recvTLS(); err != nil {
			return
		}
	}
}

func NewServer(nc net.Conn, cfg *Config, b []byte, timeout time.Duration, shiftDeadline bool) (conn net.Conn, err error) {
	if !supported {
		return nil, errors.New(SupportedErrMsg())
	}

	var cUser, cSecret *C.char
	if cfg.Accept&ConnPSK != 0 {
		cUser = C.CString(cfg.PSKIdentity)
		cSecret = C.CString(cfg.PSKKey)

		defer func() {
			log.Tracef("Calling C function \"free()\"")
			C.free(unsafe.Pointer(cUser))
			log.Tracef("Calling C function \"free()\"")
			C.free(unsafe.Pointer(cSecret))
		}()
	}

	context := pskContext
	if cfg.Accept&ConnCert != 0 {
		context = defaultContext
	}

	// for TLS we overwrite the timeoutMode and force it to move on every read or write
	log.Tracef("Calling C function \"tls_new_server()\"")
	s := &Server{
		tlsConn: tlsConn{
			conn:          nc,
			buf:           make([]byte, 4096),
			tls:           unsafe.Pointer(C.tls_new_server(C.SSL_CTX_LP(context), cUser, cSecret)),
			timeout:       timeout,
			shiftDeadline: shiftDeadline,
		},
	}
	log.Tracef("Calling C function \"tls_free()\"")
	runtime.SetFinalizer(s, func(s *Server) { C.tls_free((*C.tls_t)(s.tls)) })

	if !s.ready() {
		return nil, s.Error()
	}

	log.Tracef("Calling C function \"tls_send()\"")
	C.tls_send((*C.tls_t)(s.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))

	if err = s.checkConnection(); err != nil {
		s.conn.Close()
		return
	}

	if C.tls_has_peer_certificate((*C.tls_t)(s.tls)) == 1 {
		if err = s.verifyIssuerSubject(cfg); err != nil {
			s.Close()
			return
		}
	}

	log.Debugf("connection established using %s", s.String())

	return s, nil
}

var pskContext, defaultContext unsafe.Pointer

const (
	ConnUnencrypted = 1 << iota
	ConnPSK
	ConnCert
)

type Config struct {
	Accept            int
	Connect           int
	PSKIdentity       string
	PSKKey            string
	CAFile            string
	CRLFile           string
	CertFile          string
	KeyFile           string
	ServerCertIssuer  string
	ServerCertSubject string
	CipherAll         string
	CipherAll13       string
	CipherPSK         string
	CipherPSK13       string
}

func CopyrightMessage() (message string) {
	log.Tracef("Calling C function \"tls_version()\"")
	version := C.tls_version()
	if version == nil {
		return ""
	}

	log.Tracef("Calling C function \"tls_version_static()\"")
	return fmt.Sprintf("\n\nThis product includes software developed by the OpenSSL Project\n"+
		"for use in the OpenSSL Toolkit (http://www.openssl.org/).\n\n"+
		"Compiled with %s\nRunning with %s\n", C.GoString(C.tls_version_static()), C.GoString(version))
}

func Init(config *Config) (err error) {
	if !supported {
		return errors.New(SupportedErrMsg())
	}
	if pskContext != nil {
		log.Tracef("Calling C function \"tls_free_context()\"")
		C.tls_free_context(C.SSL_CTX_LP(pskContext))
	}
	if defaultContext != nil {
		log.Tracef("Calling C function \"tls_free_context()\"")
		C.tls_free_context(C.SSL_CTX_LP(defaultContext))
	}

	var cErr, cCaFile, cCrlFile, cCertFile, cKeyFile, cCipherCert, cCipherCert13, cCipherPSK, cCipherPSK13,
		cNULL *C.char

	if (config.Accept|config.Connect)&ConnCert != 0 {
		cCaFile = C.CString(config.CAFile)
		cCertFile = C.CString(config.CertFile)
		cKeyFile = C.CString(config.KeyFile)

		defer func() {
			log.Tracef("Calling C function \"free()\"")
			C.free(unsafe.Pointer(cCaFile))
			log.Tracef("Calling C function \"free()\"")
			C.free(unsafe.Pointer(cCertFile))
			log.Tracef("Calling C function \"free()\"")
			C.free(unsafe.Pointer(cKeyFile))
		}()

		if config.CRLFile != "" {
			cCrlFile = C.CString(config.CRLFile)
			defer func() {
				log.Tracef("Calling C function \"free()\"")
				C.free(unsafe.Pointer(cCrlFile))
			}()
		}

		if config.CipherAll != "" {
			cCipherCert = C.CString(config.CipherAll)
			defer func() {
				log.Tracef("Calling C function \"free()\"")
				C.free(unsafe.Pointer(cCipherCert))
			}()
		}

		if config.CipherAll13 != "" {
			cCipherCert13 = C.CString(config.CipherAll13)
			defer func() {
				log.Tracef("Calling C function \"free()\"")
				C.free(unsafe.Pointer(cCipherCert13))
			}()
		}
	}

	if (config.Accept|config.Connect)&ConnPSK != 0 {
		if config.CipherPSK != "" {
			cCipherPSK = C.CString(config.CipherPSK)
			defer func() {
				log.Tracef("Calling C function \"free()\"")
				C.free(unsafe.Pointer(cCipherPSK))
			}()
		}
		if config.CipherPSK13 != "" {
			cCipherPSK13 = C.CString(config.CipherPSK13)
			defer func() {
				log.Tracef("Calling C function \"free()\"")
				C.free(unsafe.Pointer(cCipherPSK13))
			}()
		}
	}

	log.Tracef("Calling C function \"tls_new_context()\"")
	defaultContext = C.tls_new_context(cCaFile, cCrlFile, cCertFile, cKeyFile, cCipherCert, cCipherCert13, &cErr)

	if defaultContext == nil {
		err = fmt.Errorf("cannot initialize default TLS context: %s", C.GoString(cErr))
		log.Tracef("Calling C function \"free()\"")
		C.free(unsafe.Pointer(cErr))
		return
	}

	log.Tracef("Calling C function \"tls_new_context()\"")
	pskContext = C.tls_new_context(cNULL, cNULL, cNULL, cNULL, cCipherPSK, cCipherPSK13, &cErr)

	if pskContext == nil {
		err = fmt.Errorf("cannot initialize PSK TLS context: %s", C.GoString(cErr))
		log.Tracef("Calling C function \"free()\"")
		C.free(unsafe.Pointer(cErr))
		return
	}

	log.Tracef("Calling C function \"tls_version()\"")
	log.Infof("OpenSSL library (%s) initialized", C.GoString(C.tls_version()))
	log.Debugf("default context ciphersuites:%s", describeCiphersuites(defaultContext))
	log.Debugf("psk context ciphersuites:%s", describeCiphersuites(pskContext))

	return
}
