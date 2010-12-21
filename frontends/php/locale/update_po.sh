#!/bin/bash

echo "Generating translation template..."

# xgettext will be used on all php files
find .. -type f -name '*.php' > POTFILES.in

xgettext --files-from=POTFILES.in --from-code=UTF-8 \
--output=frontend.pot --copyright-holder="SIA Zabbix" --no-wrap \
--add-comments="GETTEXT:" --keyword=_n:1,2

#--sort-output
#--sort-by-file

echo "Merging new strings in po files..."

for i in */LC_MESSAGES/frontend.po; do
	echo -n "$i" | cut -d/ -f1
	# fuzzy matching provides all kinds of interesting results, for example,
	# "NTLM authentication" is translated as "LDAP-Authentifizierung" - thus
	# it is disabled
	msgmerge --no-fuzzy-matching --no-wrap --update --backup=off "$i" frontend.pot
done

for i in */LC_MESSAGES/frontend.po; do
	echo -ne "$i\t"
	# setting output file to /dev/null so that unneeded messages.mo file
	# is not created
	msgfmt -c --statistics -o /dev/null $i
done
