-- Runs only when the MariaDB volume is first created.
-- Existing volumes: see docs/testing/bdd.md for the one-time manual command.
CREATE DATABASE IF NOT EXISTS iconvenue_testing;
GRANT ALL PRIVILEGES ON iconvenue_testing.* TO 'iconvenue'@'%';
FLUSH PRIVILEGES;
