/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

package tls

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../../include

#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <unistd.h>
#include <ctype.h>
#include "config.h"

#ifdef HAVE_OPENSSL
#include <openssl/ssl.h>
#include <openssl/err.h>
#include <openssl/bio.h>

#define TLS_EX_DATA_ERRBIO		0
#define TLS_EX_DATA_IDENTITY	1
#define TLS_EX_DATA_KEY			2

typedef SSL_CTX * SSL_CTX_LP;

typedef struct {
	SSL *ssl;
	BIO *in;
	BIO *out;
	BIO *err;
	int ready;
	char *psk_identity;
	char *psk_key;
} tls_t, *tls_lp_t;

static int tls_init()
{
	ERR_load_crypto_strings();
	ERR_load_SSL_strings();
	OpenSSL_add_all_algorithms();
	SSL_library_init();
	return 0;
}

static unsigned int tls_psk_client_cb(SSL *ssl, const char *hint, char *identity,
	unsigned int max_identity_len, unsigned char *psk, unsigned int max_psk_len)
{
	size_t	sz;
	const char	*psk_identity, *psk_key;
	BIO	*err;
	unsigned char *key;
	long key_len;

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
	if (key == NULL) {
		BIO_printf(err, "invalid PSK key");
		return 0;
	}

	if (key_len > (long)max_psk_len) {
		BIO_printf(err, "PSK key is too large");
		OPENSSL_free(key);
		return 0;
	}

	memcpy(psk, key, key_len);
	OPENSSL_free(key);
	return key_len;
}

static unsigned int tls_psk_server_cb(SSL *ssl, const char *identity, unsigned char *psk, unsigned int max_psk_len)
{
	const char	*psk_identity, *psk_key;
	BIO	*err;
	unsigned char *key;
	long key_len;

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
	if (key == NULL) {
		BIO_printf(err, "invalid PSK key");
		return 0;
	}

	if (key_len > (long)max_psk_len) {
		BIO_printf(err, "PSK key is too large");
		return 0;
	}

	memcpy(psk, key, key_len);
	OPENSSL_free(key);
	return key_len;
}

#define TLS_1_3_CIPHERSUITES "TLS_CHACHA20_POLY1305_SHA256:TLS_AES_128_GCM_SHA256"
#define TLS_CIPHERS "RSA+aRSA+AES128:kPSK+AES128"

static void *tls_new_context(const char *ca_file, const char *cert_file, const char *key_file, char **error)
{
	SSL_CTX	*ctx;
	int		ret = -1;

	if (NULL == (ctx = SSL_CTX_new(TLS_method())))
		goto out;

	if (1 != SSL_CTX_set_min_proto_version(ctx, TLS1_2_VERSION))
		goto out;

	if (NULL != ca_file)
	{
		if (1 != SSL_CTX_load_verify_locations(ctx, ca_file, NULL))
			goto out;
		SSL_CTX_set_verify(ctx, SSL_VERIFY_PEER | SSL_VERIFY_FAIL_IF_NO_PEER_CERT, NULL);
	}

	if (NULL != cert_file && 1 != SSL_CTX_use_certificate_chain_file(ctx, cert_file))
		goto out;

	if (NULL != key_file && 1 != SSL_CTX_use_PrivateKey_file(ctx, key_file, SSL_FILETYPE_PEM))
		goto out;

	SSL_CTX_set_mode(ctx, SSL_MODE_AUTO_RETRY);
	SSL_CTX_set_options(ctx, SSL_OP_CIPHER_SERVER_PREFERENCE | SSL_OP_NO_TICKET);
	SSL_CTX_clear_options(ctx, SSL_OP_LEGACY_SERVER_CONNECT);
	SSL_CTX_set_session_cache_mode(ctx, SSL_SESS_CACHE_OFF);

#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	// OpenSSL 1.1.1
	if (1 != SSL_CTX_set_ciphersuites(ctx, TLS_1_3_CIPHERSUITES))
		goto out;
#endif
	if (0 == SSL_CTX_set_cipher_list(ctx, TLS_CIPHERS))
		goto out;

	ret = 0;
out:
	if (-1 == ret)
	{
		int	sz;
		BIO	*err;

		err = BIO_new(BIO_s_mem());
		BIO_set_nbio(err, 1);
		ERR_print_errors(err);

		sz = BIO_ctrl_pending(err);
		if (sz != 0)
		{
			*error = malloc(sz + 1);
			BIO_read(err, *error, sz);
			(*error)[sz] = '\0';
		}
		else
			*error = strdup("unknown openssl error");
		BIO_free(err);
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
	int		ret;

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

static tls_t *tls_new_client(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key)
{
	tls_t	*tls;
	int		ret;

	if (0 == tls_new(ctx, psk_identity, psk_key, &tls))
	{
		if (psk_identity != NULL && psk_key != NULL)
			SSL_set_psk_client_callback(tls->ssl, tls_psk_client_cb);

		SSL_set_connect_state(tls->ssl);
		if (1 == (ret = SSL_connect(tls->ssl)) || SSL_ERROR_WANT_READ == SSL_get_error(tls->ssl, ret))
			tls->ready = 1;
	}
	return tls;
}

static tls_t *tls_new_server(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key)
{
	tls_t	*tls;
	int		ret;

	if (0 == tls_new(ctx, psk_identity, psk_key, &tls))
	{
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	// OpenSSL 1.1.1 or newer, or LibreSSL
		if (1 != SSL_set_session_id_context(tls->ssl, "Zbx", sizeof("Zbx") - 1))
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
		ERR_print_errors(tls->err);
		sz = BIO_ctrl_pending(tls->err);
	}
	if (sz != 0)
	{
		*buf = malloc(sz + 1);
		BIO_read(tls->err, *buf, sz);
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
	if (0 > (ret = SSL_shutdown(tls->ssl)) && SSL_ERROR_WANT_READ == SSL_get_error(tls->ssl, ret))
		return 0;
	return ret;
}

static void tls_free(tls_t *tls)
{
	if (NULL != tls->ssl)
		SSL_free(tls->ssl);
	if (NULL != tls->err)
		BIO_free(tls->err);
	if (NULL != tls->psk_identity)
		free(tls->psk_identity);
	if (NULL != tls->psk_key)
		free(tls->psk_key);
	free(tls);
}

#else // HAVE_OPENSSL 0

typedef void * SSL_CTX_LP;

typedef struct {
} tls_t, *tls_lp_t;

static int tls_init()
{
	return -1;
}

static void *tls_new_context(const char *ca_file, const char *cert_file, const char *key_file, char **error)
{
	*error = strdup("built without OpenSSL");
	return NULL;
}

static void tls_free_context(SSL_CTX_LP ctx)
{
}

static tls_t *tls_new_client(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key)
{
	return NULL;
}

static tls_t *tls_new_server(SSL_CTX_LP ctx, const char *psk_identity, const char *psk_key)
{
	return NULL;
}

static int tls_recv(tls_t *tls, char *buf, int size)
{
	return 0;
}

static int tls_send(tls_t *tls, char *buf, int size)
{
	return 0;
}

static int tls_connected(tls_t *tls)
{
	return 0;
}

static int tls_write(tls_t *tls, char *buf, int len)
{
	return 0;
}

static int tls_read(tls_t *tls, char *buf, int len)
{
	return 0;
}

static int tls_handshake(tls_t *tls)
{
	return 0;
}

static int tls_accept(tls_t *tls)
{
	return 0;
}

static size_t tls_error(tls_t *tls, char **buf)
{
	return 0;
}

static int tls_ready(tls_t *tls)
{
	return 0;
}

static int tls_close(tls_t *tls)
{
	return 0;
}

static void tls_free(tls_t *tls)
{
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
)

type tlsConn struct {
	conn    net.Conn
	tls     unsafe.Pointer
	buf     []byte
	timeout time.Duration
}

func (c *tlsConn) Error() (err error) {
	var cBuf *C.char
	var errmsg string
	if c.tls != nil && 0 != C.tls_error(C.tls_lp_t(c.tls), &cBuf) {
		errmsg = C.GoString(cBuf)
		C.free(unsafe.Pointer(cBuf))
	} else {
		errmsg = "unknown openssl error"
	}
	return errors.New(errmsg)
}

func (c *tlsConn) ready() bool {
	return C.tls_ready(C.tls_lp_t(c.tls)) == 1
}

// Note, don't use flushTLS() and recvTLS() concurrently
func (c *tlsConn) flushTLS() (err error) {
	for {
		if cn := C.tls_recv(C.tls_lp_t(c.tls), (*C.char)(unsafe.Pointer(&c.buf[0])), C.int(len(c.buf))); cn > 0 {
			// TODO: remove
			fmt.Println("->server", cn, c.buf[:5])
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
	if err = c.conn.SetDeadline(time.Now().Add(c.timeout)); err != nil {
		return
	}
	if n, err = c.conn.Read(c.buf); err != nil {
		return
	}
	// TODO: remove
	fmt.Println("->openssl", n, c.buf[:5])
	C.tls_send(C.tls_lp_t(c.tls), (*C.char)(unsafe.Pointer(&c.buf[0])), C.int(n))
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
	cr := C.tls_close(C.tls_lp_t(c.tls))
	c.conn.Close()
	c.tls = nil
	if cr < 0 {
		return c.Error()
	}
	return
}

// TLS connection client
type Client struct {
	tlsConn
}

func (c *Client) checkConnection() (err error) {
	if C.tls_connected(C.tls_lp_t(c.tls)) == C.int(1) {
		return
	}
	for C.tls_connected(C.tls_lp_t(c.tls)) != C.int(1) {
		cRet := C.tls_handshake(C.tls_lp_t(c.tls))
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
	cRet := C.tls_write(C.tls_lp_t(c.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
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
		cRet := C.tls_read(C.tls_lp_t(c.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
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

// NewClient(connection, tlsConfig, timeoutDuration)
func NewClient(nc net.Conn, args ...interface{}) (conn net.Conn, err error) {
	if len(args) == 0 || args[0] == nil {
		return nc, nil
	}
	if !supported {
		return nil, errors.New("built without TLS support")
	}
	var cfg *Config
	var ok bool
	if cfg, ok = args[0].(*Config); !ok {
		return nil, fmt.Errorf("invalid configuration parameter of type %T", args)
	}
	if cfg.Connect == ConnUnencrypted {
		return nc, nil
	}

	var cUser, cSecret *C.char
	context := defaultContext
	if cfg.Connect == ConnPSK {
		cUser = C.CString(cfg.PSKIdentity)
		cSecret = C.CString(cfg.PSKKey)

		defer func() {
			C.free(unsafe.Pointer(cUser))
			C.free(unsafe.Pointer(cSecret))
		}()
		context = pskContext
	}

	var timeout time.Duration
	if len(args) > 1 {
		if timeout, ok = args[1].(time.Duration); !ok {
			return nil, fmt.Errorf("invalid timeout parameter of type %T", args)
		}
	} else {
		timeout = 3 * time.Second
	}

	c := &Client{
		tlsConn: tlsConn{
			conn:    nc,
			buf:     make([]byte, 4096),
			tls:     unsafe.Pointer(C.tls_new_client(C.SSL_CTX_LP(context), cUser, cSecret)),
			timeout: timeout,
		},
	}
	runtime.SetFinalizer(c, func(c *Client) { C.tls_free(C.tls_lp_t(c.tls)) })

	if !c.ready() {
		return nil, c.Error()
	}
	if err = c.checkConnection(); err != nil {
		c.conn.Close()
		return
	}
	return c, nil
}

// TLS connection server
type Server struct {
	tlsConn
}

func (s *Server) checkConnection() (err error) {
	if C.tls_connected(C.tls_lp_t(s.tls)) == C.int(1) {
		return
	}
	for {
		cRet := C.tls_accept(C.tls_lp_t(s.tls))
		if cRet == 0 {
			return
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
}

func (s *Server) Write(b []byte) (n int, err error) {
	if err = s.checkConnection(); err != nil {
		return
	}
	cRet := C.tls_write(C.tls_lp_t(s.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
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
		cRet := C.tls_read(C.tls_lp_t(s.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
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

// NewServer(connection, tlsConfig, pendingbufer, timeoutSeconds)
func NewServer(nc net.Conn, args ...interface{}) (conn net.Conn, err error) {
	if len(args) == 0 || args[0] == nil {
		return nc, nil
	}
	if !supported {
		return nil, errors.New("built without TLS support")
	}
	var cfg *Config
	var ok bool
	if cfg, ok = args[0].(*Config); !ok {
		return nil, fmt.Errorf("invalid configuration parameter of type %T", args)
	}

	if cfg.Accept == ConnUnencrypted {
		return nc, nil
	}

	var cUser, cSecret *C.char
	if cfg.Accept&ConnPSK != 0 {
		cUser = C.CString(cfg.PSKIdentity)
		cSecret = C.CString(cfg.PSKKey)

		defer func() {
			C.free(unsafe.Pointer(cUser))
			C.free(unsafe.Pointer(cSecret))
		}()
	}

	context := pskContext
	if cfg.Accept&ConnCert != 0 {
		context = defaultContext
	}

	var timeout time.Duration
	if len(args) > 2 {
		if timeout, ok = args[2].(time.Duration); !ok {
			return nil, fmt.Errorf("invalid timeout parameter of type %T", args)
		}
	} else {
		timeout = 3 * time.Second
	}

	s := &Server{
		tlsConn: tlsConn{
			conn:    nc,
			buf:     make([]byte, 4096),
			tls:     unsafe.Pointer(C.tls_new_server(C.SSL_CTX_LP(context), cUser, cSecret)),
			timeout: timeout,
		},
	}
	runtime.SetFinalizer(s, func(s *Server) { C.tls_free(C.tls_lp_t(s.tls)) })

	if !s.ready() {
		return nil, s.Error()
	}

	if len(args) > 1 {
		if b, ok := args[1].([]byte); !ok {
			return nil, fmt.Errorf("invalid pending buffer parameter of type %T", args)
		} else {
			C.tls_send(C.tls_lp_t(s.tls), (*C.char)(unsafe.Pointer(&b[0])), C.int(len(b)))
		}
	}

	if err = s.checkConnection(); err != nil {
		s.conn.Close()
		return
	}

	return s, nil
}

var supported bool
var pskContext, defaultContext unsafe.Pointer

const (
	ConnUnencrypted = 1 << iota
	ConnPSK
	ConnCert
)

type Config struct {
	Accept      int
	Connect     int
	PSKIdentity string
	PSKKey      string
	CAFile      string
	CertFile    string
	KeyFile     string
}

func Supported() bool {
	return supported
}
func Init(config *Config) (err error) {
	if !supported {
		return errors.New("built without TLS support")
	}
	if pskContext != nil {
		C.tls_free_context(C.SSL_CTX_LP(pskContext))
	}
	if defaultContext != nil {
		C.tls_free_context(C.SSL_CTX_LP(defaultContext))
	}

	var cErr, cCaFile, cCertFile, cKeyFile, cNULL *C.char
	if (config.Accept|config.Connect)&ConnCert != 0 {
		cCaFile = C.CString(config.CAFile)
		cCertFile = C.CString(config.CertFile)
		cKeyFile = C.CString(config.KeyFile)

		defer func() {
			C.free(unsafe.Pointer(cCaFile))
			C.free(unsafe.Pointer(cCertFile))
			C.free(unsafe.Pointer(cKeyFile))
		}()
	}

	if defaultContext = unsafe.Pointer(C.tls_new_context(cCaFile, cCertFile, cKeyFile, &cErr)); defaultContext == nil {
		err = fmt.Errorf("cannot initialize global TLS context: %s", C.GoString(cErr))
		C.free(unsafe.Pointer(cErr))
	}
	if pskContext = unsafe.Pointer(C.tls_new_context(cNULL, cNULL, cNULL, &cErr)); pskContext == nil {
		err = fmt.Errorf("cannot initialize PSK TLS context: %s", C.GoString(cErr))
		C.free(unsafe.Pointer(cErr))
	}
	return err
}

func init() {
	supported = C.tls_init() != -1
}
