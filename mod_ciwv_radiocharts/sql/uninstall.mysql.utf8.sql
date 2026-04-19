-- CIWV Radiocharts: uninstall.mysql.utf8.sql
-- Removes the shared chart-data table on module uninstall.
-- WARNING: This will permanently delete all stored chart history.

DROP TABLE IF EXISTS `#__ciwv_radiocharts`;
