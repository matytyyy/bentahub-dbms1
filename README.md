# BentaHub — Automated FEFO & Seller Verification Engine

## 📖 Overview

**BentaHub** is a student-based local marketplace database designed for buying and selling items within a campus or local community. Built as a Database Management Systems 1 (DBMS 1) course project, the system handles item listings, user accounts, product categories, and meetup coordination.

To handle practical campus trading scenarios (such as selling food, snacks, or second-hand items), the database includes tracking for perishable goods using **First-Expired, First-Out (FEFO)** inventory ordering, price drop alerts, and basic seller trust scores across 14 relational tables.

### 🛠️ Core Features

* **User Accounts & Roles:** Stores user profiles, assigns roles (buyer, seller, admin), and tracks basic account verification status.
* **Item Catalog & Price Alerts:** Organizes listings by category and condition, with automatic tracking for item price drops.
* **FEFO Inventory Tracking:** Stores expiration dates on perishable item listings so products closer to expiring can be prioritized in queries.
* **Chat & Meetup Scheduling:** Links buyer-seller chat threads with agreed meetup times at designated campus safe-zones.
* **Seller Ratings & Audit Logs:** Records seller rating scores based on user feedback and tracks basic administrative audit logs.

---

## 📌 Project & Course Details

| Attribute | Details |
| :--- | :--- |
| **Course** | Database Management Systems 1 (DBMS 1) |
| **System Title** | BentaHub |
| **Track** | Independent Track (5 Members) |
| **Target Database** | MySQL 8.0 / 8.4 LTS |
| **Major Features** | Seller Trust Ratings & Meetup Schedule Tracking |

---

## 👥 Group Roster & Table Ownership Matrix

| Member Name | GitHub Username | Assigned Tables | Complete Resource Area | Assigned JOIN Query & Report Type | Feature Module |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Ijan** *(Lead)* | [`@matytyyy`](https://github.com/matytyyy) | `users`, `roles`, `user_roles` | User Profile & Roles | **INNER JOIN:** Active verified sellers with roles | Core & Auth |
| **Ib-Ib** | [`@EsuMikuroTheCreeper`](https://github.com/EsuMikuroTheCreeper) | `categories`, `item_conditions`, `safe_meetup_zones` | Safe-Zone Config | **LEFT JOIN:** Safe zones with scheduled meetup counts | Master Data |
| **Dinglasan** | [`@enzo706-arc`](https://github.com/enzo706-arc) | `listings`, `listing_images`, `price_drop_alerts` | Item Catalog | **RIGHT JOIN:** Categories with price drop alerts | Item Catalog |
| **Espiritu** | [`@leyespiritu21`](https://github.com/leyespiritu21) | `chat_threads`, `chat_messages`, `meetup_schedules` | Inquiries & Chat | **INNER JOIN:** Active chats with confirmed meetups | Chat & Social |
| **DELVO** | [`@Silthia`](https://github.com/Silthia) | `seller_trust_scores`, `audit_logs` | Trust & Audit Engine | **FULL OUTER JOIN (UNION):** Trust tiers vs audit logs | Trust & Audit |

---

## 📂 Repository Structure

```text
bentahub-dbms1/
├── database/
│   ├── schema.sql        # Database initialization & 14 relational tables with constraints
│   └── seed.sql          # Test dataset for demo queries & transaction simulations
├── src/
│   ├── member_DELVO_full_outer_join.sql  # Trust Ratings & Audit Engine (Full Outer Join)
│   ├── member_DINGLASAN_right_join.sql   # Item Catalog & Price Drop Alerts (Right Join)
│   ├── member_ESPIRITU_inner_join.sql    # Chat & Meetup Schedules (Inner Join)
│   ├── member_IB-IB_left_join.sql        # Master Data & Safe Zones (Left Join)
│   └── member_IJAN_inner_join.sql        # Core & Auth (Inner Join)
├── docs/
├── bentahub_er_diagram.txt
└── ERDIAGRAM.png
├── .env.example
├── .gitignore
└── README.md