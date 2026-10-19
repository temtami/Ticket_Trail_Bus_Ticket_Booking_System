# TicketTrail – Bus Ticket Booking System

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-10.4%2B-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

A full-stack bus ticket booking web app built with PHP and MySQL.
Passengers can search trips, choose seats on an interactive seat map, book and cancel tickets.
Admins manage bus types, buses, routes and trip schedules from a dashboard.

---
## Project Structure

```
Ticket_trail/
├── admin/index.php        # Admin dashboard
├── assets/css/style.css   # Styles
├── config/db.php          # Database connection and settings
├── database/schema.sql    # Tables and demo data
├── includes/              # Shared helpers, header and footer
├── tests/run_tests.php    # Automated test suite
├── index.php              # Search page
├── book.php               # Seat selection and booking
├── my_bookings.php        # Booking history and cancellation
├── login.php / register.php / logout.php
└── setup.php              # One-time admin creation


##  Features

### For passengers
- Sign up and log in securely
- Search trips by source, destination and date
- Interactive seat map showing available, selected and booked seats
- Live fare total while choosing seats (up to 6 seats per booking)
- Booking history with payment reference and status
- Cancel upcoming bookings, which instantly releases the seats

### For admins
- Dashboard with customers, upcoming trips, active bookings and revenue
- Add bus types, buses and routes
- Schedule trips with automatic fare calculation (route base fare + bus type surcharge)
- Delete trips that have no bookings
- View recent bookings

### Security and data integrity
- Passwords hashed with bcrypt (`password_hash`)
- All queries use PDO prepared statements (SQL injection protection)
- CSRF tokens on every form
- All output escaped (XSS protection)
- Double booking is impossible: the database rejects a second booking of the same seat
- Bookings and cancellations run inside transactions

---

## 🗄️ Database Design

```mermaid
erDiagram
    USERS ||--o{ BOOKINGS : makes
    SCHEDULES ||--o{ BOOKINGS : has
    BOOKINGS ||--o{ BOOKING_SEATS : contains
    SCHEDULES ||--o{ BOOKING_SEATS : reserves
    BUSES ||--o{ SCHEDULES : runs
    ROUTES ||--o{ SCHEDULES : follows
    BUS_TYPES ||--o{ BUSES : categorizes
```

| Table | Purpose |
|---|---|
| `users` | Passengers and admins (separated by a `role` column) |
| `bus_types` | Bus categories such as AC Sleeper, with a fare surcharge |
| `buses` | Registration number, name, type and seat count |
| `routes` | Source, destination, distance and base fare |
| `schedules` | A bus on a route at a departure time, with its fare |
| `bookings` | One record per checkout, with payment reference and status |
| `booking_seats` | Seats in each booking. The primary key `(schedule_id, seat_no)` prevents double booking |

---

## 🧪 Testing

The test suite checks the database schema, helper functions, and the full booking flow:
registration, duplicate email rejection, fare calculation, seat booking, double-booking
prevention, cancellation, and database constraints. All test data is rolled back,
so the database is never modified.

```bash
php tests/run_tests.php
```

<img width="1861" height="813" alt="bookings " src="https://github.com/user-attachments/assets/afc8721c-1762-4539-a800-4dea000c0c0a" />
<img width="1849" height="930" alt="Homepage of ticket_trail" src="https://github.com/user-attachments/assets/6512e14c-ad75-4c9f-944c-93b3cc286e61" />
<img width="1846" height="936" alt="regestration" src="https://github.com/user-attachments/assets/43ca1cf6-ff05-4f59-b0b9-e286670bd41b" />
<img width="1839" height="934" alt="ticket booking " src="https://github.com/user-attachments/assets/61d1b566-0ecb-4e51-8ce9-286db2e147aa" />



