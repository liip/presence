#!/bin/sh
bin/phpcs --report-width=80 --standard=code_standard.xml $1
exit $?
