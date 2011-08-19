#!/bin/bash

./bootstrap.sh
./configure
make dbschema
debuild -b -uc
