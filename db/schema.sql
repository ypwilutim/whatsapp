CREATE TABLE IF NOT EXISTS agents (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL,
    username   VARCHAR(50)  UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    avatar     VARCHAR(255) DEFAULT NULL,
    is_online  TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nomor_wa   VARCHAR(20)  UNIQUE NOT NULL,
    nama       VARCHAR(100),
    last_seen  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chats (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    agent_id    INT NULL,
    customer_id INT NOT NULL,
    status      ENUM('open', 'closed', 'waiting') DEFAULT 'open',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at   TIMESTAMP NULL,
    FOREIGN KEY (agent_id)    REFERENCES agents(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    chat_id         INT NOT NULL,
    sender_type     ENUM('customer', 'agent') NOT NULL,
    pesan           TEXT NOT NULL,
    tipe_pesan      ENUM('text', 'image', 'file', 'button') DEFAULT 'text',
    is_wa_sent      TINYINT(1) DEFAULT 0,
    whacenter_msg_id VARCHAR(100),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE
);

-- Default CS admin (run: wa.ypwilutim.com/setup.php once after importing schema)
-- DELETE setup.php after use! Admin pass: admin123
