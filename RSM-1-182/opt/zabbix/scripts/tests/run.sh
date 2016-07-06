#!/bin/bash

TLD=$1

if [ -z "$TLD" ]; then
	echo "usage: $0 <TLD>"
	exit 1
fi

./t.epp-results.pl --tld $TLD --from $(date +%s -d '1 hour ago') --till $(date +%s -d '51 minutes ago') || exit 1
../get-results.pl --tld $TLD --service tcp-dns-rtt || exit 1
