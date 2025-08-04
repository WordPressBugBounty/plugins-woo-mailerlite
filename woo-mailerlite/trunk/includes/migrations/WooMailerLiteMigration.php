<?php

class WooMailerLiteMigration
{

    public static function migrate()
    {
        $cartsTableSql = "CREATE TABLE IF NOT EXISTS wp_woo_mailerlite_carts (
                        id BIGINT (20) NOT NULL AUTO_INCREMENT,
                    hash VARCHAR(255) DEFAULT NULL,
                    email VARCHAR(255) DEFAULT NULL,
                    subscribe TINYINT(1) DEFAULT 0,
                    data LONGTEXT NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id))
                    DEFAULT CHARACTER
                    SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;";
        db()->query($cartsTableSql);
        $jobsTableMigration = "CREATE TABLE IF NOT EXISTS wp_woo_mailerlite_jobs (
                            id BIGINT (20) NOT NULL AUTO_INCREMENT,
                        object_id TEXT NOT NULL,
                        job TEXT NOT NULL,
                        data LONGTEXT NOT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (id))
                        DEFAULT CHARACTER
                        SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;";
        db()->query($jobsTableMigration);
    }

    public static function rollback()
    {
        $prefix = db()->prefix;
        db()->query("DROP TABLE IF EXISTS {$prefix}woo_mailerlite_carts");
        db()->query("DROP TABLE IF EXISTS {$prefix}woo_mailerlite_jobs");
    }

    public static function truncate()
    {
        $prefix = db()->prefix;
        $tables = [
            "{$prefix}woo_mailerlite_carts",
            "{$prefix}woo_mailerlite_jobs",
        ];

        foreach ($tables as $table) {
            $exists = db()->get_var(db()->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));

            if ($exists === $table) {
                db()->query("TRUNCATE TABLE $table");
            }
        }
    }
}