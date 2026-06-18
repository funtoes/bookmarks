<?php
require_once __DIR__ . '/db.php';

try {
    $pdo = getDB();
    
    // 创建用户表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `default_view` ENUM('card','table') DEFAULT 'card',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		`api_key` VARCHAR(64) NULL DEFAULT NULL,
		`remember_token` VARCHAR(64) NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 创建分类表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 创建书签表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bookmarks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `category_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `url` TEXT NOT NULL,
        `clicks` INT NOT NULL DEFAULT 0,
        `last_click` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	
	// 创建设置表
	$pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
		`key` VARCHAR(50) PRIMARY KEY,
		`value` TEXT NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	
	// 创建备忘录表
	$pdo->exec("CREATE TABLE IF NOT EXISTS `memos` (
		`id` INT AUTO_INCREMENT PRIMARY KEY,
		`user_id` INT NOT NULL,
		`category_id` INT NOT NULL,
		`content` TEXT NOT NULL,
		`share_token` VARCHAR(64) NULL DEFAULT NULL,
		`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
		FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "数据库表创建成功！请删除 install.php 文件。";
} catch (PDOException $e) {
    die("安装失败: " . $e->getMessage());
}