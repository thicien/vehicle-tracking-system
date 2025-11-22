-- CREATE DATABASE
-- You must first create the database 'rwanda_bus_booking' in your environment (e.g., phpMyAdmin or command line)
-- CREATE DATABASE rwanda_bus_booking;
-- USE rwanda_bus_booking;

-- 1. Users Table (Stores all roles)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'company', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Companies Table
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(100) NOT NULL UNIQUE,
    logo_url VARCHAR(255) DEFAULT 'default_logo.png',
    status ENUM('Pending', 'Active', 'Suspended') DEFAULT 'Active',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 3. Routes Table (Fixed routes like Kigali to Gisenyi)
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    departure_city VARCHAR(50) NOT NULL,
    arrival_city VARCHAR(50) NOT NULL,
    price_base DECIMAL(10, 2) NOT NULL 
);

-- 4. Buses Table (Owned by a Company)
CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    bus_name VARCHAR(50) NOT NULL, -- Plate number e.g., KN-456-R
    total_seats INT NOT NULL,
    image_url VARCHAR(255),
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- 5. Schedules Table (Links a Bus, Route, and Time)
CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    route_id INT NOT NULL,
    departure_time TIME NOT NULL,
    price DECIMAL(10, 2) NOT NULL, -- Final ticket price
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (route_id) REFERENCES routes(id)
);

-- 6. Bookings Table (The transaction record)
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    schedule_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    booking_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Confirmed', 'Cancelled', 'Pending') DEFAULT 'Confirmed',
    -- Ensures the seat is unique per schedule
    UNIQUE KEY unique_seat_schedule (schedule_id, seat_number), 
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id)
);

-- 7. Activity Log (For Admin Oversight)
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    role VARCHAR(50),
    action TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- --- SAMPLE DATA INSERTS ---

-- Hash for 'password123'
SET @hashed_password = '$2y$10$tM96.4.V1t8U2c8Q1jY4iO6k0vF3n2jP7w5A4sD0B6G8H7I9J0K'; 

-- Sample Users: 1 Admin, 1 Company (Volcano), 1 User (Aline)
INSERT INTO users (username, email, password_hash, role) VALUES 
('Kagabo Theoneste', 'admin@system.com', @hashed_password, 'admin'),
('Volcano Admin', 'volcano@bus.com', @hashed_password, 'company'),
('Aline Uwimana', 'aline.uwimana@mail.com', @hashed_password, 'user');

-- Sample Company (linked to user_id 2)
INSERT INTO companies (user_id, company_name) VALUES 
(2, 'Volcano Express');

-- Sample Routes
INSERT INTO routes (departure_city, arrival_city, price_base) VALUES 
('Kigali', 'Gisenyi (Rubavu)', 5000.00),
('Kigali', 'Huye', 4000.00),
('Gisenyi (Rubavu)', 'Kigali', 5000.00);

-- Sample Bus (owned by Volcano Express - company_id 1)
INSERT INTO buses (company_id, bus_name, total_seats, image_url) VALUES 
(1, 'KN-456-R', 45, 'bus_volcano_1.jpg');

-- Sample Schedule (Kigali to Gisenyi, 08:00 AM)
INSERT INTO schedules (bus_id, route_id, departure_time, price) VALUES 
(1, 1, '08:00:00', 5500.00);

-- Sample Booking (User Aline books seat A1 on the 08:00 schedule - schedule_id 1)
INSERT INTO bookings (user_id, schedule_id, seat_number) VALUES 
(3, 1, 'A1');