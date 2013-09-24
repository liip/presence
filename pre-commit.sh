#!/bin/sh

# make sure only changes of the current commit are affected
git stash -q -u --keep-index

./phpcodesniffer.sh src/
exit_code=$?

git stash pop -q

exit $exit_code
