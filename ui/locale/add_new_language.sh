#!/bin/bash

function run
{
	local workdir=$(realpath $(dirname $0))
	local potfile=${workdir}/en/LC_MESSAGES/frontend.pot

	[[ -n "$1" ]] ||
		die "specify language code!"

	local pofile=${workdir}/$1/LC_MESSAGES/frontend.po
	[[ -f $pofile  ]] &&
		die "$pofile already exists"

	if [[ -f $potfile ]]; then
		mkdir -p $(dirname $pofile)
		msginit --no-translator --no-wrap --locale=$1 --input=$potfile -o $pofile || die
		git add $pofile || die
	else
		die "po template $potfile missing"
	fi
}

function die
{
	[[ -n "$@" ]] && >&2 echo -e "$@"
	exit 1
}

run "$@"
