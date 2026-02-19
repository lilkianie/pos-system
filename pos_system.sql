/*
SQLyog Ultimate v13.1.1 (64 bit)
MySQL - 10.4.27-MariaDB : Database - pos_system
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`pos_system` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `pos_system`;

/*Table structure for table `accounts_payable` */

DROP TABLE IF EXISTS `accounts_payable`;

CREATE TABLE `accounts_payable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL,
  `status` enum('open','partial','paid','overdue') DEFAULT 'open',
  `payment_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_po` (`purchase_order_id`),
  CONSTRAINT `accounts_payable_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `accounts_payable_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `accounts_payable` */

insert  into `accounts_payable`(`id`,`purchase_order_id`,`supplier_id`,`invoice_number`,`invoice_date`,`due_date`,`amount`,`paid_amount`,`balance`,`status`,`payment_date`,`notes`,`created_at`,`updated_at`) values 
(1,1,1,NULL,'2026-02-19','2026-03-21',5000.00,0.00,5000.00,'open',NULL,NULL,'2026-02-19 01:09:01','2026-02-19 01:09:01');

/*Table structure for table `accounts_receivable` */

DROP TABLE IF EXISTS `accounts_receivable`;

CREATE TABLE `accounts_receivable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) NOT NULL,
  `status` enum('open','partial','paid','overdue') DEFAULT 'open',
  `payment_date` date DEFAULT NULL,
  `points_earned` int(11) DEFAULT 0 COMMENT 'Points earned from this transaction',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_transaction` (`transaction_id`),
  CONSTRAINT `accounts_receivable_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`),
  CONSTRAINT `accounts_receivable_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `accounts_receivable` */

insert  into `accounts_receivable`(`id`,`transaction_id`,`customer_id`,`invoice_number`,`invoice_date`,`due_date`,`amount`,`paid_amount`,`balance`,`status`,`payment_date`,`points_earned`,`notes`,`created_at`,`updated_at`) values 
(1,8,1,'INV-20260219-000008','2026-02-19','2026-03-21',750.40,0.00,750.40,'open',NULL,0,NULL,'2026-02-19 01:01:21','2026-02-19 01:01:21');

/*Table structure for table `ap_payments` */

DROP TABLE IF EXISTS `ap_payments`;

CREATE TABLE `ap_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `accounts_payable_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','check','bank_transfer','credit_card','other') DEFAULT 'bank_transfer',
  `check_number` varchar(100) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_ap` (`accounts_payable_id`),
  KEY `idx_date` (`payment_date`),
  CONSTRAINT `ap_payments_ibfk_1` FOREIGN KEY (`accounts_payable_id`) REFERENCES `accounts_payable` (`id`),
  CONSTRAINT `ap_payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `ap_payments` */

/*Table structure for table `ar_payments` */

DROP TABLE IF EXISTS `ar_payments`;

CREATE TABLE `ar_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `accounts_receivable_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','check','bank_transfer','credit_card','other') DEFAULT 'cash',
  `check_number` varchar(100) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_ar` (`accounts_receivable_id`),
  KEY `idx_date` (`payment_date`),
  CONSTRAINT `ar_payments_ibfk_1` FOREIGN KEY (`accounts_receivable_id`) REFERENCES `accounts_receivable` (`id`),
  CONSTRAINT `ar_payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `ar_payments` */

/*Table structure for table `cash_count_items` */

DROP TABLE IF EXISTS `cash_count_items`;

CREATE TABLE `cash_count_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cash_count_id` int(11) NOT NULL,
  `denomination` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `total_amount` decimal(10,2) NOT NULL,
  `count_type` enum('beginning','ending') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cash_count` (`cash_count_id`),
  KEY `idx_count_type` (`count_type`),
  CONSTRAINT `cash_count_items_ibfk_1` FOREIGN KEY (`cash_count_id`) REFERENCES `cash_counts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `cash_count_items` */

insert  into `cash_count_items`(`id`,`cash_count_id`,`denomination`,`quantity`,`total_amount`,`count_type`) values 
(1,1,1000.00,10,10000.00,'beginning'),
(2,1,500.00,10,5000.00,'beginning'),
(3,1,200.00,20,4000.00,'beginning'),
(4,1,100.00,50,5000.00,'beginning'),
(5,1,50.00,100,5000.00,'beginning'),
(6,1,20.00,200,4000.00,'beginning'),
(7,1,10.00,500,5000.00,'beginning'),
(8,1,5.00,1000,5000.00,'beginning'),
(9,1,1.00,1000,1000.00,'beginning'),
(10,2,500.00,50,25000.00,'beginning'),
(11,2,500.00,10,5000.00,'beginning');

/*Table structure for table `cash_counts` */

DROP TABLE IF EXISTS `cash_counts`;

CREATE TABLE `cash_counts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `shift_type` enum('morning','afternoon','night','full_day') DEFAULT 'full_day',
  `beginning_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ending_cash` decimal(10,2) DEFAULT NULL,
  `expected_cash` decimal(10,2) DEFAULT NULL,
  `actual_cash` decimal(10,2) DEFAULT NULL,
  `difference` decimal(10,2) DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `beginning_notes` text DEFAULT NULL,
  `ending_notes` text DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_shift` (`user_id`,`shift_date`,`shift_type`),
  KEY `idx_user_date` (`user_id`,`shift_date`),
  KEY `idx_date` (`shift_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `cash_counts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `cash_counts` */

insert  into `cash_counts`(`id`,`user_id`,`shift_date`,`shift_type`,`beginning_cash`,`ending_cash`,`expected_cash`,`actual_cash`,`difference`,`status`,`beginning_notes`,`ending_notes`,`started_at`,`ended_at`,`created_at`,`updated_at`) values 
(1,4,'2026-02-19','full_day',44000.00,44240.80,44240.80,44240.80,0.00,'closed','','','2026-02-19 00:06:04','2026-02-19 00:07:32','2026-02-19 00:06:04','2026-02-19 00:07:32'),
(2,4,'2026-02-19','morning',30000.00,NULL,NULL,NULL,NULL,'open','',NULL,'2026-02-19 14:09:46',NULL,'2026-02-19 14:09:46','2026-02-19 14:09:46');

/*Table structure for table `categories` */

DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `categories` */

insert  into `categories`(`id`,`category_name`,`description`,`is_active`,`created_at`,`updated_at`) values 
(1,'Beverages','Drinks and beverages',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(2,'Food','Food items',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(3,'Snacks','Snacks and chips',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(4,'Dairy','Dairy products',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(5,'Fruits & Vegetables','Fresh fruits and vegetables',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(6,'Meat & Seafood','Meat and seafood products',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(7,'Bakery','Bread and bakery items',1,'2026-02-18 23:06:53','2026-02-18 23:41:13'),
(8,'Household','Household items',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(9,'Personal Care','Personal care products',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(10,'Other','Other products',1,'2026-02-18 23:06:53','2026-02-18 23:06:53'),
(11,'Beverages','Drinks and beverages',0,'2026-02-19 00:03:07','2026-02-19 06:53:12'),
(12,'Food','Food items',0,'2026-02-19 00:03:07','2026-02-19 06:53:22'),
(13,'Snacks','Snacks and chips',0,'2026-02-19 00:03:07','2026-02-19 06:53:49'),
(14,'Dairy','Dairy products',0,'2026-02-19 00:03:07','2026-02-19 06:53:18'),
(15,'Fruits & Vegetables','Fresh fruits and vegetables',0,'2026-02-19 00:03:07','2026-02-19 06:53:26'),
(16,'Meat & Seafood','Meat and seafood products',0,'2026-02-19 00:03:07','2026-02-19 06:53:37'),
(17,'Bakery','Bread and bakery items',0,'2026-02-19 00:03:07','2026-02-19 06:53:01'),
(18,'Household','Household items',0,'2026-02-19 00:03:07','2026-02-19 06:53:33'),
(19,'Personal Care','Personal care products',0,'2026-02-19 00:03:07','2026-02-19 06:53:45'),
(20,'Other','Other products',0,'2026-02-19 00:03:07','2026-02-19 06:53:41');

/*Table structure for table `customer_points_transactions` */

DROP TABLE IF EXISTS `customer_points_transactions`;

CREATE TABLE `customer_points_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `transaction_type` enum('earned','redeemed','expired','adjusted') NOT NULL,
  `points` int(11) NOT NULL COMMENT 'Positive for earned/adjusted, negative for redeemed/expired',
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'transaction, ar_payment, manual, etc.',
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_type` (`transaction_type`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `customer_points_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `customer_points_transactions` */

/*Table structure for table `customers` */

DROP TABLE IF EXISTS `customers`;

CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `customer_type` enum('regular','member') DEFAULT 'regular',
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00 COMMENT 'Current outstanding balance',
  `points_balance` int(11) DEFAULT 0 COMMENT 'Reward points balance',
  `points_earned_total` int(11) DEFAULT 0 COMMENT 'Total points earned (lifetime)',
  `standing` enum('good','warning','bad') DEFAULT 'good',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_code` (`customer_code`),
  KEY `idx_code` (`customer_code`),
  KEY `idx_name` (`customer_name`),
  KEY `idx_type` (`customer_type`),
  KEY `idx_standing` (`standing`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `customers` */

insert  into `customers`(`id`,`customer_code`,`customer_name`,`email`,`phone`,`address`,`city`,`state`,`zip_code`,`birth_date`,`customer_type`,`credit_limit`,`balance`,`points_balance`,`points_earned_total`,`standing`,`is_active`,`notes`,`created_at`,`updated_at`) values 
(1,'1','Herald Felisilda','','','','','','','1976-12-17','regular',5000.00,750.40,0,0,'good',1,'','2026-02-19 00:44:49','2026-02-19 01:01:21'),
(2,'2','Hezekian','','','','','','','0000-00-00','regular',5000.00,0.00,0,0,'good',1,'','2026-02-19 13:30:09','2026-02-19 13:30:35');

/*Table structure for table `inventory_transactions` */

DROP TABLE IF EXISTS `inventory_transactions`;

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('stock_in','stock_out','adjustment','sale','return','damaged','expired') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'transaction, purchase_order, adjustment, etc.',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of related transaction or document',
  `notes` text DEFAULT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who performed the transaction',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_type` (`transaction_type`),
  KEY `idx_date` (`created_at`),
  KEY `idx_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `inventory_transactions` */

insert  into `inventory_transactions`(`id`,`product_id`,`transaction_type`,`quantity`,`previous_stock`,`new_stock`,`reference_type`,`reference_id`,`notes`,`user_id`,`created_at`) values 
(1,3,'sale',-1,43,42,'transaction',6,NULL,1,'2026-02-19 00:49:42'),
(2,2,'sale',-1,86,85,'transaction',6,NULL,1,'2026-02-19 00:49:42'),
(3,3,'sale',-1,42,41,'transaction',7,NULL,1,'2026-02-19 00:51:16'),
(4,1,'sale',-1,96,95,'transaction',7,NULL,1,'2026-02-19 00:51:16'),
(5,5,'sale',-1,58,57,'transaction',7,NULL,1,'2026-02-19 00:51:16'),
(6,4,'sale',-1,79,78,'transaction',7,NULL,1,'2026-02-19 00:51:16'),
(7,2,'sale',-8,85,77,'transaction',7,NULL,1,'2026-02-19 00:51:16'),
(8,3,'sale',-1,41,40,'transaction',8,NULL,1,'2026-02-19 01:01:21'),
(9,1,'sale',-1,95,94,'transaction',8,NULL,1,'2026-02-19 01:01:21'),
(10,5,'sale',-4,57,53,'transaction',8,NULL,1,'2026-02-19 01:01:21'),
(11,4,'sale',-1,78,77,'transaction',8,NULL,1,'2026-02-19 01:01:21'),
(12,2,'sale',-1,77,76,'transaction',8,NULL,1,'2026-02-19 01:01:21'),
(13,3,'stock_in',50,40,90,'purchase_order',1,'PO 1 received',1,'2026-02-19 06:56:00'),
(14,3,'sale',-1,90,89,'transaction',9,NULL,4,'2026-02-19 13:47:32'),
(15,1,'sale',-1,94,93,'transaction',9,NULL,4,'2026-02-19 13:47:32'),
(16,1,'sale',-1,93,92,'transaction',10,NULL,4,'2026-02-19 14:10:13'),
(17,5,'sale',-1,53,52,'transaction',10,NULL,4,'2026-02-19 14:10:13'),
(18,3,'sale',-1,89,88,'transaction',10,NULL,4,'2026-02-19 14:10:13'),
(19,4,'sale',-1,77,76,'transaction',10,NULL,4,'2026-02-19 14:10:13');

/*Table structure for table `offline_transactions` */

DROP TABLE IF EXISTS `offline_transactions`;

CREATE TABLE `offline_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `local_transaction_id` varchar(100) NOT NULL,
  `transaction_data` text NOT NULL,
  `status` enum('pending','synced','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `local_transaction_id` (`local_transaction_id`),
  KEY `idx_status` (`status`),
  KEY `idx_local_id` (`local_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `offline_transactions` */

/*Table structure for table `permissions` */

DROP TABLE IF EXISTS `permissions`;

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_name` (`permission_name`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `permissions` */

insert  into `permissions`(`id`,`permission_name`,`description`,`created_at`) values 
(1,'manage_users','Create, edit, and delete users','2026-02-18 23:06:53'),
(2,'manage_products','Create, edit, and delete products','2026-02-18 23:06:53'),
(3,'manage_categories','Create, edit, and delete categories','2026-02-18 23:06:53'),
(4,'manage_roles','Create, edit, and delete roles and permissions','2026-02-18 23:06:53'),
(5,'view_reports','View sales and inventory reports','2026-02-18 23:06:53'),
(6,'process_sales','Process sales transactions','2026-02-18 23:06:53'),
(7,'manage_settings','Manage system settings','2026-02-18 23:06:53'),
(8,'view_dashboard','View dashboard and analytics','2026-02-18 23:06:53'),
(9,'void_transactions','Void/cancel transactions','2026-02-18 23:44:22'),
(10,'manage_cash_count','Manage cashier cash counts','2026-02-19 00:01:51'),
(21,'manage_inventory','Manage inventory transactions and adjustments','2026-02-19 00:11:29'),
(22,'manage_suppliers','Manage suppliers and vendor information','2026-02-19 00:22:55'),
(23,'manage_purchase_orders','Create and manage purchase orders','2026-02-19 00:22:55'),
(24,'manage_accounts_payable','Manage accounts payable and payments','2026-02-19 00:22:55'),
(25,'view_ap_reports','View accounts payable reports','2026-02-19 00:22:55'),
(26,'manage_customers','Manage customers and customer accounts','2026-02-19 00:38:43'),
(27,'manage_accounts_receivable','Manage accounts receivable and collections','2026-02-19 00:38:43'),
(28,'view_ar_reports','View accounts receivable reports','2026-02-19 00:38:43'),
(29,'process_credit_sales','Process credit sales transactions','2026-02-19 00:38:43');

/*Table structure for table `products` */

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 0,
  `unit` varchar(50) DEFAULT 'pcs',
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_category` (`category_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `products` */

insert  into `products`(`id`,`barcode`,`product_name`,`description`,`category_id`,`price`,`cost`,`stock_quantity`,`min_stock_level`,`unit`,`image_url`,`is_active`,`created_at`,`updated_at`) values 
(1,'1234567890123','Coca Cola 1.5L','Carbonated soft drink',1,45.00,35.00,92,20,'bottle','http://possystem.com/assets/uploads/products/product_1771455068_6996425c2afb5.png',1,'2026-02-18 23:06:53','2026-02-19 14:10:13'),
(2,'1234567890124','Pepsi 1.5L','Carbonated soft drink',1,45.00,35.00,76,20,'bottle',NULL,1,'2026-02-18 23:06:53','2026-02-19 01:01:21'),
(3,'1234567890125','Bread White','White bread loaf',7,35.00,25.00,88,10,'loaf','http://possystem.com/assets/uploads/products/product_1771455146_699642aae3b90.jpeg',1,'2026-02-18 23:06:53','2026-02-19 14:10:13'),
(4,'1234567890126','Milk 1L','Fresh milk',4,65.00,50.00,76,15,'bottle',NULL,1,'2026-02-18 23:06:53','2026-02-19 14:10:13'),
(5,'1234567890127','Eggs Dozen','Fresh chicken eggs',4,120.00,95.00,52,12,'dozen',NULL,1,'2026-02-18 23:06:53','2026-02-19 14:10:13'),
(11,'9002648','electronic cleaner','',10,560.00,500.00,10,5,'pcs','',1,'2026-02-19 13:34:19','2026-02-19 13:34:19');

/*Table structure for table `purchase_order_items` */

DROP TABLE IF EXISTS `purchase_order_items`;

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_po` (`purchase_order_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `purchase_order_items` */

insert  into `purchase_order_items`(`id`,`purchase_order_id`,`product_id`,`quantity`,`unit_cost`,`discount`,`subtotal`,`received_quantity`) values 
(1,1,3,100,50.00,0.00,5000.00,50);

/*Table structure for table `purchase_orders` */

DROP TABLE IF EXISTS `purchase_orders`;

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` enum('pending','ordered','partial','received','cancelled') DEFAULT 'pending',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`order_date`),
  KEY `idx_po_number` (`po_number`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `purchase_orders` */

insert  into `purchase_orders`(`id`,`po_number`,`supplier_id`,`order_date`,`expected_delivery_date`,`delivery_date`,`status`,`subtotal`,`tax_amount`,`discount_amount`,`total_amount`,`paid_amount`,`balance`,`payment_status`,`notes`,`created_by`,`created_at`,`updated_at`) values 
(1,'1',1,'2026-02-19','2026-02-19','2026-02-19','partial',5000.00,0.00,0.00,5000.00,0.00,5000.00,'unpaid','',1,'2026-02-19 01:09:01','2026-02-19 06:56:00');

/*Table structure for table `rewards` */

DROP TABLE IF EXISTS `rewards`;

CREATE TABLE `rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reward_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `points_required` int(11) NOT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL COMMENT 'If applicable',
  `discount_amount` decimal(10,2) DEFAULT NULL COMMENT 'If applicable',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `rewards` */

insert  into `rewards`(`id`,`reward_name`,`description`,`points_required`,`discount_percentage`,`discount_amount`,`is_active`,`created_at`,`updated_at`) values 
(1,'5% Discount','Get 5% discount on your next purchase',100,5.00,NULL,1,'2026-02-19 00:38:43','2026-02-19 00:38:43'),
(2,'10% Discount','Get 10% discount on your next purchase',200,10.00,NULL,1,'2026-02-19 00:38:43','2026-02-19 00:38:43'),
(3,'15% Discount','Get 15% discount on your next purchase',300,15.00,NULL,1,'2026-02-19 00:38:43','2026-02-19 00:38:43'),
(4,'Free Item (Small)','Redeem a small free item',500,NULL,NULL,1,'2026-02-19 00:38:43','2026-02-19 00:38:43'),
(5,'Free Item (Medium)','Redeem a medium free item',1000,NULL,NULL,1,'2026-02-19 00:38:43','2026-02-19 00:38:43'),
(6,'Free Item (Large)','Redeem a large free item',2000,NULL,NULL,1,'2026-02-19 00:38:43','2026-02-19 00:38:43');

/*Table structure for table `role_permissions` */

DROP TABLE IF EXISTS `role_permissions`;

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `role_permissions` */

insert  into `role_permissions`(`id`,`role_id`,`permission_id`) values 
(5,1,1),
(2,1,2),
(1,1,3),
(3,1,4),
(8,1,5),
(6,1,6),
(4,1,7),
(7,1,8),
(34,1,9),
(39,1,10),
(49,1,21),
(58,1,22),
(55,1,23),
(52,1,24),
(61,1,25),
(70,1,26),
(67,1,27),
(76,1,28),
(73,1,29),
(83,2,1),
(17,2,2),
(16,2,3),
(81,2,4),
(20,2,5),
(18,2,6),
(82,2,7),
(19,2,8),
(33,2,9),
(36,2,10),
(47,2,21),
(56,2,22),
(53,2,23),
(50,2,24),
(59,2,25),
(68,2,26),
(65,2,27),
(74,2,28),
(71,2,29),
(95,3,1),
(89,3,2),
(99,3,3),
(90,3,4),
(87,3,5),
(23,3,6),
(96,3,7),
(24,3,8),
(93,3,9),
(37,3,10),
(98,3,21),
(91,3,22),
(97,3,23),
(88,3,24),
(92,3,25),
(84,3,26),
(100,3,27),
(94,3,28),
(80,3,29),
(27,4,2),
(26,4,3),
(29,4,5),
(28,4,8),
(38,4,10),
(48,4,21),
(57,4,22),
(54,4,23),
(51,4,24),
(60,4,25),
(69,4,26),
(66,4,27),
(75,4,28),
(72,4,29);

/*Table structure for table `roles` */

DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `roles` */

insert  into `roles`(`id`,`role_name`,`description`,`created_at`) values 
(1,'Super Admin','Full system access','2026-02-18 23:06:53'),
(2,'Admin','Administrative access','2026-02-18 23:06:53'),
(3,'Cashier','POS cashiering access','2026-02-18 23:06:53'),
(4,'Manager','Management and reporting access','2026-02-18 23:06:53');

/*Table structure for table `settings` */

DROP TABLE IF EXISTS `settings`;

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `settings` */

insert  into `settings`(`id`,`setting_key`,`setting_value`,`description`,`updated_at`) values 
(1,'store_name','POS Cashiering','Store name','2026-02-19 13:53:08'),
(2,'tax_rate','12','Tax rate percentage','2026-02-18 23:06:53'),
(3,'currency','PHP','Currency code','2026-02-18 23:06:53'),
(4,'receipt_footer','Thank you for shopping with us!','Receipt footer text','2026-02-18 23:06:53'),
(5,'offline_mode_enabled','1','Enable offline mode','2026-02-18 23:06:53'),
(11,'points_per_peso','1','Points earned per peso spent','2026-02-19 00:38:43'),
(12,'points_expiry_days','365','Points expiry in days','2026-02-19 00:38:43'),
(13,'credit_payment_terms','30','Default credit payment terms in days','2026-02-19 00:38:43'),
(14,'bad_standing_threshold','90','Days overdue to mark as bad standing','2026-02-19 00:38:43'),
(15,'warning_standing_threshold','60','Days overdue to mark as warning','2026-02-19 00:38:43');

/*Table structure for table `suppliers` */

DROP TABLE IF EXISTS `suppliers`;

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Philippines',
  `tax_id` varchar(100) DEFAULT NULL,
  `payment_terms` int(11) DEFAULT 30 COMMENT 'Payment terms in days',
  `credit_limit` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00 COMMENT 'Current outstanding balance',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`supplier_name`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `suppliers` */

insert  into `suppliers`(`id`,`supplier_name`,`contact_person`,`email`,`phone`,`address`,`city`,`state`,`zip_code`,`country`,`tax_id`,`payment_terms`,`credit_limit`,`balance`,`is_active`,`notes`,`created_at`,`updated_at`) values 
(1,'ABC Trading Company','Juan Dela Cruz','juan@abctrading.com','+63 912 345 6789','123 Main Street, Manila',NULL,NULL,NULL,'Philippines',NULL,30,100000.00,0.00,1,NULL,'2026-02-19 00:22:55','2026-02-19 00:22:55'),
(2,'XYZ Distributors','Maria Santos','maria@xyzdist.com','+63 923 456 7890','456 Business Ave, Quezon City',NULL,NULL,NULL,'Philippines',NULL,45,50000.00,0.00,1,NULL,'2026-02-19 00:22:55','2026-02-19 00:22:55');

/*Table structure for table `transaction_items` */

DROP TABLE IF EXISTS `transaction_items`;

CREATE TABLE `transaction_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_transaction` (`transaction_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transaction_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `transaction_items` */

insert  into `transaction_items`(`id`,`transaction_id`,`product_id`,`quantity`,`unit_price`,`subtotal`,`discount`) values 
(1,1,3,1,35.00,35.00,0.00),
(2,1,1,1,45.00,45.00,0.00),
(3,1,5,1,120.00,120.00,0.00),
(4,1,4,1,65.00,65.00,0.00),
(5,2,3,1,35.00,35.00,0.00),
(6,2,1,1,45.00,45.00,0.00),
(7,3,3,3,35.00,105.00,0.00),
(8,3,2,3,45.00,135.00,0.00),
(9,3,1,1,45.00,45.00,0.00),
(10,4,3,2,35.00,70.00,0.00),
(11,4,1,1,45.00,45.00,0.00),
(12,4,5,1,120.00,120.00,0.00),
(13,4,2,8,45.00,360.00,0.00),
(14,5,2,3,45.00,135.00,0.00),
(15,5,3,1,35.00,35.00,0.00),
(16,5,1,1,45.00,45.00,0.00),
(17,6,3,1,35.00,35.00,0.00),
(18,6,2,1,45.00,45.00,0.00),
(19,7,3,1,35.00,35.00,0.00),
(20,7,1,1,45.00,45.00,0.00),
(21,7,5,1,120.00,120.00,0.00),
(22,7,4,1,65.00,65.00,0.00),
(23,7,2,8,45.00,360.00,0.00),
(24,8,3,1,35.00,35.00,0.00),
(25,8,1,1,45.00,45.00,0.00),
(26,8,5,4,120.00,480.00,0.00),
(27,8,4,1,65.00,65.00,0.00),
(28,8,2,1,45.00,45.00,0.00),
(29,9,3,1,35.00,35.00,0.00),
(30,9,1,1,45.00,45.00,0.00),
(31,10,1,1,45.00,45.00,0.00),
(32,10,5,1,120.00,120.00,0.00),
(33,10,3,1,35.00,35.00,0.00),
(34,10,4,1,65.00,65.00,0.00);

/*Table structure for table `transactions` */

DROP TABLE IF EXISTS `transactions`;

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','e_wallet','credit') DEFAULT 'cash',
  `payment_status` enum('pending','completed','refunded','voided') DEFAULT 'completed',
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` int(11) DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `is_synced` tinyint(1) DEFAULT 0,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_number` (`transaction_number`),
  KEY `idx_transaction_number` (`transaction_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_synced` (`is_synced`),
  KEY `voided_by` (`voided_by`),
  KEY `idx_voided` (`voided_at`),
  KEY `idx_customer` (`customer_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `transactions` */

insert  into `transactions`(`id`,`transaction_number`,`user_id`,`customer_id`,`total_amount`,`discount_amount`,`tax_amount`,`final_amount`,`payment_method`,`payment_status`,`voided_at`,`voided_by`,`void_reason`,`is_synced`,`synced_at`,`created_at`,`updated_at`) values 
(1,'TXN-20260218-0380',1,NULL,265.00,0.00,31.80,296.80,'e_wallet','completed',NULL,NULL,NULL,0,NULL,'2026-02-18 23:32:00','2026-02-18 23:32:00'),
(2,'TXN-20260218-5770',1,NULL,80.00,0.00,9.60,89.60,'cash','voided','2026-02-18 23:45:21',1,'testing',0,NULL,'2026-02-18 23:33:15','2026-02-18 23:45:21'),
(3,'TXN-20260218-5731',1,NULL,285.00,0.00,34.20,319.20,'cash','completed',NULL,NULL,NULL,0,NULL,'2026-02-18 23:49:55','2026-02-18 23:49:55'),
(4,'TXN-20260218-2602',1,NULL,595.00,0.00,71.40,666.40,'cash','completed',NULL,NULL,NULL,0,NULL,'2026-02-18 23:51:03','2026-02-18 23:51:03'),
(5,'TXN-20260219-6483',4,NULL,215.00,0.00,25.80,240.80,'cash','completed',NULL,NULL,NULL,0,NULL,'2026-02-19 00:06:48','2026-02-19 00:06:48'),
(6,'TXN-20260219-1183',1,1,80.00,0.00,9.60,89.60,'credit','completed',NULL,NULL,NULL,0,NULL,'2026-02-19 00:49:42','2026-02-19 00:49:42'),
(7,'TXN-20260219-4494',1,1,625.00,0.00,75.00,700.00,'credit','completed',NULL,NULL,NULL,0,NULL,'2026-02-19 00:51:16','2026-02-19 00:51:16'),
(8,'TXN-20260219-5637',1,1,670.00,0.00,80.40,750.40,'credit','completed',NULL,NULL,NULL,0,NULL,'2026-02-19 01:01:21','2026-02-19 01:01:21'),
(9,'TXN-20260219-2355',4,NULL,80.00,0.00,9.60,89.60,'cash','completed',NULL,NULL,NULL,0,NULL,'2026-02-19 13:47:32','2026-02-19 13:47:32'),
(10,'TXN-20260219-2134',4,NULL,265.00,0.00,31.80,296.80,'cash','completed',NULL,NULL,NULL,0,NULL,'2026-02-19 14:10:13','2026-02-19 14:10:13');

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `avatar_url` varchar(255) DEFAULT NULL COMMENT 'Profile image path relative to uploads/avatars/',
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`username`,`email`,`password`,`full_name`,`avatar_url`,`role_id`,`is_active`,`created_at`,`updated_at`) values 
(1,'admin','admin@pos.com','$2y$10$cAnCoqPVQ/yD6NgZqpaio.prNX4UlLSuwJEXFU2krGjY.AB8HK8Gm','System Administrator','avatars/1_1771436026.jpg',1,1,'2026-02-18 23:06:53','2026-02-19 01:33:46'),
(4,'kiana','kiana@yahoo.com','$2y$10$KazOTmKiqY8CH0UnDZ4UtuhTppZrO.4ifx1hu5X96DpE0SBG1Ss62','Hezekiah Anne Felisilda',NULL,3,1,'2026-02-18 23:38:35','2026-02-18 23:38:35');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
