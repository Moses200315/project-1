-- ============================================================
-- MealKit – Subscription-Based Recipe & Meal Planning System
-- Database Schema  v1.0.0
-- ============================================================
-- Compatible : MySQL 8.0+ / MariaDB 10.4+
-- Charset    : utf8mb4  (full Unicode + emoji support)
-- Engine     : InnoDB   (FK constraint enforcement)
-- Currency   : GHS (Ghanaian Cedi)  – change per locale
-- ============================================================
-- SETUP INSTRUCTIONS
-- ------------------
--   1. Open phpMyAdmin  →  http://localhost/phpmyadmin
--   2. Create a new database named  mealkit_db
--   3. Select mealkit_db, click Import, and choose this file
--   4. After a successful import visit:
--      http://localhost/mealkit/database/seed.php
--      to load the default admin, subscription plans, categories,
--      and sample recipe data.
--   !! Delete seed.php from the server before going to production !!
-- ============================================================

CREATE DATABASE IF NOT EXISTS `mealkit_db`
    CHARACTER SET  utf8mb4
    COLLATE        utf8mb4_unicode_ci;

USE `mealkit_db`;

-- Disable FK checks during table creation to avoid ordering issues
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE            = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone           = '+00:00';

-- ============================================================
-- TABLE 1: admins
-- Back-office / administrative user accounts.
-- Kept separate from the public `users` table so that a
-- compromised customer account can never escalate to admin.
-- ============================================================
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name`  VARCHAR(100) NOT NULL,
    `last_name`   VARCHAR(100) NOT NULL,
    `email`       VARCHAR(191) NOT NULL,
    `password`    VARCHAR(255) NOT NULL                   COMMENT 'bcrypt hash via password_hash()',
    `avatar`      VARCHAR(255) NOT NULL DEFAULT 'default.png',
    `role`        ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
    `status`      ENUM('active','inactive')  NOT NULL DEFAULT 'active',
    `last_login`  TIMESTAMP    NULL     DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    UNIQUE KEY   `uq_admins_email`  (`email`),
    KEY          `idx_admins_status` (`status`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Administrative back-office user accounts';


-- ============================================================
-- TABLE 2: users
-- Customer / subscriber accounts (public-facing).
-- ============================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name`           VARCHAR(100) NOT NULL,
    `last_name`            VARCHAR(100) NOT NULL,
    `email`                VARCHAR(191) NOT NULL,
    `password`             VARCHAR(255) NOT NULL                   COMMENT 'bcrypt hash',
    `phone`                VARCHAR(25)  NULL DEFAULT NULL,
    `avatar`               VARCHAR(255) NOT NULL DEFAULT 'default.png',
    `bio`                  TEXT         NULL DEFAULT NULL,
    `status`               ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
    `email_verified_at`    TIMESTAMP    NULL DEFAULT NULL,
    `remember_token`       VARCHAR(100) NULL DEFAULT NULL,
    `reset_token`          VARCHAR(100) NULL DEFAULT NULL          COMMENT 'Password reset token (SHA-256)',
    `reset_token_expires`  TIMESTAMP    NULL DEFAULT NULL,
    `last_login`           TIMESTAMP    NULL DEFAULT NULL,
    `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    UNIQUE KEY   `uq_users_email`        (`email`),
    KEY          `idx_users_status`      (`status`),
    KEY          `idx_users_reset_token` (`reset_token`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer subscriber accounts';


-- ============================================================
-- TABLE 3: categories
-- Recipe categories (Breakfast, Dinner, Vegan, etc.).
-- Created and managed exclusively by admins.
-- ============================================================
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`    INT UNSIGNED NOT NULL                           COMMENT 'Admin who created the category',
    `name`        VARCHAR(150) NOT NULL,
    `slug`        VARCHAR(191) NOT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `image`       VARCHAR(255) NULL DEFAULT NULL,
    `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    UNIQUE KEY   `uq_categories_slug`   (`slug`),
    KEY          `idx_categories_status` (`status`),
    KEY          `fk_categories_admin`   (`admin_id`),
    CONSTRAINT `fk_categories_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Recipe categories managed by admins';


-- ============================================================
-- TABLE 4: subscription_plans
-- Defines the available subscription tiers and their privileges.
-- Referenced by the `subscriptions` table (many users → one plan).
-- ============================================================
DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE `subscription_plans` (
    `id`                  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`                VARCHAR(100)   NOT NULL,
    `slug`                VARCHAR(100)   NOT NULL,
    `description`         TEXT           NULL DEFAULT NULL,
    `price`               DECIMAL(10,2)  NOT NULL DEFAULT 0.00    COMMENT 'Price in GHS',
    `duration_days`       INT UNSIGNED   NOT NULL DEFAULT 30       COMMENT 'Subscription duration in days',
    `recipe_limit`        INT UNSIGNED   NOT NULL DEFAULT 0        COMMENT '0 = unlimited',
    `meal_plan_limit`     INT UNSIGNED   NOT NULL DEFAULT 0        COMMENT '0 = unlimited',
    `can_download`        TINYINT(1)     NOT NULL DEFAULT 0        COMMENT 'Allow PDF recipe download',
    `can_access_premium`  TINYINT(1)     NOT NULL DEFAULT 0        COMMENT 'Allow premium recipe access',
    `features`            JSON           NULL DEFAULT NULL         COMMENT 'Feature bullet points for pricing page',
    `is_popular`          TINYINT(1)     NOT NULL DEFAULT 0        COMMENT 'Highlight as most popular',
    `status`              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    UNIQUE KEY   `uq_plans_slug`   (`slug`),
    KEY          `idx_plans_status` (`status`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Subscription plan definitions (Free / Basic / Premium)';


-- ============================================================
-- TABLE 5: recipes
-- Core recipe records. Each recipe belongs to one category
-- and is created by one admin.
-- `is_premium = 1` restricts access to subscribed users.
-- ============================================================
DROP TABLE IF EXISTS `recipes`;
CREATE TABLE `recipes` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `admin_id`     INT UNSIGNED  NOT NULL,
    `category_id`  INT UNSIGNED  NOT NULL,
    `title`        VARCHAR(255)  NOT NULL,
    `slug`         VARCHAR(300)  NOT NULL,
    `description`  TEXT          NOT NULL,
    `image`        VARCHAR(255)  NULL DEFAULT NULL,
    `prep_time`    SMALLINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'Preparation time in minutes',
    `cook_time`    SMALLINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'Cooking time in minutes',
    `servings`     TINYINT UNSIGNED  NOT NULL DEFAULT 2,
    `difficulty`   ENUM('easy','medium','hard') NOT NULL DEFAULT 'medium',
    `calories`     INT UNSIGNED  NULL DEFAULT NULL       COMMENT 'Estimated kcal per serving',
    `is_premium`   TINYINT(1)    NOT NULL DEFAULT 0      COMMENT '1 = active subscription required',
    `is_featured`  TINYINT(1)    NOT NULL DEFAULT 0      COMMENT '1 = shown on homepage / featured section',
    `status`       ENUM('published','draft','archived') NOT NULL DEFAULT 'published',
    `views`        INT UNSIGNED  NOT NULL DEFAULT 0      COMMENT 'Cumulative page-view counter',
    `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    UNIQUE KEY   `uq_recipes_slug`       (`slug`),
    KEY          `idx_recipes_category`  (`category_id`),
    KEY          `idx_recipes_status`    (`status`),
    KEY          `idx_recipes_premium`   (`is_premium`),
    KEY          `idx_recipes_featured`  (`is_featured`),
    KEY          `fk_recipes_admin`      (`admin_id`),
    FULLTEXT KEY `ftx_recipes_search`    (`title`, `description`),
    CONSTRAINT `fk_recipes_admin`
        FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_recipes_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Core recipe records with metadata';


-- ============================================================
-- TABLE 6: ingredients
-- Individual ingredient lines that belong to a recipe.
-- Deleted automatically when parent recipe is removed (CASCADE).
-- ============================================================
DROP TABLE IF EXISTS `ingredients`;
CREATE TABLE `ingredients` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipe_id`   INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `quantity`    VARCHAR(100) NOT NULL,
    `unit`        VARCHAR(50)  NULL DEFAULT NULL            COMMENT 'e.g. cups, tbsp, g, ml',
    `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0       COMMENT 'Display order in ingredient list',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY          `fk_ingredients_recipe` (`recipe_id`),
    KEY          `idx_ingredients_sort`  (`sort_order`),
    CONSTRAINT `fk_ingredients_recipe`
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Ingredient lines per recipe (child of recipes)';


-- ============================================================
-- TABLE 7: procedures
-- Step-by-step cooking instructions per recipe.
-- `step_number` is unique per recipe, enforced by composite UNIQUE.
-- ============================================================
DROP TABLE IF EXISTS `procedures`;
CREATE TABLE `procedures` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipe_id`    INT UNSIGNED NOT NULL,
    `step_number`  TINYINT UNSIGNED NOT NULL                COMMENT 'Ordered step index (1-based)',
    `instruction`  TEXT         NOT NULL,
    `tip`          TEXT         NULL DEFAULT NULL            COMMENT 'Optional chef tip for this step',
    `image`        VARCHAR(255) NULL DEFAULT NULL            COMMENT 'Optional step image',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    UNIQUE KEY   `uq_proc_recipe_step`   (`recipe_id`, `step_number`),
    KEY          `fk_procedures_recipe`  (`recipe_id`),
    CONSTRAINT `fk_procedures_recipe`
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Step-by-step cooking procedures per recipe';


-- ============================================================
-- TABLE 8: meal_plans
-- A customer's named, date-ranged meal plan (weekly or custom).
-- ============================================================
DROP TABLE IF EXISTS `meal_plans`;
CREATE TABLE `meal_plans` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT         NULL DEFAULT NULL,
    `week_start`  DATE         NOT NULL,
    `week_end`    DATE         NOT NULL,
    `status`      ENUM('draft','active','completed') NOT NULL DEFAULT 'draft',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY          `fk_meal_plans_user`   (`user_id`),
    KEY          `idx_meal_plans_week`  (`week_start`, `week_end`),
    KEY          `idx_meal_plans_status` (`status`),
    CONSTRAINT `fk_meal_plans_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer meal plans (weekly or custom date range)';


-- ============================================================
-- TABLE 9: meal_plan_recipes
-- Junction: links a recipe to a specific meal plan slot
-- (day of week + meal type: breakfast / lunch / dinner / snack).
-- ============================================================
DROP TABLE IF EXISTS `meal_plan_recipes`;
CREATE TABLE `meal_plan_recipes` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `meal_plan_id` INT UNSIGNED NOT NULL,
    `recipe_id`    INT UNSIGNED NOT NULL,
    `day_of_week`  ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    `meal_type`    ENUM('breakfast','lunch','dinner','snack') NOT NULL,
    `servings`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `notes`        TEXT         NULL DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY          `fk_mpr_meal_plan` (`meal_plan_id`),
    KEY          `fk_mpr_recipe`    (`recipe_id`),
    CONSTRAINT `fk_mpr_meal_plan`
        FOREIGN KEY (`meal_plan_id`) REFERENCES `meal_plans` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_mpr_recipe`
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Junction table – recipes assigned to meal plan slots';


-- ============================================================
-- TABLE 10: subscriptions
-- One active subscription row per user (status tracks lifecycle).
-- References subscription_plans for the tier details.
-- ============================================================
DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED NOT NULL,
    `plan_id`       INT UNSIGNED NOT NULL,
    `status`        ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
    `starts_at`     TIMESTAMP    NULL DEFAULT NULL,
    `ends_at`       TIMESTAMP    NULL DEFAULT NULL,
    `auto_renew`    TINYINT(1)   NOT NULL DEFAULT 0,
    `cancelled_at`  TIMESTAMP    NULL DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY          `fk_subs_user`      (`user_id`),
    KEY          `fk_subs_plan`      (`plan_id`),
    KEY          `idx_subs_status`   (`status`),
    KEY          `idx_subs_ends_at`  (`ends_at`),
    CONSTRAINT `fk_subs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_subs_plan`
        FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='User subscription records (lifecycle: pending → active → expired)';


-- ============================================================
-- TABLE 11: favourites
-- Customers bookmark their favourite recipes.
-- Composite UNIQUE prevents duplicate bookmarks.
-- ============================================================
DROP TABLE IF EXISTS `favourites`;
CREATE TABLE `favourites` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `recipe_id`  INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    UNIQUE KEY   `uq_fav_user_recipe`  (`user_id`, `recipe_id`),
    KEY          `fk_fav_recipe`       (`recipe_id`),
    CONSTRAINT `fk_fav_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fav_recipe`
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer favourite / bookmarked recipes';


-- ============================================================
-- TABLE 12: payments
-- Mobile Money sandbox payment records.
-- Linked to a subscription (nullable – supports one-off payments).
-- `gateway_response` stores the full sandbox JSON payload.
-- ============================================================
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `user_id`          INT UNSIGNED   NOT NULL,
    `subscription_id`  INT UNSIGNED   NULL DEFAULT NULL,
    `transaction_ref`  VARCHAR(100)   NOT NULL                    COMMENT 'Unique reference (UUID or sandbox ref)',
    `amount`           DECIMAL(10,2)  NOT NULL,
    `currency`         CHAR(3)        NOT NULL DEFAULT 'GHS',
    `payment_method`   ENUM('mobile_money','card','bank') NOT NULL DEFAULT 'mobile_money',
    `provider`         VARCHAR(50)    NULL DEFAULT NULL            COMMENT 'MTN, Vodafone, AirtelTigo, etc.',
    `phone_number`     VARCHAR(25)    NULL DEFAULT NULL,
    `status`           ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
    `gateway_response` JSON           NULL DEFAULT NULL            COMMENT 'Full sandbox API response payload',
    `paid_at`          TIMESTAMP      NULL DEFAULT NULL,
    `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    UNIQUE KEY   `uq_payments_ref`          (`transaction_ref`),
    KEY          `fk_payments_user`         (`user_id`),
    KEY          `fk_payments_subscription` (`subscription_id`),
    KEY          `idx_payments_status`      (`status`),
    KEY          `idx_payments_paid_at`     (`paid_at`),
    CONSTRAINT `fk_payments_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_subscription`
        FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Mobile Money (and other) payment transaction records';


-- ============================================================
-- TABLE 13: recipe_downloads
-- Tracks every PDF download per user per recipe.
-- Allows admin to see download counts and enforce plan limits.
-- ============================================================
DROP TABLE IF EXISTS `recipe_downloads`;
CREATE TABLE `recipe_downloads` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED NOT NULL,
    `recipe_id`      INT UNSIGNED NOT NULL,
    `ip_address`     VARCHAR(45)  NULL DEFAULT NULL        COMMENT 'IPv4 or IPv6 of client',
    `downloaded_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY          `fk_rd_user`         (`user_id`),
    KEY          `fk_rd_recipe`       (`recipe_id`),
    KEY          `idx_rd_downloaded`  (`downloaded_at`),
    CONSTRAINT `fk_rd_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rd_recipe`
        FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log of every recipe PDF download per user';


-- ============================================================
-- TABLE 14: notifications
-- In-app notification inbox per customer.
-- `is_read` toggled to 1 when the user opens the notification.
-- ============================================================
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `message`     TEXT         NOT NULL,
    `type`        ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
    `category`    ENUM('subscription','payment','recipe','meal_plan','system','general') NOT NULL DEFAULT 'general',
    `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
    `action_url`  VARCHAR(500) NULL DEFAULT NULL            COMMENT 'Optional CTA deep-link',
    `read_at`     TIMESTAMP    NULL DEFAULT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY          `fk_notif_user`        (`user_id`),
    KEY          `idx_notif_is_read`    (`is_read`),
    KEY          `idx_notif_created`    (`created_at`),
    CONSTRAINT `fk_notif_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='In-app notification inbox per customer';


-- Re-enable FK enforcement
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SCHEMA COMPLETE
-- Run http://localhost/mealkit/database/seed.php next.
-- ============================================================
