-- Database Schema for Birzeit Flat Rent

-- Users table to store all types of users (customers, owners, managers)
CREATE TABLE users (
    user_id VARCHAR(9) PRIMARY KEY,
    national_id VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    address_flat VARCHAR(50) NOT NULL,
    address_street VARCHAR(100) NOT NULL,
    address_city VARCHAR(50) NOT NULL,
    address_postal VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    mobile VARCHAR(20) NOT NULL,
    telephone VARCHAR(20),
    user_type ENUM('customer', 'owner', 'manager') NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    registration_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    profile_photo VARCHAR(255) DEFAULT 'images/default_profile.png'
);

-- Additional details for owners
CREATE TABLE owner_details (
    owner_id VARCHAR(9) PRIMARY KEY,
    bank_name VARCHAR(100) NOT NULL,
    bank_branch VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Flats table
CREATE TABLE flats (
    flat_ref VARCHAR(6) PRIMARY KEY,
    owner_id VARCHAR(9) NOT NULL,
    location VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    monthly_cost DECIMAL(10, 2) NOT NULL,
    available_from DATE NOT NULL,
    available_to DATE NOT NULL,
    bedrooms INT NOT NULL,
    bathrooms INT NOT NULL,
    size_sqm DECIMAL(8, 2) NOT NULL,
    has_heating BOOLEAN DEFAULT FALSE,
    has_ac BOOLEAN DEFAULT FALSE,
    has_access_control BOOLEAN DEFAULT FALSE,
    has_parking BOOLEAN DEFAULT FALSE,
    has_backyard ENUM('none', 'individual', 'shared') DEFAULT 'none',
    has_playground BOOLEAN DEFAULT FALSE,
    has_storage BOOLEAN DEFAULT FALSE,
    is_furnished BOOLEAN DEFAULT FALSE,
    rental_conditions TEXT,
    status ENUM('pending', 'approved', 'rented', 'unavailable') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Flat photos
CREATE TABLE flat_photos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_ref VARCHAR(6) NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (flat_ref) REFERENCES flats(flat_ref) ON DELETE CASCADE
);

-- Marketing information for flats
CREATE TABLE marketing_info (
    info_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_ref VARCHAR(6) NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    url VARCHAR(255),
    FOREIGN KEY (flat_ref) REFERENCES flats(flat_ref) ON DELETE CASCADE
);

-- Viewing appointments
CREATE TABLE viewing_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_ref VARCHAR(6) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    FOREIGN KEY (flat_ref) REFERENCES flats(flat_ref) ON DELETE CASCADE
);

-- Booked appointments
CREATE TABLE appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    slot_id INT NOT NULL,
    customer_id VARCHAR(9) NOT NULL,
    appointment_date DATE NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'completed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (slot_id) REFERENCES viewing_slots(slot_id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Rentals
CREATE TABLE rentals (
    rental_id INT AUTO_INCREMENT PRIMARY KEY,
    flat_ref VARCHAR(6) NOT NULL,
    customer_id VARCHAR(9) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_cost DECIMAL(10, 2) NOT NULL,
    payment_card_number VARCHAR(9) NOT NULL,
    payment_card_expiry VARCHAR(7) NOT NULL,
    payment_card_name VARCHAR(100) NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (flat_ref) REFERENCES flats(flat_ref) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Messages
CREATE TABLE messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id VARCHAR(9) NOT NULL,
    receiver_id VARCHAR(9) NOT NULL,
    title VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    related_entity_type ENUM('flat', 'appointment', 'rental') NULL,
    related_entity_id VARCHAR(20) NULL,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Shopping basket (temporary rentals)
CREATE TABLE basket_items (
    basket_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(9) NOT NULL,
    flat_ref VARCHAR(6) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (flat_ref) REFERENCES flats(flat_ref) ON DELETE CASCADE
);

-- Insert a manager account for testing
INSERT INTO users (user_id, national_id, name, address_flat, address_street, address_city, address_postal, date_of_birth, email, mobile, telephone, user_type, username, password)
VALUES ('100000000', 'M12345678', 'System Manager', '1', 'Admin Street', 'Ramallah', '00000', '1980-01-01', 'manager@birzeitflat.com', '0599123456', '02-2951234', 'manager', 'manager@birzeitflat.com', '$2y$10$GkVbVJ.LSoF0LZ9QhB5CB.6FUJsHLCMnHXJXMJJXxCuNTkxVEZfsm'); -- Password: 1manager
