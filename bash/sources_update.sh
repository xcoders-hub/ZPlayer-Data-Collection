#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

php "$DIR/../Sources/Main.php" Watchseries update > "$DIR/../output/sources_update.log" &