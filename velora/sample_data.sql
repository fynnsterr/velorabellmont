SET NAMES utf8mb4;

INSERT INTO vb_users (name, email, phone, password_hash, role, email_verified_at) VALUES
('Ama Boateng', 'ama.boateng@example.com', '0240000001', '$2y$10$oJUWmlrg2dkDZkVtsO6kOOqTKoz0wwCP3fbvaJ0mW21vfPKFpXj.e', 'customer', NOW()),
('Kofi Mensah', 'kofi.mensah@example.com', '0240000002', '$2y$10$oJUWmlrg2dkDZkVtsO6kOOqTKoz0wwCP3fbvaJ0mW21vfPKFpXj.e', 'customer', NOW());

INSERT INTO vb_reviews (product_id, user_id, rating, comment, photo, created_at) VALUES
(15, 2, 5, 'Beautiful ring. The blue stone looks even better in person and it arrived well packed.', NULL, '2026-11-12 10:15:00'),
(1, 3, 4, 'Lovely pendant, sits nicely below the collarbone. The chain could be a little longer.', NULL, '2026-11-13 18:40:00');

INSERT INTO vb_points (user_id, points, reason, created_at) VALUES
(2, 20, 'order:VB-SAMPLE-0001', '2026-11-10 20:05:00'),
(2, 10, 'review:15', '2026-11-12 10:15:00');

INSERT INTO vb_gift_cards (code, order_id, amount, balance, recipient_name, recipient_email, message, status, created_at) VALUES
('VG-K7M2-9QX4', NULL, 100.00, 100.00, 'Abena Owusu', 'abena@example.com', 'Happy birthday Abena, pick something you love.', 'active', '2026-11-14 12:00:00'),
('VG-R5T8-3WNP', NULL, 200.00, 150.00, 'Kojo Asante', 'kojo@example.com', 'Congratulations on graduating.', 'active', '2026-11-15 09:30:00');

INSERT INTO vb_gift_dates (user_id, label, who, occasion, month, day, created_at) VALUES
(2, 'Mum', 'mum', 'birthday', 3, 14, '2026-11-12 11:00:00'),
(2, 'Esi', 'partner', 'anniversary', 6, 2, '2026-11-12 11:02:00');

INSERT INTO vb_stock_alerts (product_id, email, created_at, notified_at) VALUES
(15, 'guest.one@example.com', '2026-11-17 14:20:00', NULL),
(14, 'kofi.mensah@example.com', '2026-11-18 08:05:00', '2026-11-19 09:00:00');

INSERT INTO vb_password_resets (user_id, token_hash, expires_at, used_at, created_at) VALUES
(2, '3f1c9a5e7b2d4086a1c3e5f7092b4d6e8f0a1c3e5b7d9f2a4c6e8081a3c5e7f9', '2026-11-20 10:30:00', '2026-11-20 10:05:00', '2026-11-20 09:30:00'),
(3, 'b82d4f6a8c0e1357d9b1f3a5c7e9024681acd3e5f7092b4d6e8f0a1c3e5b7d9f', '2026-11-23 18:00:00', NULL, '2026-11-22 17:00:00');

INSERT INTO vb_messages (name, email, phone, topic, order_ref, message, status, user_id, created_at, handled_at) VALUES
('Guest One', 'guest.one@example.com', '0240000003', 'order', 'VB-SAMPLE-0001', 'Hi, can you hold the Sovereign Cuff for me until Friday?', 'replied', NULL, '2026-11-19 17:30:00', '2026-11-19 18:10:00'),
('Kofi Mensah', 'kofi.mensah@example.com', '0240000002', 'product', NULL, 'Do you have the Reine Cocktail Ring in size 7?', 'new', 3, '2026-11-21 13:45:00', NULL);
