CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'company', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(100) NOT NULL UNIQUE,
    logo_url VARCHAR(255) DEFAULT 'default_logo.png',
    status ENUM('Pending', 'Active', 'Suspended') DEFAULT 'Active',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    departure_city VARCHAR(50) NOT NULL,
    arrival_city VARCHAR(50) NOT NULL,
    price_base DECIMAL(10, 2) NOT NULL 
);

CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    bus_name VARCHAR(50) NOT NULL, 
    total_seats INT NOT NULL,
    image_url VARCHAR(255),
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    route_id INT NOT NULL,
    departure_time TIME NOT NULL,
    price DECIMAL(10, 2) NOT NULL, 
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (route_id) REFERENCES routes(id)
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    schedule_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    booking_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Confirmed', 'Cancelled', 'Pending') DEFAULT 'Confirmed',
    UNIQUE KEY unique_seat_schedule (schedule_id, seat_number), 
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id)
);

CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    role VARCHAR(50),
    action TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

SET @hashed_password = '$2y$10$tM96.4.V1t8U2c8Q1jY4iO6k0vF3n2jP7w5A4sD0B6G8H7I9J0K'; 

INSERT INTO users (username, email, password_hash, role) VALUES 
('Kagabo Theoneste', 'admin@system.com', @hashed_password, 'admin'),
('Volcano Admin', 'volcano@bus.com', @hashed_password, 'company'),
('Aline Uwimana', 'aline.uwimana@mail.com', @hashed_password, 'user');

INSERT INTO companies (user_id, company_name) VALUES 
(2, 'Volcano Express');

INSERT INTO routes (departure_city, arrival_city, price_base) VALUES 
('Kigali', 'Gisenyi (Rubavu)', 5000.00),
('Kigali', 'Huye', 4000.00),
('Gisenyi (Rubavu)', 'Kigali', 5000.00);

INSERT INTO buses (company_id, bus_name, total_seats, image_url) VALUES 
(1, 'KN-456-R', 45, 'bus_volcano_1.jpg');

INSERT INTO schedules (bus_id, route_id, departure_time, price) VALUES 
(1, 1, '08:00:00', 5500.00);

INSERT INTO bookings (user_id, schedule_id, seat_number) VALUES 
(3, 1, 'A1');