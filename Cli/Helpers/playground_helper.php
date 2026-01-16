if (!function_exists('table')) {
function table($name)
{
return \Database\DB::table($name);
}
}

if (!function_exists('queries')) {
function queries()
{
return \Database\Connection::getQueryLog();
}
}

if (!function_exists('lastQuery')) {
function lastQuery()
{
$log = \Database\Connection::getQueryLog();

return end($log) ?: null;
}
}

if (!function_exists('clearQueries')) {
function clearQueries()
{
\Database\Connection::clearQueryLog();
}
}

if (!function_exists('clear')) {
function clear()
{
// ANSI escape codes to clear the screen
echo "\033[2J\033[;H";
}
}