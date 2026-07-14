-- Update subscription plans from Free/Basic/Premium to Free/Monthly/Yearly
-- Run this SQL to update existing database without resetting other data

USE mealkit_db;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- First, insert new Monthly plan
INSERT INTO `subscription_plans`
(`name`, `slug`, `description`, `price`, `duration_days`, `recipe_limit`, `meal_plan_limit`, `can_download`, `can_access_premium`, `features`, `is_popular`, `status`, `created_at`)
VALUES
('Monthly', 'monthly', 'Unlock unlimited recipes, PDF downloads, and up to 5 meal plans.', 10000, 30, 0, 5, 1, 0,
'["Unlimited public recipes","5 meal plans per month","PDF recipe download","Serving size calculator","Email support"]',
1, 'active', NOW());

-- Insert new Yearly plan
INSERT INTO `subscription_plans`
(`name`, `slug`, `description`, `price`, `duration_days`, `recipe_limit`, `meal_plan_limit`, `can_download`, `can_access_premium`, `features`, `is_popular`, `status`, `created_at`)
VALUES
('Yearly', 'yearly', 'Everything in Monthly plus exclusive premium recipes and unlimited meal plans. Save 20% with annual billing.', 100000, 365, 0, 0, 1, 1,
'["Everything in Monthly","Exclusive premium recipes","Unlimited meal plans","Priority support","Early access to new recipes","Monthly nutrition report","Save 20% annually"]',
0, 'active', NOW());

-- Update existing subscriptions referencing Basic/Premium to reference Monthly plan
UPDATE `subscriptions` SET `plan_id` = (SELECT `id` FROM `subscription_plans` WHERE `slug` = 'monthly' LIMIT 1) 
WHERE `plan_id` IN (SELECT `id` FROM `subscription_plans` WHERE `slug` IN ('basic', 'premium'));

-- Now delete old Basic and Premium plans
DELETE FROM `subscription_plans` WHERE `slug` IN ('basic', 'premium');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
