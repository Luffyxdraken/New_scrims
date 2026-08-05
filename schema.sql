CREATE DATABASE IF NOT EXISTS pirtaes_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pirtaes_db;

-- 1. Website Settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(100) DEFAULT 'pirtaes.co',
    support_name VARCHAR(100) DEFAULT 'Pirtaes Support',
    support_whatsapp VARCHAR(20) DEFAULT '+919876543210',
    logo_path VARCHAR(255) DEFAULT 'assets/img/logo.png',
    banner_path VARCHAR(255) DEFAULT 'assets/img/banner.jpg',
    default_point_system JSON,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (site_name, default_point_system) VALUES (
    'pirtaes.co',
    '{"1": 10, "2": 6, "3": 4, "4": 2, "5": 2, "6": 2, "7": 2, "8": 2, "9": 2, "10": 2, "kill": 1}'
);

-- 2. Admin Users
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default Admin (User: admin | Pass: admin123)
INSERT INTO admins (username, password_hash) 
VALUES ('admin', '$2y$10$w4LqH/Vq9Zt7e9gS4y7X4.rN/3k9085rM1aNfO5G/kS8nZlUaZ8yG');

-- 3. Games
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

INSERT INTO games (slug, name) VALUES ('bgmi', 'BGMI'), ('free_fire', 'Free Fire');

-- 4. Scrims
CREATE TABLE scrims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    banner_path VARCHAR(255) NOT NULL,
    type ENUM('Solo', 'Duo', 'Squad') NOT NULL,
    total_slots INT NOT NULL,
    mode ENUM('Free', 'Paid') NOT NULL DEFAULT 'Free',
    entry_fee DECIMAL(10,2) DEFAULT 0.00,
    prize_pool VARCHAR(100) NOT NULL,
    scrim_date DATE NOT NULL,
    scrim_time TIME NOT NULL,
    map_name VARCHAR(50) NOT NULL,
    reg_type ENUM('Auto', 'Manual') DEFAULT 'Auto',
    status ENUM('Upcoming', 'Live', 'Completed') DEFAULT 'Upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);

-- 5. Players / Teams
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    in_game_id VARCHAR(50) NOT NULL UNIQUE, -- BGMI ID or FF UID
    whatsapp VARCHAR(20) NOT NULL,
    ign VARCHAR(50) NOT NULL,
    player_name VARCHAR(100) NOT NULL,
    profile_ss VARCHAR(255) NULL,
    status ENUM('active', 'banned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Registrations
CREATE TABLE registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scrim_id INT NOT NULL,
    player_id INT NOT NULL,
    team_name VARCHAR(100) DEFAULT NULL,
    slot_number INT NULL,
    payment_status ENUM('Approved', 'Pending', 'Rejected') DEFAULT 'Pending',
    payment_ss VARCHAR(255) NULL,
    txn_id VARCHAR(100) NULL,
    rejection_reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scrim_id) REFERENCES scrims(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_registration (scrim_id, player_id),
    UNIQUE KEY unique_slot (scrim_id, slot_number)
);

-- 7. Team Members (For Duo/Squad)
CREATE TABLE team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    player_number INT NOT NULL, -- 1, 2, 3, 4
    ign VARCHAR(50) NOT NULL,
    in_game_id VARCHAR(50) NOT NULL,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
);

-- 8. Matches
CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scrim_id INT NOT NULL,
    match_number INT NOT NULL,
    room_id VARCHAR(50) DEFAULT NULL,
    room_password VARCHAR(50) DEFAULT NULL,
    start_time DATETIME NOT NULL,
    status ENUM('Scheduled', 'Ongoing', 'Finished') DEFAULT 'Scheduled',
    FOREIGN KEY (scrim_id) REFERENCES scrims(id) ON DELETE CASCADE
);

-- 9. Match Results / Points
CREATE TABLE match_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    registration_id INT NOT NULL,
    placement INT NOT NULL,
    kills INT NOT NULL DEFAULT 0,
    placement_points INT NOT NULL DEFAULT 0,
    kill_points INT NOT NULL DEFAULT 0,
    total_points INT NOT NULL DEFAULT 0,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_match_entry (match_id, registration_id)
);

-- 10. FAQs (AI Helpline System)
CREATE TABLE faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    keywords VARCHAR(255) NOT NULL
);

INSERT INTO faqs (question, answer, keywords) VALUES 
('How to get Room ID?', 'Room ID & Password will be visible on your Player Dashboard 10 minutes before match start.', 'room, id, password, timing'),
('How are payments verified?', 'Paid registrations require a transaction ID and payment screenshot. Admins verify them within 30 minutes.', 'payment, paid, verify, status');

-- 11. Public Notices
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
