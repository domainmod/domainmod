#!/bin/bash
#
# /tests/get-phpunit.sh
#
# This file is part of DomainMOD, an open source domain and internet asset manager.
# Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
#
# Project: http://domainmod.org   Author: https://greg.ca
#
# DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
# License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
# version.
#
# DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
# warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
# http://www.gnu.org/licenses/.
#
# Downloads the PHPUnit PHAR that DomainMOD's tests use into tests/phpunit.phar (ignored by git) and checks its SHA-256.
#   bash tests/get-phpunit.sh
# A copy that's already there is kept when its checksum matches, and replaced when it doesn't. A download with the
# wrong checksum is deleted, and the script stops with exit code 1.
# To update PHPUnit, change VERSION and SHA256 below (the checksum is listed in https://phar.phpunit.de/phive.xml).
set -euo pipefail

VERSION=11.5.58
SHA256=9d488cd5e62b8eea20dd50ea1e2b65ff6e3837183922f152c7264407f82c368e

URL="https://phar.phpunit.de/phpunit-$VERSION.phar"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHAR="$DIR/phpunit.phar"

sha256() {
    if command -v sha256sum > /dev/null; then
        sha256sum "$1" | cut -d ' ' -f 1
    elif command -v shasum > /dev/null; then
        shasum -a 256 "$1" | cut -d ' ' -f 1
    else
        # shellcheck disable=SC2016  # PHP code, not shell: $argv is PHP's.
        php -r 'echo hash_file("sha256", $argv[1]);' -- "$1"
    fi
}

if [ -L "$PHAR" ] || { [ -e "$PHAR" ] && [ ! -f "$PHAR" ]; }; then
    echo "Stopped: tests/phpunit.phar is a link or a folder, not a file. Remove it, then run this again." >&2
    exit 1
fi
if [ -f "$PHAR" ]; then
    if [ "$(sha256 "$PHAR")" = "$SHA256" ]; then
        echo "PHPUnit $VERSION is already in tests/phpunit.phar, and its checksum matches."
        exit 0
    fi
    echo "tests/phpunit.phar isn't PHPUnit $VERSION (its checksum doesn't match), so it's being replaced."
    rm -f "$PHAR"
fi

command -v curl > /dev/null || { echo "Stopped: curl is needed to download PHPUnit." >&2; exit 1; }
DOWNLOAD="$(mktemp "$DIR/phpunit.phar.download.XXXXXX")"
trap 'rm -f "$DOWNLOAD"' EXIT
if ! curl -fsSL --proto '=https' --tlsv1.2 -o "$DOWNLOAD" "$URL"; then
    echo "Stopped: couldn't download $URL." >&2
    exit 1
fi
ACTUAL="$(sha256 "$DOWNLOAD")"
if [ "$ACTUAL" != "$SHA256" ]; then
    echo "Stopped: the download's SHA-256 is $ACTUAL, not $SHA256, so it was deleted." >&2
    exit 1
fi
mv "$DOWNLOAD" "$PHAR"
echo "PHPUnit $VERSION saved in tests/phpunit.phar, and its checksum matches."
