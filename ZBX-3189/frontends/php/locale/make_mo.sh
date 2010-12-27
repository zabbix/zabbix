#!/bin/bash

while read pofile; do
        msgfmt -c -o ${pofile%po}mo $pofile
done < <(find $(dirname $0) -type f ! -wholename '*/.svn*' -name '*.po')
