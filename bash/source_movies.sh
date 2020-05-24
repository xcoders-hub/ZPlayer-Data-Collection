#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

php "$DIR/../Sources/Main.php" watchseries movies > "$DIR/../output/sources_movies.log" &