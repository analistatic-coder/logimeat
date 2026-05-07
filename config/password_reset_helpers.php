<?php
declare(strict_types=1);

/**
 * Tabla de tokens y columna Email en User (si no existen).
 */
function lm_password_reset_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `password_reset` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(64) NOT NULL,
            `token` CHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            INDEX `idx_pr_token` (`token`),
            INDEX `idx_pr_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    try {
        $pdo->exec('ALTER TABLE `User` ADD COLUMN `Email` VARCHAR(255) NULL');
    } catch (PDOException) {
        // Columna ya existe
    }
}
