/*
hmac_sha256.h
Originally written by https://github.com/h5p9sl
*/

#ifndef _HMAC_SHA256_H_
#define _HMAC_SHA256_H_

#include <stddef.h>

size_t	hmac_sha256(const void* key, const size_t keylen, const void* data, const size_t datalen, void* out,
		const size_t outlen);

#endif
