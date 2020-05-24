DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

rm "$DIR/output/*.log" -rf

php "$DIR/Sources/Main.php" Watchseries update > "$DIR/output/sources_update.log" &
php "$DIR/Sources/Main.php" watchseries series > "$DIR/output/sources_series.log" &
php "$DIR/Sources/Main.php" watchseries movies > "$DIR/output/sources_movies.log" &