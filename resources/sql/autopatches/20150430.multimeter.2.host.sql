CREATE TABLE {$NAMESPACE}_multimeter.multimeter_host (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name LONGTEXT COLLATE {$COLLATE_TEXT} NOT NULL,
  nameHash BINARY(12) NOT NULL,
  UNIQUE KEY `key_hash` (nameHash)
) ENGINE=InnoDB, COLLATE {$COLLATE_TEXT};
