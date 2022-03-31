#!/bin/bash
function show_help
{
	echo "$(basename $0) [--cleanup]"
	echo ""
	echo "Extract new strings from php files and regenerate po files."
	echo ""
}

function run
{
	local cleanup=false

	while [[ $# -gt 0 ]]; do
		case "$1" in
			--help|-h|help)
				show_help
				exit 0
			;;

			--cleanup)
				cleanup=true
			;;

			*)
				die "unsupported parameter \"$1\"!"
			;;
		esac
		shift
	done

	local workdir=$(realpath $(dirname $0)/..)
	[[ -d "$workdir" ]] ||
		die "cannot find \"$workdir\" working directory!"
	cd "$workdir" || die

	local pofiles=locale/*/LC_MESSAGES/frontend.po
	local curpot=locale/en/LC_MESSAGES/frontend.pot
	local oldpot=locale/old.pot
	local newpot=locale/new.pot
	local cur_msgids=locale/cur_msgids
	local new_msgids=locale/new_msgids
	local potlist=locale/POTFILES.in

	# handle nonexistent potfile
	mkdir -p $(dirname $curpot)
	touch $curpot

	# use xgettext on all php files
	find . -type f -name '*.php' | sort -d -f > $potlist

	# keyword "_n" is Zabbix frontend plural function
	# keyword "_s" is Zabbix frontend placeholder function
	# keyword "_x" is Zabbix frontend context function
	# keyword "_xs" is Zabbix frontend context function
	xgettext --files-from=$potlist \
		--from-code=UTF-8 \
		--output=$newpot \
		--copyright-holder="Zabbix SIA" \
		--no-wrap --sort-output \
		--add-comments="GETTEXT:" \
		--keyword=_n:1,2 \
		--keyword=_s \
		--keyword=_x:1,2c \
		--keyword=_xs:1,2c \
		--keyword=_xn:1,2,4c ||
	die

	rm $potlist

	sed -i 's/^#, php-format/#, c-format/' $newpot || die


	if $cleanup; then
		mv $newpot $curpot
		local num_msgids_not_removed=-1
	else
		# compute the number of strings that were repoved from php, but we still are keeping in po
		grep msgid $curpot | sort > $cur_msgids
		grep msgid $newpot | sort > $new_msgids
		local num_msgids_not_removed=$(diff $cur_msgids $new_msgids | grep '^<' | wc -l)
		[[ "$num_msgids_not_removed" =~ ^[0-9]+$ ]] ||
			die "cannot compute number of msgids that were not removed!"
		rm $cur_msgids $new_msgids

		# make sure no string are removed or pontoon will die!
		mv $curpot $oldpot
		msgcat --use-first --no-wrap --sort-output $oldpot $newpot -o $curpot
		rm $oldpot $newpot
	fi


	local translation=''
	for translation in $pofiles; do
		echo -n "$translation" | cut -d/ -f1
		# fuzzy matching provides all kinds of interesting results, for example,
		# "NTLM authentication" is translated as "LDAP-Authentifizierung" - thus
		# it is disabled
		msgmerge --no-fuzzy-matching --no-wrap --update --backup=off "$translation" "$curpot"

		# dropping obsolete strings
		msgattrib --no-obsolete --no-wrap --sort-output $translation -o $translation
	done

	for translation in $pofiles; do
		echo -ne "$translation\t"
		# setting output file to /dev/null so that unneeded messages.mo file
		# is not created
		msgfmt --use-fuzzy -c --statistics -o /dev/null $translation
	done

	if [[ $num_msgids_not_removed -gt 0 ]]; then
		echo ""
		echo "!! $num_msgids_not_removed strings are no longer present in php files, but have been preserved in po files !!"
		echo ""
	fi
}

function die
{
	[[ -n "$@" ]] && >&2 echo -e "$@"
	exit 1
}

run "$@"
