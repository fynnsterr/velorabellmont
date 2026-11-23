SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `vb_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(80) NOT NULL,
  `role` varchar(12) NOT NULL,
  `action` varchar(40) NOT NULL,
  `target` varchar(80) DEFAULT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vb_idx_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `label` varchar(40) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(80) NOT NULL,
  `region` varchar(80) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `vb_idx_addresses_user` (`user_id`),
  CONSTRAINT `vb_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vb_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_campaigns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kind` varchar(12) NOT NULL DEFAULT 'newsletter',
  `subject` varchar(160) NOT NULL,
  `body` text NOT NULL,
  `button_text` varchar(40) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'draft',
  `total_recipients` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_campaign_sends` (
  `id` int NOT NULL AUTO_INCREMENT,
  `campaign_id` int NOT NULL,
  `email` varchar(190) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_send` (`campaign_id`,`email`),
  CONSTRAINT `vb_campaign_sends_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `vb_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_carts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `cart_data` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reminder_sent` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_cart` (`user_id`),
  CONSTRAINT `vb_carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vb_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_collections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) NOT NULL,
  `title` varchar(120) NOT NULL,
  `kind` varchar(10) NOT NULL DEFAULT 'seasonal',
  `blurb` varchar(300) NOT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_collection` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_collection_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `product_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_citem` (`collection_id`,`product_id`),
  KEY `vb_idx_citems_product` (`product_id`),
  CONSTRAINT `vb_collection_items_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `vb_collections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vb_collection_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `vb_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_gallery` (
  `id` int NOT NULL AUTO_INCREMENT,
  `image` varchar(255) NOT NULL,
  `handle` varchar(60) DEFAULT NULL,
  `caption` varchar(200) DEFAULT NULL,
  `post_url` varchar(300) DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `vb_idx_gallery_active` (`active`,`sort_order`),
  CONSTRAINT `vb_gallery_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `vb_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_gift_cards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(24) NOT NULL,
  `order_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance` decimal(10,2) NOT NULL,
  `recipient_name` varchar(120) DEFAULT NULL,
  `recipient_email` varchar(190) DEFAULT NULL,
  `message` varchar(300) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_gift` (`code`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `vb_gift_cards_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `vb_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_gift_dates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `label` varchar(80) NOT NULL,
  `who` varchar(12) NOT NULL,
  `occasion` varchar(12) NOT NULL,
  `month` tinyint NOT NULL,
  `day` tinyint NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `vb_gift_dates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vb_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_lifecycle_sent` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `kind` varchar(20) NOT NULL,
  `ref` varchar(40) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_lifecycle` (`email`,`kind`,`ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_mail_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mail_type` varchar(30) NOT NULL,
  `recipient` varchar(190) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `status` varchar(10) NOT NULL,
  `order_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vb_idx_maillog_order` (`order_id`,`mail_type`),
  KEY `vb_idx_maillog_created` (`created_at`),
  KEY `vb_idx_maillog_type` (`mail_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `topic` varchar(20) NOT NULL DEFAULT 'general',
  `order_ref` varchar(40) DEFAULT NULL,
  `message` text NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'new',
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `handled_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vb_idx_messages_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_ref` varchar(40) NOT NULL,
  `user_id` int DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(80) NOT NULL,
  `region` varchar(80) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `promo_code` varchar(30) DEFAULT NULL,
  `shipping` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `delivery_estimate` varchar(60) DEFAULT NULL,
  `payment_method` varchar(20) NOT NULL,
  `pay_channel` varchar(20) DEFAULT NULL,
  `confirmation_sent` tinyint(1) NOT NULL DEFAULT '0',
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `order_status` varchar(20) NOT NULL DEFAULT 'pending',
  `paystack_reference` varchar(80) DEFAULT NULL,
  `courier` varchar(60) DEFAULT NULL,
  `tracking_no` varchar(80) DEFAULT NULL,
  `tracking_url` varchar(255) DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `feedback_sent_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `kind` varchar(10) NOT NULL DEFAULT 'shop',
  `points_used` int NOT NULL DEFAULT '0',
  `points_discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `gift_code` varchar(24) DEFAULT NULL,
  `gift_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `whatsapp_optin` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_ref` (`order_ref`),
  KEY `vb_idx_orders_user` (`user_id`),
  KEY `vb_idx_orders_status` (`order_status`),
  KEY `vb_idx_orders_email` (`email`),
  KEY `vb_idx_orders_pay` (`payment_status`,`order_status`),
  KEY `vb_idx_orders_created` (`created_at`),
  CONSTRAINT `vb_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vb_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_order_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `event` varchar(30) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vb_idx_events_order` (`order_id`),
  CONSTRAINT `vb_order_events_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `vb_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `product_name` varchar(160) NOT NULL,
  `size` varchar(20) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `quantity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `vb_idx_items_order` (`order_id`),
  KEY `vb_idx_items_product` (`product_id`),
  CONSTRAINT `vb_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `vb_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vb_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `vb_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_reset` (`token_hash`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `vb_password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vb_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_points` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `points` int NOT NULL,
  `reason` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_points` (`user_id`,`reason`),
  CONSTRAINT `vb_points_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vb_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `name` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int NOT NULL DEFAULT '0',
  `metal` varchar(40) NOT NULL DEFAULT 'Gold tone',
  `image` varchar(255) NOT NULL,
  `specs` text,
  `sizes` varchar(120) DEFAULT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sale_ends` date DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `materials` varchar(200) DEFAULT NULL,
  `materials_verified` tinyint(1) NOT NULL DEFAULT '0',
  `stone_color` varchar(80) DEFAULT NULL,
  `low_stock_at` int DEFAULT NULL,
  `low_alerted` tinyint(1) NOT NULL DEFAULT '0',
  `featured` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `vb_idx_products_category` (`category_id`),
  KEY `vb_idx_products_featured` (`featured`,`active`),
  KEY `vb_idx_products_active` (`active`,`created_at`),
  CONSTRAINT `vb_products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `vb_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_product_views` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `viewed_on` date NOT NULL,
  `views` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_view` (`product_id`,`viewed_on`),
  CONSTRAINT `vb_product_views_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `vb_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_promos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `kind` varchar(10) NOT NULL DEFAULT 'percent',
  `amount` decimal(10,2) NOT NULL,
  `min_subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `max_uses` int DEFAULT NULL,
  `used_count` int NOT NULL DEFAULT '0',
  `expires_on` date DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_promo` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_refunds` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(40) NOT NULL,
  `method` varchar(20) NOT NULL,
  `reference` varchar(80) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `restock` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `admin_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vb_idx_refunds_order` (`order_id`),
  KEY `vb_idx_refunds_status` (`status`),
  CONSTRAINT `vb_refunds_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `vb_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` tinyint NOT NULL,
  `comment` varchar(600) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_review` (`product_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `vb_idx_reviews_created` (`created_at`),
  CONSTRAINT `vb_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `vb_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vb_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `vb_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_site_stats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `day` date NOT NULL,
  `metric` varchar(24) NOT NULL,
  `ref` varchar(120) NOT NULL DEFAULT '',
  `n` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_stat` (`day`,`metric`,`ref`),
  KEY `vb_idx_stats_metric` (`metric`,`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_stock_alerts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `email` varchar(190) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_stock_alert` (`product_id`,`email`),
  CONSTRAINT `vb_stock_alerts_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `vb_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_stock_moves` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `qty_change` int NOT NULL,
  `balance` int NOT NULL,
  `reason` varchar(12) NOT NULL,
  `note` varchar(160) DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `admin_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vb_idx_moves_product` (`product_id`),
  KEY `vb_idx_moves_created` (`created_at`),
  CONSTRAINT `vb_stock_moves_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `vb_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_subscribers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `unsub_token` varchar(40) DEFAULT NULL,
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'newsletter',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_throttle` (
  `k` varchar(64) NOT NULL,
  `hits` int NOT NULL DEFAULT '0',
  `reset_at` int NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `referral_code` varchar(12) DEFAULT NULL,
  `referred_by` int DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `birthday_used_year` int DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `verify_token` char(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `vb_uniq_referral` (`referral_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_wa_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `event` varchar(24) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `attempts` int NOT NULL DEFAULT '1',
  `detail` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_wa` (`order_id`,`event`),
  CONSTRAINT `vb_wa_log_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `vb_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `vb_wishlist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vb_uniq_wish` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `vb_wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `vb_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vb_wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `vb_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `vb_users` (`name`,`email`,`phone`,`password_hash`,`role`,`email_verified_at`) VALUES
('Velora Admin','admin@velorabellmont.com','0200000000','$2y$10$V9nC/kHqfa4yvrhgV/67nuJ6A0QNRgw6vwo6FSoQaLTbP7.JYtBVi','admin',NOW());

INSERT INTO `vb_categories` (`id`, `name`, `slug`, `sort_order`) VALUES
(1, 'Necklaces', 'necklaces', 1),
(2, 'Bracelets', 'bracelets', 2),
(3, 'Rings', 'rings', 3),
(4, 'Earrings', 'earrings', 4),
(5, 'Statement pieces', 'statement-pieces', 5);

INSERT INTO `vb_products` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `stock`, `metal`, `image`, `specs`, `sizes`, `sale_price`, `sale_ends`, `cost_price`, `materials`, `materials_verified`, `stone_color`, `low_stock_at`, `low_alerted`, `featured`, `active`, `created_at`) VALUES
(1, 1, 'Solène Pendant', 'solene-pendant', 'A fine cable chain holding a teardrop crystal in a polished gold-tone frame. Sits just below the collarbone and works with open collars and high necklines alike. Chain length 45 cm with a 5 cm extender.', 240.00, 13, 'Gold tone', 'img/products/solene-pendant.jpg', 'Finish: Gold tone|Stone: Teardrop emerald-green crystal|Chain: Cable chain, 45 cm with 5 cm extender|Best for: Open collars and high necklines', NULL, NULL, NULL, 132.00, NULL, 0, 'green', NULL, 0, 1, 1, '2026-11-09 15:44:22'),
(2, 1, 'Perle Strand', 'perle-strand', 'A single strand of lustrous cultured-style pearls, individually knotted, with a clasp. A classic for weddings, church and the office. Length 45 cm.', 320.00, 9, 'Pearl', 'img/products/perle-strand.jpg', 'Finish: Silver tone clasp|Beads: Cultured-style pearls, individually knotted|Length: 45 cm|Best for: Weddings, church and the office', NULL, NULL, NULL, 176.00, NULL, 0, 'pearl', NULL, 0, 0, 1, '2026-11-09 15:44:22'),
(3, 1, 'Luna Disc Necklace', 'luna-disc-necklace', 'A smooth polished disc on a delicate gold-tone chain. Light enough to wear every day and layer with other chains. Chain length 42 cm with a 5 cm extender.', 185.00, 20, 'Gold tone', 'img/products/luna-disc-necklace.jpg', 'Finish: Gold tone|Pendant: Smooth polished disc|Chain: Delicate chain, 42 cm with 5 cm extender|Best for: Everyday wear and layering', NULL, NULL, NULL, 101.75, NULL, 0, NULL, NULL, 0, 0, 1, '2026-11-09 15:44:22'),
(4, 2, 'Aria Link Bracelet', 'aria-link-bracelet', 'Interlocking gold-tone links finished with a secure lobster clasp. Wear it alone or stacked with a watch. Fits wrists from 15 to 19 cm.', 160.00, 18, 'Gold tone', 'img/products/aria-link-bracelet.jpg', 'Finish: Gold tone|Style: Interlocking links|Closure: Lobster clasp|Fits wrists: 15 to 19 cm', NULL, NULL, NULL, 88.00, NULL, 0, NULL, NULL, 0, 0, 1, '2026-11-09 15:44:22'),
(5, 2, 'Celeste Tennis Bracelet', 'celeste-tennis-bracelet', 'A continuous line of clear cut-crystal stones set in silver-tone metal with a double safety clasp. Catches the light at dinners, weddings and graduations. Length 18 cm.', 350.00, 7, 'Silver tone', 'img/products/celeste-tennis-bracelet.jpg', 'Finish: Silver tone|Stones: Clear cut crystal, continuous line|Closure: Double safety clasp|Length: 18 cm', NULL, NULL, NULL, 192.50, NULL, 0, 'clear', NULL, 0, 1, 1, '2026-11-09 15:44:22'),
(6, 2, 'Marée Bead Bracelet', 'maree-bead-bracelet', 'Smooth beads on a flexible stretch cord. No clasp to fumble with, and it slides on easily. Fits most wrists.', 130.00, 25, 'Beads', 'img/products/maree-bead-bracelet.jpg', 'Finish: Rose gold tone beads|Fit: Flexible stretch cord, no clasp|Fits: Most wrists', NULL, NULL, NULL, 71.50, NULL, 0, NULL, NULL, 0, 0, 1, '2026-11-09 15:44:22'),
(7, 3, 'Éclat Solitaire Ring', 'eclat-solitaire-ring', 'A brilliant clear crystal raised on a four-prong silver-tone setting with a slim comfortable band. A quiet, classic choice. Available in sizes 5 to 9.', 280.00, 11, 'Silver tone', 'img/products/eclat-solitaire-ring.jpg', 'Finish: Silver tone|Stone: Clear crystal in a four-prong setting|Band: Slim and comfortable|Sizes: 5 to 9', '5,6,7,8,9', NULL, NULL, 154.00, NULL, 0, 'clear', NULL, 0, 0, 1, '2026-11-09 15:44:22'),
(8, 3, 'Obsidienne Signet Ring', 'obsidienne-signet-ring', 'A broad gold-tone signet face with a small onyx-black stone and a raised border. Bold without being heavy. Available in sizes 5 to 10.', 210.00, 13, 'Gold tone', 'img/products/obsidienne-signet-ring.jpg', 'Finish: Gold tone|Stone: Small onyx-black stone, raised border|Face: Broad signet|Sizes: 5 to 10', '5,6,7,8,9,10', NULL, NULL, 115.50, NULL, 0, 'black', NULL, 0, 1, 1, '2026-11-09 15:44:22'),
(9, 3, 'Rosalind Twist Ring', 'rosalind-twist-ring', 'Two slim gold-tone bands that twist together under a sapphire-blue stone, with small clear stones on either side. Available in sizes 5 to 9.', 190.00, 12, 'Gold tone', 'img/products/rosalind-twist-ring.jpg', 'Finish: Gold tone|Stone: Sapphire-blue centre with small clear stones|Band: Two slim twisted bands|Sizes: 5 to 9', '5,6,7,8,9', NULL, NULL, 104.50, NULL, 0, 'blue,clear', NULL, 0, 0, 1, '2026-11-09 15:44:22'),
(10, 4, 'Petite Pearl Studs', 'petite-pearl-studs', 'Round pearl-finish studs in a gold-tone setting with secure butterfly backs. Small enough for every day, polished enough for any occasion.', 95.00, 30, 'Gold tone', 'img/products/petite-pearl-studs.jpg', 'Finish: Gold tone setting|Style: Round pearl-finish studs|Backs: Secure butterfly backs', NULL, NULL, NULL, 52.25, NULL, 0, 'pearl', NULL, 0, 0, 1, '2026-11-09 15:44:22'),
(11, 4, 'Orla Hoops', 'orla-hoops', 'Lightweight polished gold-tone hoops with a hinged closure. Comfortable for all-day wear. Diameter 3 cm.', 140.00, 22, 'Gold tone', 'img/products/orla-hoops.jpg', 'Finish: Polished gold tone|Closure: Hinged|Diameter: 3 cm|Weight: Lightweight for all-day wear', NULL, NULL, NULL, 77.00, NULL, 0, NULL, NULL, 0, 1, 1, '2026-11-09 15:44:22'),
(12, 4, 'Gala Drop Earrings', 'gala-drop-earrings', 'Clear crystal teardrops framed in silver-tone metal and hung from a smaller clear crystal accent. Made for evenings out. Drop length 5 cm.', 210.00, 10, 'Silver tone', 'img/products/gala-drop-earrings.jpg', 'Finish: Silver tone|Stones: Clear crystal teardrops with a clear crystal accent|Drop length: 5 cm|Best for: Evenings out', NULL, NULL, NULL, 115.50, NULL, 0, 'clear', NULL, 0, 0, 1, '2026-11-09 15:44:22'),
(13, 5, 'Aurelia Layered Collar', 'aurelia-layered-collar', 'Three graduated gold-tone chains with scattered clear crystals. One necklace that does the work of a full layered look. Adjustable length 40 to 48 cm.', 480.00, 6, 'Gold tone', 'img/products/aurelia-layered-collar.jpg', 'Finish: Gold tone|Style: Three graduated chains|Stones: Scattered clear crystals|Length: Adjustable 40 to 48 cm', NULL, NULL, NULL, 264.00, NULL, 0, 'clear', NULL, 0, 1, 1, '2026-11-09 15:44:22'),
(14, 5, 'Sovereign Cuff', 'sovereign-cuff', 'A wide curved gold-tone cuff. Open back for an easy fit. Fits wrists from 15 to 18 cm.', 420.00, 5, 'Gold tone', 'img/products/sovereign-cuff.jpg', 'Finish: Gold tone|Stones: Emerald-green centre with a row of clear crystals|Style: Wide curved cuff, open back|Fits wrists: 15 to 18 cm', NULL, NULL, NULL, 231.00, NULL, 0, 'green,clear', NULL, 1, 0, 1, '2026-11-09 15:44:22'),
(15, 5, 'Reine Cocktail Ring', 'reine-cocktail-ring', 'An oversized sapphire-blue stone surrounded by a halo of clear crystals on a wide silver-tone band. For the nights you want to be noticed. Available in sizes 5 to 9.', 360.00, 7, 'Silver tone', 'img/products/reine-cocktail-ring.jpg', 'Finish: Silver tone\r\nStone: Oversized sapphire-blue with a halo of clear crystals\r\nBand: Wide\r\nSizes: 5 to 9', '5,6,7,8,9', NULL, NULL, 198.00, NULL, 0, 'blue,clear', NULL, 0, 1, 1, '2026-11-09 15:44:22');

INSERT INTO `vb_collections` (`id`, `slug`, `title`, `kind`, `blurb`, `starts_on`, `ends_on`, `active`) VALUES
(1, 'christmas-gifts', 'Christmas gifts', 'seasonal', 'Pieces made for giving, wrapped and ready for the season.', '2026-11-01', '2026-12-31', 1),
(2, 'graduation-season', 'Graduation season', 'seasonal', 'Polished pieces to mark a proud day.', NULL, NULL, 1),
(3, 'valentines-day', 'Valentine\'s Day', 'seasonal', 'Gold, pearls and a little sparkle for someone you love.', '2027-01-15', '2027-02-14', 1),
(4, 'mothers-day', 'Mother\'s Day', 'seasonal', 'Thoughtful gifts for the woman who raised you.', '2027-04-15', '2027-05-10', 1),
(5, 'signature-statement', 'The Signature Statement', 'limited', 'A small run of bold pieces. When they sell out, they are gone.', NULL, NULL, 1),
(6, 'wedding-guest', 'Wedding season', 'seasonal', 'Pearls and crystal for the guest, the bridesmaid and the day itself.', NULL, NULL, 1),
(7, 'church-sunday', 'Church and Sunday best', 'seasonal', 'Refined, modest pieces for service, thanksgiving and Sunday lunch.', NULL, NULL, 1);

INSERT INTO `vb_collection_items` (`id`, `collection_id`, `product_id`) VALUES
(4, 1, 2),
(3, 1, 3),
(1, 1, 5),
(5, 1, 10),
(2, 1, 12),
(11, 2, 1),
(8, 2, 4),
(9, 2, 7),
(10, 2, 10),
(15, 3, 3),
(17, 3, 9),
(16, 3, 10),
(26, 4, 2),
(24, 4, 6),
(25, 4, 11),
(21, 5, 13),
(23, 5, 14),
(22, 5, 15),
(30, 6, 2),
(27, 6, 5),
(28, 6, 7),
(31, 6, 10),
(29, 6, 12),
(38, 7, 1),
(36, 7, 2),
(34, 7, 4),
(37, 7, 10),
(35, 7, 11);

INSERT INTO `vb_promos` (`id`, `code`, `kind`, `amount`, `min_subtotal`, `max_uses`, `used_count`, `expires_on`, `active`) VALUES
(1, 'WELCOME10', 'percent', 10.00, 100.00, NULL, 0, NULL, 1),
(2, 'SAVE20', 'fixed', 20.00, 300.00, NULL, 0, NULL, 1);

SET FOREIGN_KEY_CHECKS=1;
