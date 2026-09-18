/**
 * TEMPORARY DB SETUP DISABLE
 * ------------------------------------------------------------
 * The original database bootstrap and migration logic is intentionally kept here
 * but commented out so the app can still start locally while the real schema is
 * being restored. We are NOT deleting any database code; we are pausing it for a
 * controlled rebuild.
 */

// Original setup code retained for reference only.
//
// <?php
// $host = '127.0.0.1';
// $user = 'root';
// $pass = '1234';
// $dbName = 'venture_harbor';
//
// $mysqli = new mysqli($host, $user, $pass);
// if ($mysqli->connect_errno) {
//     echo '<h2>MySQL connection failed</h2><p>' . htmlspecialchars($mysqli->connect_error) . '</p>';
//     exit;
// }
//
// if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
//     echo '<h2>Database creation failed</h2><p>' . htmlspecialchars($mysqli->error) . '</p>';
//     exit;
// }
//
// if (!$mysqli->select_db($dbName)) {
//     echo '<h2>Database selection failed</h2><p>' . htmlspecialchars($mysqli->error) . '</p>';
//     exit;
// }
//
// $setupSqlPath = __DIR__ . '/config/setup.sql';
// if (!is_file($setupSqlPath)) {
//     $setupSqlPath = __DIR__ . '/config/migrate.sql';
// }
//
// $sql = file_get_contents($setupSqlPath);
// if ($sql === false) {
//     echo '<h2>Database setup script not found</h2><p>Neither config/setup.sql nor config/migrate.sql is available.</p>';
//     exit;
// }
//
// if ($mysqli->multi_query($sql)) {
//     do {
//         if ($result = $mysqli->store_result()) {
//             $result->free();
//         }
//     } while ($mysqli->more_results() && $mysqli->next_result());
//     echo '<h2>Database setup completed successfully.</h2><p>The tables and demo data are ready.</p>';
// } else {
//     echo '<h2>Database setup failed</h2><p>' . htmlspecialchars($mysqli->error) . '</p>';
// }

echo '<h2>Database startup is temporarily paused.</h2>';
echo '<p>The project is in a partial schema state, so the original setup and migration scripts are intentionally disabled for now. This keeps the app runnable locally without deleting any database logic.</p>';
