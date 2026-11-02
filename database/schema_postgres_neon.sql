-- ============================================================
-- ThumWadThaiHub — Full PostgreSQL / Neon Database Schema
-- Ready to run directly in Neon SQL Editor (https://console.neon.tech)
-- ============================================================

CREATE TABLE IF NOT EXISTS temples (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    province VARCHAR(100),
    district VARCHAR(100),
    address TEXT,
    description TEXT,
    tags TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    image VARCHAR(255),
    promptpay_id VARCHAR(13),
    badge VARCHAR(50),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS temple_sections (
    id SERIAL PRIMARY KEY,
    temple_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_sections_temple ON temple_sections(temple_id);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    temple_id INT,
    profile_image VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_users_temple ON users(temple_id);

CREATE TABLE IF NOT EXISTS bookable_items (
    id SERIAL PRIMARY KEY,
    temple_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_qty INT NOT NULL DEFAULT 1,
    is_active VARCHAR(5) NOT NULL DEFAULT 'yes',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_items_temple ON bookable_items(temple_id);

CREATE TABLE IF NOT EXISTS bookings (
    id SERIAL PRIMARY KEY,
    temple_id INT NOT NULL,
    item_id INT NOT NULL,
    user_id INT,
    booking_date DATE NOT NULL,
    stall_no VARCHAR(20),
    qty_booked INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    contact_name VARCHAR(100) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    admin_note TEXT,
    cancelled_by VARCHAR(20),
    cancelled_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_bookings_temple ON bookings(temple_id);
CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings(user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_item ON bookings(item_id);
CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings(booking_date);

CREATE TABLE IF NOT EXISTS donations (
    id SERIAL PRIMARY KEY,
    temple_id INT,
    amount DECIMAL(12, 2) NOT NULL,
    donor_name VARCHAR(100) NOT NULL DEFAULT 'ผู้ไม่ประสงค์ออกนาม',
    anonymous SMALLINT NOT NULL DEFAULT 0,
    tax_deduction VARCHAR(5) NOT NULL DEFAULT 'no',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    slip_image VARCHAR(255),
    receipt_no VARCHAR(50),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_donations_temple_created ON donations(temple_id, created_at);

CREATE TABLE IF NOT EXISTS events (
    id SERIAL PRIMARY KEY,
    temple_id INT NOT NULL,
    event_name VARCHAR(200) NOT NULL,
    description TEXT,
    event_image VARCHAR(255),
    event_date DATE NOT NULL,
    event_time TIME,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_events_temple_date ON events(temple_id, event_date);

CREATE TABLE IF NOT EXISTS news (
    id SERIAL PRIMARY KEY,
    temple_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_news_temple ON news(temple_id);

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    ref_id INT,
    ref_table VARCHAR(50),
    message TEXT NOT NULL,
    is_read SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_notif_user_read ON notifications(user_id, is_read, created_at);
