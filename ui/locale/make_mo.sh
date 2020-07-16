#!/bin/bash

while read pofile; do
        msgfmt --use-fuzzy -c -o ${pofile%po}mo $pofile || exit $?
done < <(find $(dirname $0) -type f -name '*.po')
