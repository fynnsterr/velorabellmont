SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS vb_order_items;
DROP TABLE IF EXISTS vb_orders;
DROP TABLE IF EXISTS vb_products;
DROP TABLE IF EXISTS vb_categories;
DROP TABLE IF EXISTS vb_users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE vb_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(10) NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vb_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vb_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    metal VARCHAR(40) NOT NULL DEFAULT 'Gold tone',
    image VARCHAR(255) NOT NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES vb_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vb_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_ref VARCHAR(40) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(80) NOT NULL,
    region VARCHAR(80) NOT NULL,
    notes VARCHAR(500) NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    shipping DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    order_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    paystack_reference VARCHAR(80) NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES vb_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vb_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(160) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES vb_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES vb_products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX vb_idx_products_category ON vb_products (category_id);
CREATE INDEX vb_idx_products_featured ON vb_products (featured, active);
CREATE INDEX vb_idx_orders_user ON vb_orders (user_id);
CREATE INDEX vb_idx_orders_status ON vb_orders (order_status);
CREATE INDEX vb_idx_items_order ON vb_order_items (order_id);

INSERT INTO vb_users (name, email, phone, password_hash, role) VALUES
('Velora Admin', 'admin@velorabellmont.com', '0200000000', '$2y$10$V9nC/kHqfa4yvrhgV/67nuJ6A0QNRgw6vwo6FSoQaLTbP7.JYtBVi', 'admin');

INSERT INTO vb_categories (id, name, slug, sort_order) VALUES
(1, 'Necklaces', 'necklaces', 1),
(2, 'Bracelets', 'bracelets', 2),
(3, 'Rings', 'rings', 3),
(4, 'Earrings', 'earrings', 4),
(5, 'Statement pieces', 'statement-pieces', 5);

INSERT INTO vb_products (category_id, name, slug, description, price, stock, metal, image, featured) VALUES
(1, 'Solène Pendant', 'solene-pendant', 'A fine cable chain holding a teardrop emerald-green crystal in a polished gold-tone frame. Sits just below the collarbone and works with open collars and high necklines alike. Chain length 45 cm with a 5 cm extender.', 240.00, 14, 'Gold tone', 'img/products/solene-pendant.svg', 1),
(1, 'Perle Strand', 'perle-strand', 'A single strand of lustrous cultured-style pearls, individually knotted, with a silver-tone clasp. A classic for weddings, church and the office. Length 45 cm.', 320.00, 9, 'Silver tone', 'img/products/perle-strand.svg', 0),
(1, 'Luna Disc Necklace', 'luna-disc-necklace', 'A smooth polished disc on a delicate rose gold-tone chain. Light enough to wear every day and layer with other chains. Chain length 42 cm with a 5 cm extender.', 185.00, 20, 'Rose gold tone', 'img/products/luna-disc-necklace.svg', 0),
(2, 'Aria Link Bracelet', 'aria-link-bracelet', 'Interlocking gold-tone links finished with a secure lobster clasp. Wear it alone or stacked with a watch. Fits wrists from 15 to 19 cm.', 160.00, 18, 'Gold tone', 'img/products/aria-link-bracelet.svg', 0),
(2, 'Celeste Tennis Bracelet', 'celeste-tennis-bracelet', 'A continuous line of clear cut-crystal stones set in silver-tone metal with a double safety clasp. Catches the light at dinners, weddings and graduations. Length 18 cm.', 350.00, 7, 'Silver tone', 'img/products/celeste-tennis-bracelet.svg', 1),
(2, 'Marée Bead Bracelet', 'maree-bead-bracelet', 'Smooth rose gold-tone beads on a flexible stretch cord. No clasp to fumble with, and it slides on easily. Fits most wrists.', 130.00, 25, 'Rose gold tone', 'img/products/maree-bead-bracelet.svg', 0),
(3, 'Éclat Solitaire Ring', 'eclat-solitaire-ring', 'A brilliant clear crystal raised on a four-prong silver-tone setting with a slim comfortable band. A quiet, classic choice. Available in sizes 5 to 9.', 280.00, 11, 'Silver tone', 'img/products/eclat-solitaire-ring.svg', 0),
(3, 'Obsidienne Signet Ring', 'obsidienne-signet-ring', 'A broad gold-tone signet face with a small onyx-black stone and a raised border. Bold without being heavy. Available in sizes 5 to 10.', 210.00, 13, 'Gold tone', 'img/products/obsidienne-signet-ring.svg', 1),
(3, 'Rosalind Twist Ring', 'rosalind-twist-ring', 'Two slim rose gold-tone bands that twist together under a sapphire-blue stone, with small clear stones on either side. Available in sizes 5 to 9.', 190.00, 12, 'Rose gold tone', 'img/products/rosalind-twist-ring.svg', 0),
(4, 'Petite Pearl Studs', 'petite-pearl-studs', 'Round pearl-finish studs in a gold-tone setting with secure butterfly backs. Small enough for every day, polished enough for any occasion.', 95.00, 30, 'Gold tone', 'img/products/petite-pearl-studs.svg', 0),
(4, 'Orla Hoops', 'orla-hoops', 'Lightweight polished gold-tone hoops with a hinged closure. Comfortable for all-day wear. Diameter 3 cm.', 140.00, 22, 'Gold tone', 'img/products/orla-hoops.svg', 1),
(4, 'Gala Drop Earrings', 'gala-drop-earrings', 'Sapphire-blue teardrop stones framed in silver-tone metal and hung from a clear crystal accent. Made for evenings out. Drop length 5 cm.', 210.00, 10, 'Silver tone', 'img/products/gala-drop-earrings.svg', 0),
(5, 'Aurelia Layered Collar', 'aurelia-layered-collar', 'Three graduated gold-tone chains with scattered clear crystals and a ruby-red teardrop centrepiece. One necklace that does the work of a full layered look. Adjustable length 40 to 48 cm.', 480.00, 6, 'Gold tone', 'img/products/aurelia-layered-collar.svg', 1),
(5, 'Sovereign Cuff', 'sovereign-cuff', 'A wide curved gold-tone cuff with an emerald-green centre stone and a row of clear crystals. Open back for an easy fit. Fits wrists from 15 to 18 cm.', 420.00, 5, 'Gold tone', 'img/products/sovereign-cuff.svg', 0),
(5, 'Reine Cocktail Ring', 'reine-cocktail-ring', 'An oversized sapphire-blue stone surrounded by a halo of clear crystals on a wide gold-tone band. For the nights you want to be noticed. Available in sizes 5 to 9.', 360.00, 8, 'Gold tone', 'img/products/reine-cocktail-ring.svg', 1);
