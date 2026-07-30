# AGENT.md

# GreenPool Development Guide

## Project Overview

GreenPool is a web-based ride-sharing platform developed as a university collaborative software engineering project. The objective is to provide a carpooling system that allows passengers to search and book rides while enabling drivers to publish trips and manage booking requests.

The project also integrates a Tourism Attraction feature that recommends nearby attractions and allows passengers to save favourite destinations.

The application is developed using Laravel 12 following the MVC architecture.

---

# Technology Stack

Backend
- Laravel 12
- PHP 8.2+
- MySQL

Frontend
- Blade Template
- Tailwind CSS
- Alpine.js
- Vite

Authentication
- Laravel Breeze
- Email Verification

Version Control
- Git
- GitHub

---

# Project Architecture

The application follows the standard Laravel MVC architecture.

Browser
→ Blade View
→ Controller
→ Model (Eloquent ORM)
→ MySQL Database

Business logic should remain inside Controllers or Service classes.

Database interaction should always use Eloquent instead of raw SQL unless absolutely necessary.

---

# Development Principles

Follow Laravel conventions whenever possible.

Prefer:
- Route Model Binding
- Eloquent Relationships
- Form Request Validation
- Resource Controllers
- Blade Components

Avoid:
- Raw SQL queries
- Business logic inside Blade views
- Duplicate code
- Hardcoded values

Keep controllers concise and move reusable logic into dedicated classes when necessary.

---

# Authentication

Authentication is handled using Laravel Breeze.

User registration flow:

Register
→ Email Verification
→ Login
→ Redirect based on role

Roles:

- passenger
- driver

After login:

Passenger
→ passenger.home

Driver
→ driver.home

---

# Navigation

Passenger Navigation

- Home
- My Booking
- Profile

Driver Navigation

- Home
- Booking Request
- Vehicle
- Profile

Navigation items are conditionally rendered using:

```php
Auth::user()->role
```

---

# Database Design

Main entities:

Users

- user_id
- name
- email
- password
- role

Vehicles

- belongsTo(User)

Trips

- belongsTo(User)
- belongsTo(Vehicle)

Bookings

- belongsTo(Trip)
- belongsTo(User)

Reviews

- belongsTo(Booking)

TouristAttractions

Favourites

- belongsTo(User)
- belongsTo(TouristAttraction)

---

# Current Modules

Completed

- Authentication
- Registration
- Login
- Email Verification
- Role-based Login Redirect
- Passenger Home
- Driver Home
- Dynamic Navigation Bar

In Progress

Passenger

- Ride Booking Module

Driver

- Booking Request Module

Upcoming

- Vehicle Management Module
- Tourist Attraction Module
- Favourite Attraction Module
- Review Module

---

# Ride Booking Module

Passenger Features

- Search available trips
- View trip information
- Submit booking request
- View booking status
- Cancel booking request

Driver Features

- View booking requests
- Accept booking request
- Reject booking request

Booking Status

- Pending
- Accepted
- Rejected
- Cancelled

---

# Coding Standards

Controllers

Naming

Example:

PassengerBookingController

DriverBookingController

VehicleController

Methods

- index()
- create()
- store()
- show()
- edit()
- update()
- destroy()

Validation

Always validate incoming requests before database operations.

Example:

- required
- string
- integer
- exists
- unique

---

# Database Naming

Tables

Plural

Examples

users

vehicles

trips

bookings

Columns

snake_case

Examples

pickup_location

departure_time

available_seat

Foreign Keys

user_id

trip_id

vehicle_id

booking_id

---

# Git Workflow

Feature branches

Example

feature/passenger-booking

feature/vehicle-management

Commit message format

feat:

fix:

refactor:

docs:

Example

feat: implement passenger booking request

fix: resolve email verification redirect

---

# UI Guidelines

Use Laravel Breeze layout.

Keep UI clean and consistent.

Prefer:

Cards

Tables

Modals

Responsive layouts

Avoid inline CSS.

Use Tailwind utility classes.

---

# Future Enhancements

- Real-time booking notification
- Google Maps integration
- Route visualization
- Trip history
- Driver ratings
- Passenger ratings
- Search filters
- Dashboard statistics

---

# Development Goal

Prioritize:

1. Correct business logic
2. Database integrity
3. Laravel best practices
4. Clean architecture
5. Readable code
6. Reusable components

Maintain code quality suitable for academic software engineering projects while following Laravel conventions.