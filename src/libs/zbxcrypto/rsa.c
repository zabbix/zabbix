/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxcrypto.h"
#include "zbxstr.h"

#if defined(HAVE_OPENSSL)
#include <openssl/evp.h>
#include <openssl/rsa.h>
#include <openssl/err.h>
#include <openssl/bio.h>
#include <openssl/pem.h>
#elif defined(HAVE_GNUTLS)
#include <gnutls/gnutls.h>
#include <gnutls/crypto.h>
#include <gnutls/x509.h>
#include <gnutls/abstract.h>
#endif

#if defined(HAVE_OPENSSL) || defined(HAVE_GNUTLS)
static int	pem_insert_newlines(char **key, size_t header_len)
{
	char	*end_ptr;
	size_t	offset;

	offset = header_len - 1;

	if ('\n' != (*key)[offset + 1])
		zbx_replace_string(key, offset, &offset, "-\n");

	if (NULL == (end_ptr = strstr(*key, "-----END")))
		return FAIL;

	if ('\n' != *(end_ptr - 1))
	{
		offset = end_ptr - *key;
		zbx_replace_string(key, offset, &offset, "\n-");
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: receive private key in PEM container with arbitrary newlines,     *
 *          validate if its header corresponds to PKCS#1 or PKCS#8 format,    *
 *          the if required - add newlines to header and footer (so OpenSSL   *
 *          or GnuTLS would be able to parse it)                              *
 *                                                                            *
 * Parameters:                                                                *
 *     key         - [OUT/IN] the private key in ASCII format                 *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - key was successfully formatted                               *
 *     FAIL    - an error occurred                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_format_pem_pkey(char **key)
{
#define	PEM_HEADER_PKCS1	"-----BEGIN RSA PRIVATE KEY-----"
#define	PEM_HEADER_PKCS8	"-----BEGIN PRIVATE KEY-----"
	if (0 == strncmp(*key, PEM_HEADER_PKCS8, ZBX_CONST_STRLEN(PEM_HEADER_PKCS8)))
	{
		if (SUCCEED == pem_insert_newlines(key, ZBX_CONST_STRLEN(PEM_HEADER_PKCS8)))
		{
			return SUCCEED;
		}
	}
	else if (0 == strncmp(*key, PEM_HEADER_PKCS1, ZBX_CONST_STRLEN(PEM_HEADER_PKCS1)))
	{
		if (SUCCEED == pem_insert_newlines(key, ZBX_CONST_STRLEN(PEM_HEADER_PKCS1)))
		{
			return SUCCEED;
		}
	}

	return FAIL;
#undef	PEM_HEADER_PKCS8
#undef	PEM_HEADER_PKCS1
}
#endif

#if defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Purpose: create RS256 signature for given data                             *
 *                                                                            *
 * Parameters:                                                                *
 *     key         - [IN] private key in a PEM container (PKCS#1 or PKCS#8)   *
 *     data        - [IN] data to sign                                        *
 *     output      - [OUT] dynamically allocated memory with signature        *
 *     output_len  - [OUT] length of a signature (bytes)                      *
 *     error       - [OUT] dynamically allocated memory with error message    *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - signature was generated successfully                         *
 *     FAIL    - an error occurred                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_rs256_sign(char *key, char *data, unsigned char **output, size_t *output_len, char **error)
{
	int		ret = SUCCEED;
	BIO		*bio = NULL;
	EVP_PKEY	*pkey = NULL;
	EVP_MD_CTX	*mdctx = NULL;
	unsigned char	*sig;
	size_t		sign_len;

	bio = BIO_new_mem_buf(key, -1);

	if (NULL == (pkey = PEM_read_bio_PrivateKey(bio, NULL, NULL, NULL)) || NULL == (mdctx = EVP_MD_CTX_create()))
	{
		*error = zbx_strdup(NULL, "failed to import private key");
		ret = FAIL;
		goto out;
	}

	if (1 != EVP_DigestSignInit(mdctx, NULL, EVP_sha256(), NULL, pkey))
	{
		*error = zbx_strdup(NULL, "EVP_DigestSignInit failed");
		ret = FAIL;
		goto out;
	}

	if (0 >= EVP_DigestSign(mdctx, NULL, &sign_len, NULL, 0))
	{
		*error = zbx_strdup(NULL, "failed to retrieve length of a signature");
		ret = FAIL;
		goto out;
	}

	sig = OPENSSL_malloc(sign_len);

	if (0 >= EVP_DigestSign(mdctx, sig, &sign_len, (const unsigned char *)data, strlen(data)))
	{
		*error = zbx_strdup(NULL, "signing failed");
		ret = FAIL;
	}
	else
	{
		*output = (unsigned char *)zbx_malloc(NULL, sign_len);
		*output_len = (size_t)sign_len;
		memcpy(*output, sig, sign_len);
	}
out:
	BIO_free(bio);
	EVP_PKEY_free(pkey);
	EVP_MD_CTX_destroy(mdctx);
	OPENSSL_free(sig);

	return ret;
}
#elif defined(HAVE_GNUTLS)
int	zbx_rs256_sign(char *key, char *data, unsigned char **output, size_t *output_len, char **error)
{
	gnutls_x509_privkey_t	x509key;
	gnutls_privkey_t	privkey;
	gnutls_datum_t		keyd, bodyd, sigd;
	int			ret = SUCCEED;

	keyd.data = (unsigned char *)key;
	keyd.size = strlen(key);
	bodyd.data = (unsigned char *)data;
	bodyd.size = strlen(data);

	if (GNUTLS_E_SUCCESS != gnutls_x509_privkey_init(&x509key))
	{
		*error = zbx_strdup(NULL, "failed to initialize storage for private key");
		return FAIL;
	}

	if (GNUTLS_E_SUCCESS != gnutls_x509_privkey_import(x509key, &keyd, GNUTLS_X509_FMT_PEM)
			|| GNUTLS_E_SUCCESS != gnutls_privkey_init(&privkey))
	{
		*error = zbx_strdup(NULL, "failed to import private key");
		ret = FAIL;
		goto out2;
	}

	if (GNUTLS_E_SUCCESS != gnutls_privkey_import_x509(privkey, x509key, 0))
	{
		*error = zbx_strdup(NULL, "failed to import private key");
		ret = FAIL;
		goto out1;
	}

	if (GNUTLS_E_SUCCESS != gnutls_privkey_sign_data(privkey, GNUTLS_DIG_SHA256, 0, &bodyd, &sigd))
	{
		*error = zbx_strdup(NULL, "signing failed");
		ret = FAIL;
		goto out1;
	}
	else
	{
		*output = (unsigned char *)zbx_malloc(NULL, sigd.size);
		*output_len = (size_t)sigd.size;
		memcpy(*output, sigd.data, sigd.size);
		gnutls_free(sigd.data);
	}
out1:
	gnutls_privkey_deinit(privkey);
out2:
	gnutls_x509_privkey_deinit(x509key);

	return ret;
}
#endif
