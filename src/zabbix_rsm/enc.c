#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>

#include "common.h"
#include "rsm.h"

const char      *progname = NULL;
const char	title_message[] = "Zabbix encrypter";
const char	*help_message[] = {NULL};
const char	usage_message[] = "<secretkey_enc_b64> <secretkey_salt_b64>\n"
		"Encrypt sensitive data using secret key.\n"
		"Parameters:\n"
		"    <secretkey_enc_b64>         secret key, encrypted with passphrase, base64-encoded\n"
		"    <secretkey_salt_b64>        secret key salt, base64-encoded\n"
		"Optional:\n"
		"    <noprompt>                  do not print prompts when requesting passphrase and sensitive data";

int	main(int argc, char *argv[])
{
	const char	*secretkey_enc_b64, *secretkey_salt_b64, *prompt = 0;
	char		*sensdata_enc_b64 = NULL, *sensdata_salt_b64 = NULL, passphrase[128],
			sensdata[RSM_EPP_SENSDATA_MAX], err[128];

	progname = get_program_name(argv[0]);

	if (argc < 3)
	{
		usage();
		exit(1);
	}

	if (3 == argc || 0 != strcmp("noprompt", argv[3]))
		prompt = "Enter EPP passphrase: ";
	if (SUCCEED != zbx_read_stdin(prompt, passphrase, sizeof(passphrase), err, sizeof(err)))
	{
		fprintf(stderr, "cannot get EPP passphrase: %s\n", err);
		goto out;
	}

	if (3 == argc || 0 != strcmp("noprompt", argv[3]))
		prompt = "Enter EPP sensitive data to encrypt: ";
	if (SUCCEED != zbx_read_stdin(prompt, sensdata, sizeof(sensdata), err, sizeof(err)))
	{
		fprintf(stderr, "cannot get EPP sensitive data: %s\n", err);
		goto out;
	}

	secretkey_enc_b64 = argv[1];
	secretkey_salt_b64 = argv[2];

	/* initialize the library */
	if (SUCCEED != rsm_ssl_init())
	{
		fprintf(stderr, "cannot initialize SSL library\n");
		goto out;
	}

	if (SUCCEED != encrypt_cleartext(passphrase, strlen(passphrase), secretkey_enc_b64, strlen(secretkey_enc_b64),
			secretkey_salt_b64, strlen(secretkey_salt_b64), sensdata, strlen(sensdata), &sensdata_enc_b64,
			&sensdata_salt_b64, err, sizeof(err)))
	{
		fprintf(stderr, "cannot encrypt sensitive data: %s\n", err);
		goto out;
	}

	printf("%s|%s\n", sensdata_enc_b64, sensdata_salt_b64);
out:
	zbx_free(sensdata_enc_b64);
	zbx_free(sensdata_salt_b64);

	return 0;
}
