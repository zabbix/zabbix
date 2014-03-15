#ifndef _RSM_H_
#define _RSM_H_

#include <openssl/evp.h>

int	rsm_ssl_init();
int	get_random(void *data, int bytes);
int	encrypt(const EVP_CIPHER *cipher, unsigned char *plaintext, int plaintext_len, unsigned char *key,
		unsigned char *iv, unsigned char *ciphertext, int *ciphertext_len, char *err, size_t err_size);
int	encrypt_cleartext(const char *passphrase, int passphrase_len, const char *secretkey_enc_b64,
		int secretkey_enc_b64_len, const char *secretkey_salt_b64, int secretkey_salt_b64_len,
		const char *cleartext, int cleartext_len, char **cleartext_b64, char **cleartext_salt_b64,
		char *err, size_t err_size);
int	decrypt_ciphertext(const char *passphrase, int passphrase_len, const char *secretkey_enc_b64,
		int secretkey_enc_b64_len, const char *secretkey_salt_b64, int secretkey_salt_b64_len,
		const char *cleartext_enc_b64, int cleartext_enc_b64_len, const char *cleartext_salt_b64,
		int cleartext_salt_b64_len, char **cleartext, char *err, size_t err_size);

#endif /* _RSM_H_ */
