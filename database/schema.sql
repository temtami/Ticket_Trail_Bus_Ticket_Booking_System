CREATE DATABASE IF NOT EXISTS ticket_trail CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ticket_trail;

DROP TABLE IF EXISTS booking_seats, bookings, schedules, buses, bus_types, routes, users;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(60)  NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  phone         VARCHAR(20)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE bus_types (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name      VARCHAR(40) NOT NULL UNIQUE,
  surcharge INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE buses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reg_no      VARCHAR(20) NOT NULL UNIQUE,
  name        VARCHAR(60) NOT NULL,
  bus_type_id INT UNSIGNED NOT NULL,
  total_seats TINYINT UNSIGNED NOT NULL,
  CONSTRAINT chk_seats CHECK (total_seats BETWEEN 8 AND 60),
  FOREIGN KEY (bus_type_id) REFERENCES bus_types(id)
) ENGINE=InnoDB;

CREATE TABLE routes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source      VARCHAR(50) NOT NULL,
  destination VARCHAR(50) NOT NULL,
  distance_km SMALLINT UNSIGNED NOT NULL,
  base_fare   INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_route (source, destination),
  CONSTRAINT chk_route CHECK (source <> destination)
) ENGINE=InnoDB;

CREATE TABLE schedules (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bus_id    INT UNSIGNED NOT NULL,
  route_id  INT UNSIGNED NOT NULL,
  departure DATETIME NOT NULL,
  fare      INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_bus_departure (bus_id, departure),
  KEY idx_departure (departure),
  FOREIGN KEY (bus_id)   REFERENCES buses(id),
  FOREIGN KEY (route_id) REFERENCES routes(id)
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  schedule_id  INT UNSIGNED NOT NULL,
  seat_count   TINYINT UNSIGNED NOT NULL,
  total_amount INT UNSIGNED NOT NULL,
  payment_ref  VARCHAR(20) NOT NULL UNIQUE,
  status       ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (schedule_id) REFERENCES schedules(id)
) ENGINE=InnoDB;

-- The primary key (schedule_id, seat_no) makes double-booking a seat impossible.
CREATE TABLE booking_seats (
  booking_id  INT UNSIGNED NOT NULL,
  schedule_id INT UNSIGNED NOT NULL,
  seat_no     TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (schedule_id, seat_no),
  KEY idx_booking (booking_id),
  FOREIGN KEY (booking_id)  REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (schedule_id) REFERENCES schedules(id)
) ENGINE=InnoDB;

-- ---------- Demo data ----------
INSERT INTO bus_types (name, surcharge) VALUES
('AC Sleeper', 400), ('Non-AC Sleeper', 250), ('AC Seater', 200), ('Non-AC Seater', 0);

INSERT INTO routes (source, destination, distance_km, base_fare) VALUES
('chittagong', 'Sylhet',    145, 250),
('Munshiganj',    'mymensingh', 145, 250),
('sylhet', 'Kustia', 350, 550),
('Dhaka', 'Rajshahi', 350, 550),
('Rajshahi', 'Sylhet', 420, 600),
('Khulna', 'Dhaka', 420, 600);

INSERT INTO buses (reg_no, name, bus_type_id, total_seats) VALUES
('KA-01-AB-1234', 'Coastal Express', 1, 32),
('KA-19-CD-5678', 'Tesla Rider',    2, 36),
('KA-09-EF-9012', 'Palace Shuttle',  3, 40),
('KA-20-GH-3456', 'Sunshine Travel',       4, 44);

-- Dates are relative to today, so the demo always has upcoming trips.
INSERT INTO schedules (bus_id, route_id, departure, fare) VALUES
(1, 5, TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '21:30:00'), 1000),
(2, 6, TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '22:00:00'),  850),
(3, 1, TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '07:00:00'),  450),
(3, 2, TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '15:00:00'),  450),
(4, 3, TIMESTAMP(CURDATE() + INTERVAL 2 DAY, '20:30:00'),  550),
(2, 4, TIMESTAMP(CURDATE() + INTERVAL 2 DAY, '21:00:00'),  800),
(1, 6, TIMESTAMP(CURDATE() + INTERVAL 3 DAY, '21:30:00'), 1000);