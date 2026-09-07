<?php
// ─────────────────────────────────────────────
//  Private Group Chat — self-healing schema
//  include this at the top of groups.php / group_chat.php
//  (creates the tables if they don't exist yet, same pattern chat.php uses)
// ─────────────────────────────────────────────

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `chat_groups` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(80) NOT NULL,
  `is_private`  TINYINT(1) NOT NULL DEFAULT 1,
  `logo_path`   VARCHAR(255) NULL,
  `bg_path`     VARCHAR(255) NULL,
  `created_by`  INT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `login_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `chat_group_members` (
  `group_id`  INT UNSIGNED NOT NULL,
  `user_id`   INT UNSIGNED NOT NULL,
  `role`      ENUM('owner','member') NOT NULL DEFAULT 'member',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`,`user_id`),
  FOREIGN KEY (`group_id`) REFERENCES `chat_groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `login_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `group_messages` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `group_id`    INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `body`        TEXT NULL,
  `file_path`   VARCHAR(255) NULL,
  `file_name`   VARCHAR(255) NULL,
  `file_type`   VARCHAR(120) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`group_id`) REFERENCES `chat_groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)  REFERENCES `login_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `group_message_hides` (
  `message_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`message_id`,`user_id`),
  FOREIGN KEY (`message_id`) REFERENCES `group_messages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `login_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB");

// Returns true if $userId belongs to $groupId
function isGroupMember($conn, $groupId, $userId){
    $groupId = intval($groupId); $userId = intval($userId);
    $r = mysqli_query($conn, "SELECT 1 FROM chat_group_members WHERE group_id=$groupId AND user_id=$userId");
    return $r && mysqli_num_rows($r) > 0;
}

// Returns 'owner' | 'member' | null
function groupRole($conn, $groupId, $userId){
    $groupId = intval($groupId); $userId = intval($userId);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM chat_group_members WHERE group_id=$groupId AND user_id=$userId"));
    return $row['role'] ?? null;
}
