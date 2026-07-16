# CRATES N' PLATES: Web-Based Restaurant Management System

---

## 1. Introduction

**CRATES N' PLATES** is a website that helps manage a restaurant and its equipment rental business. Instead of using paper orders, notebooks, and phone calls, everything is done online through this system.

Customers can browse the menu, order food, and rent items like tables and chairs — all from their phone or computer. Staff can manage orders, track payments, and handle reservations in real time. The owner can see sales reports and manage everything from one dashboard.

The system was built to make restaurant operations faster, more accurate, and easier for both the staff and the customers.

---

## 2. Background

Many small restaurants and event rental shops still use manual processes — writing orders on paper, answering phone calls for reservations, and counting sales by hand. This takes a lot of time and often leads to mistakes like lost orders, double bookings, or wrong change calculations.

In the Philippines, where smartphones and internet access are widely available, a web-based system can solve these problems. With cloud databases like Firebase, data updates instantly across all devices — no need to install or maintain a database server.

This project was created to give CRATES N' PLATES a simple, affordable, and effective digital tool to run their restaurant and rental business.

---

## 3. Problem

The business currently faces these problems:

- **Orders are taken manually**, which causes errors and slows down service during busy hours.
- **No single system** for both food orders and equipment rentals — leading to confusion and double bookings.
- **Staff cannot see updates in real time** — the cashier, kitchen, and owner often work with different information.
- **Sales reports are hard to compute** — calculating daily or monthly revenue takes too long.
- **Customers have no online access** — they must call or visit to order or reserve equipment.
- **Tracking inventory is difficult** — knowing what products are running low or which equipment is available is a challenge.

---

## 4. Objectives

### General Objective

To build a website that makes it easy for CRATES N' PLATES to manage food orders, equipment rentals, staff, and sales — all in one place.

### Specific Objectives

1. Let customers browse the menu, order food, and reserve rental equipment online.
2. Give cashiers a tool to accept, process, and track orders with live status updates.
3. Show kitchen staff what orders to prepare and let them update the status as they work.
4. Create a reservation system that prevents double bookings and tracks equipment availability.
5. Let the admin manage products, rental items, staff accounts, and view sales reports.
6. Secure the system with login, passwords, and role-based access (admin, cashier, kitchen, customer).
7. Send email notifications for order confirmations and account verification.
8. Provide sales charts and reports to help the owner make better business decisions.

---

## 5. Scope and Limitations

### What the System Can Do (Scope)

- **Customers** can register (email or Google), browse the menu, add items to cart, order food, reserve rental equipment, view order history, and manage their profile.
- **Cashiers** can accept/cancel orders, verify GCash payments, manage rental bookings (approve, reject, return), create walk-in orders and bookings, and view transaction history.
- **Kitchen staff** can see incoming orders, update the order status as they cook (pending to accepted to preparing to ready to done), and view completed orders.
- **Admins** can add/edit/delete products and rental items, create staff accounts, view all bookings, generate sales reports, and edit business settings.
- Images (products, rental items, receipts) are stored both on the server and in Firebase so they work from any device.
- Security features include CSRF protection, rate limiting, bcrypt passwords, and input sanitization.

### What the System Cannot Do (Limitations)

- **No automatic GCash payment** — customers upload a screenshot and staff manually verify it.
- **No real-time push notifications** — the system checks for new orders every 20 seconds (polling).
- **No multi-branch support** — designed for one restaurant location only.
- **No table assignment** — does not track which table a dine-in customer is using.
- **No printed receipts** — receipts are digital only (no thermal printer support).
- **No offline mode** — requires internet to work.
- **No franchise or chain management** — single-location design only.

---

## 6. System Features

### Customer Features

| Feature | What It Does |
|---------|-------------|
| Sign Up / Login | Register with email (OTP code sent) or use Google account |
| Browse Menu | See all food items with pictures, prices, and stock availability |
| Shopping Cart | Add items, change quantities, remove items, see total price |
| Place Order | Choose to pay via GCash (upload receipt) or pay at counter |
| Rent Equipment | Pick items like tables and chairs, set dates, upload GCash receipt |
| Track Orders | See order progress: Pending > Accepted > Preparing > Ready > Done |
| Order History | View all past orders and bookings with status and receipts |
| Profile | Update name and profile picture |

### Cashier Features

| Feature | What It Does |
|---------|-------------|
| Order Queue | See all incoming orders with filters and auto-refresh |
| Process Orders | Accept, cancel, or restore orders; mark GCash payments as paid/unpaid |
| Bulk Accept | Accept all pending orders at once |
| View Receipts | See GCash receipt images in a popup |
| Manage Bookings | Approve, reject, or mark rental bookings as returned |
| Walk-in Orders | Create orders directly for walk-in customers (POS) |
| Walk-in Bookings | Create rental reservations for walk-in customers |

### Kitchen Features

| Feature | What It Does |
|---------|-------------|
| Order Display | See active orders as cards with customer name, items, and time elapsed |
| Update Status | Move orders through: Pending > Accepted > Preparing > Ready > Done |
| Filter Orders | View orders by status (active, pending, preparing, ready, done) |
| History | See all completed orders |

### Admin Features

| Feature | What It Does |
|---------|-------------|
| Dashboard | See key numbers (total orders, sales, bookings) and charts |
| Manage Products | Add, edit, delete food menu items with images and stock |
| Manage Rental Items | Add, edit, delete rental equipment with images and quantity |
| Manage Staff | Create and delete cashier and kitchen staff accounts |
| View Bookings | See all rental reservations with details |
| Sales Reports | View revenue and order counts by date range with charts |
| Business Settings | Edit restaurant name, address, phone, hours, social media links |

---

## 7. Target Users

| User | Who They Are |
|------|-------------|
| **Customers** | People who want to order food or rent equipment for events |
| **Cashiers** | Staff who handle orders, payments, and reservations |
| **Kitchen Staff** | Staff who prepare food orders and update their status |
| **Admin / Owner** | The person who manages the entire system and views reports |

---

## 8. Technology Used

| Part | Technology |
|------|-----------|
| Website Frontend | Custom CSS (works on phone and desktop), plain JavaScript |
| Website Backend | PHP 8.1 |
| Database | Firebase Realtime Database (cloud-based, no server setup needed) |
| Email | PHPMailer through Gmail SMTP |
| Passwords | bcrypt (one-way encryption) |
| Charts | SVG graphics generated by PHP |
| Google Login | Google OAuth 2.0 |

---

## 9. How It Works (System Flow)

```
Customer opens website
        |
        v
Browses menu or rental items
        |
        v
Adds items to cart / selects rental items
        |
        v
Checks out (GCash or Pay at Counter)
        |
        v
Order saved to Firebase (cloud database)
        |
        v
Cashier sees new order (auto-refreshes every 20 seconds)
        |
        v
Cashier accepts the order
        |
        v
Kitchen sees the order and starts cooking
        |
        v
Kitchen updates status: Preparing > Ready > Done
        |
        v
Cashier marks as done / Customer sees "Completed"
```

For rentals, the flow is similar: Customer submits > Cashier approves > Equipment returned > Stock restored.

---

## 10. Security

| Protection | How It Works |
|-----------|-------------|
| CSRF Token | Every form has a hidden security token to prevent fake submissions |
| Rate Limiting | Login attempts limited to 5 per 15 minutes to prevent brute force |
| Passwords | Stored as bcrypt hashes (never plain text) |
| Session Security | Cookies are protected with HttpOnly and SameSite flags |
| File Upload Safety | Images are checked by MIME type and image verification before saving |
| Input Sanitization | All user input is escaped to prevent code injection |
| Security Headers | Browser restrictions set to prevent common attacks |

---

## 11. Installation

### What You Need
- XAMPP (or any PHP web server)
- PHP 8.1 or higher
- A Firebase account (free)
- A Gmail account (for sending emails)

### Steps

1. Copy the project folder to `C:\xampp\htdocs\CRNP`
2. Run `setup.sh` or create a `.env` file with your Firebase URL and Gmail credentials
3. Start Apache from XAMPP
4. Open `http://localhost:8000` in your browser
5. Create the first admin at `http://localhost:8000/admin/signup.php`
6. Delete `admin/signup.php` after creating the admin

### Login Pages

| Who | Login Page |
|-----|-----------|
| Customer | `http://localhost:8000/user/login.php` |
| Cashier | `http://localhost:8000/cashier/login.php` |
| Kitchen | `http://localhost:8000/kitchen/login.php` |
| Admin | `http://localhost:8000/admin/login.php` |

---

## 12. Firebase Data Structure

```
/
├── admins/           Admin accounts
├── users/            Customer accounts
├── cashiers/         Cashier accounts
├── kitchen/          Kitchen staff accounts
├── products/         Menu items (name, price, stock, image)
├── rent_items/       Rental equipment (name, price, quantity, image)
├── orders/           Food orders (items, total, payment, status)
├── bookings/         Rental reservations (items, dates, status)
└── settings/         Restaurant info and configuration
```

---

## 13. Conclusion

The CRATES N' PLATES web-based system successfully solves the everyday problems of managing a restaurant and equipment rental business. Instead of paper orders and phone calls, everything is organized in one easy-to-use website.

The system allows customers to order and reserve online, staff to process orders faster with real-time updates, and the owner to see the full picture through sales reports and dashboards. By using cloud technology (Firebase), the system works across devices without needing expensive server equipment.

---

## 14. Recommendations

1. **Build a mobile app** — A phone app would be more convenient for customers to order and reserve.
2. **Add automatic GCash payment** — Connect directly to GCash API so payments are verified instantly.
3. **Send real-time notifications** — Use push notifications instead of polling so staff get alerted immediately.
4. **Support multiple branches** — If the business expands, the system should handle multiple locations.
5. **Print receipts** — Add support for thermal printers at the counter.
6. **Work offline** — Let customers browse the menu even without internet.
7. **Low stock alerts** — Send email or SMS when products are running low.
8. **Loyalty program** — Add a rewards system to encourage repeat customers.

---

*Capstone Project: CRATES N' PLATES Restaurant Management System*
*PHP 8.1 | Firebase RTDB | PHPMailer | Custom CSS | Vanilla JS*
*Year: 2026*
