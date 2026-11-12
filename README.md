# BentaHub: Campus Marketplace with a Deal Workflow & Seller Trust Engine

BentaHub is a Facebook-Marketplace-style app for students: sellers post items, buyers chat, they agree on a meetup at a **safe zone**, and after the deal the buyer reviews the seller. Built for **Database Management Systems 1** (PHP + PDO + MySQL) and **Systems Integration and Architecture** (Node.js MVC + REST API) as one combined system.

| | |
| :--- | :--- |
| **Courses** | Database Management Systems 1 (DBMS 1) and Systems Integration and Architecture (SIA) |
| **Track** | **Merged Track**: 5 DBMS 1 members + 2 SIA-only members (7 total); one defense graded for both courses |
| **Stack** | DBMS 1: PHP 8+ (PDO), MySQL 8.0 / 8.4 LTS. SIA: Node.js + Express (MVC), JWT auth, plain HTML/JS frontend using `fetch` |
| **Scale** | 15 tables (minimum for 5 members: 14), 12 triggers, 3 procedures, 1 function, 1 view |

## Unique major feature: Deal Workflow + Automated Seller Trust Engine

The rules live **inside MySQL** (`database/features_deal_workflow.sql`, `features_trust_engine.sql`, `features_price_alerts.sql`), so no screen can bypass them.

| Part | Owner | What happens automatically |
| :--- | :--- | :--- |
| **A. Deal workflow** | Espiritu | A meetup moving to **confirmed** reserves the listing, **completed** marks it sold, **cancelled/deleted** reopens it. |
| **B. Trust engine** | Delvo | Only **completed** meetups can be reviewed, only by that deal's buyer, once. Every insert/edit/delete of a review **recomputes the seller's average and tier** and logs tier changes. |

Tier rules (`fn_trust_tier`): **Gold** = 4+ reviews and avg ≥ 4.50 · **Silver** = 2+ reviews and avg ≥ 4.00 · **Bronze** = everyone else.

## Group roster & ownership matrix

### DBMS 1 (PHP + MySQL), 15 tables

| Member | GitHub | Tables | Complete resource (CRUD) | Screen | Join report |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Ijan** (Lead) | [`@matytyyy`](https://github.com/matytyyy) | `users`, `roles`, `user_roles` | Users + role assignment | `IJAN.php` | **INNER JOIN**: verified sellers with roles |
| **Ib-Ib** | [`@EsuMikuroTheCreeper`](https://github.com/EsuMikuroTheCreeper) | `categories`, `item_conditions`, `safe_meetup_zones` | Safe zones (+ categories, conditions) | `ibib.php` | **LEFT JOIN**: every safe zone with its meetup count |
| **Dinglasan** | [`@enzo706-arc`](https://github.com/enzo706-arc) | `listings`, `listing_images`, `price_drop_alerts` | Listings (+ images, alerts) | `dinglasan.php` | **RIGHT JOIN**: every category with items and alerts |
| **Espiritu** | [`@leyespiritu21`](https://github.com/leyespiritu21) | `chat_threads`, `chat_messages`, `meetup_schedules` | Meetup schedules (+ chats, messages) | `espiritu.php` | **INNER JOIN**: chats that led to a meetup |
| **Delvo** | [`@Silthia`](https://github.com/Silthia) | `seller_trust_scores`, `reviews`, `audit_logs` | Reviews (scores computed, audit read-only) | `DELVO.php` | **FULL OUTER JOIN** (LEFT ∪ RIGHT): trust scores vs audit activity |

### SIA (Node.js MVC + REST API)

Same database (`bentahub_db`), so the MySQL triggers still enforce the deal workflow and trust engine. A rule blocked by a trigger returns **409**.

| Member | GitHub | Resource (GET, POST, PUT/PATCH, DELETE) | Join report (GET) | Frontend screen |
| :--- | :--- | :--- | :--- | :--- |
| **Ijan** | [`@matytyyy`](https://github.com/matytyyy) | `/api/users` | INNER JOIN `/api/reports/verified-sellers` | `frontend/js/screens/users.js` |
| **Ib-Ib** | [`@EsuMikuroTheCreeper`](https://github.com/EsuMikuroTheCreeper) | `/api/safe-zones` | LEFT JOIN `/api/reports/zone-meetups` | `zones.js` |
| **Dinglasan** | [`@enzo706-arc`](https://github.com/enzo706-arc) | `/api/listings` | RIGHT JOIN `/api/reports/category-alerts` | `listings.js` |
| **Espiritu** | [`@leyespiritu21`](https://github.com/leyespiritu21) | `/api/meetups` (+ PATCH `/:id/status`) | INNER JOIN `/api/reports/chats-meetups` | `meetups.js` |
| **Gemina** | [`@R0nielGemina`](https://github.com/R0nielGemina) | `/api/reviews`, `/api/trust-scores`, `/api/auth` | FULL OUTER JOIN `/api/reports/trust-vs-audit` | `reviews.js` |
| **Cabugnason** | [`@erichs333`](https://github.com/erichs333) | `/api/threads`, `/api/messages` (PATCH) | LEFT JOIN `/api/reports/threads-message-counts` | `chats.js` |

**MVC (SIA):** `backend/models` (SQL, validation, business rules) -> `backend/controllers` (request, response, status codes) -> `backend/routes`; `backend/middleware/auth.js` (JWT, 401/403); `frontend/` is the separate client that talks JSON.

Every report screen (PHP) has a **"View the SQL"** panel. The standalone `.sql` versions are in `database/queries/`.

## How to run

**Both apps share one database. Do step 1 and 2 first.**

1. **Database** (MySQL Workbench or CLI). Run the three files **in this order**:
   `schema.sql` → `features_deal_workflow.sql` → `features_trust_engine.sql` → `features_price_alerts.sql` → `seed.sql` (all in `database/`)
   (CLI: `mysql -u root -p < database/schema.sql`, then each of the others in that order.)
   Re-running `seed.sql` alone resets the demo data.
2. **Config**: copy `.env.example` to `.env` and fill in your MySQL password. `.env` is git-ignored.
3. **DBMS 1 site (PHP)**, needs PHP 8.0+ with `pdo_mysql`: from the project folder run
   `php -S localhost:8000` and open <http://localhost:8000>. (With XAMPP, put the folder in `htdocs`.)
3b. **SIA site (Node.js)**: `cd backend && npm install && npm start`, then open <http://localhost:3000>. Log in with `matt.ijan@bentahub.ph` / `password123`.
4. Demo users all use the password `password123`. The **Acting as** switch in the top bar decides who is recorded in the audit log.

## Repository structure

```text
bentahub-dbms1/
├── index.php                 # dashboard
├── IJAN.php  ibib.php  dinglasan.php  espiritu.php  DELVO.php   # one screen per member
├── includes/                 # bootstrap.php (helpers, CSRF, audit), layout.php (shared header/footer)
├── assets/                   # style.css, app.js
├── config/db.php             # PDO connection (reads .env)
├── database/
│   ├── schema.sql            # 15 tables, PK/FK/CHECK/UNIQUE
│   ├── features_deal_workflow.sql   # Espiritu: meetup status drives listing status
│   ├── features_trust_engine.sql    # Delvo: reviews recompute seller tiers
│   ├── features_price_alerts.sql     # Dinglasan: price-drop audit trigger + alerts view
│   ├── seed.sql              # realistic demo data
│   └── queries/              # the five join reports as standalone SQL
├── backend/                  # SIA: Express API (models / controllers / routes / middleware / utils)
├── frontend/                 # SIA: separate client (index.html, js/api.js, js/screens/*.js)
├── docs/                     # ER_DIAGRAM.md, ERDIAGRAM.png, bentahub_er_diagram.txt, SIA.md (endpoints)
├── .env.example
└── README.md
```

## Security & quality notes

* All SQL uses **PDO prepared statements**; output is HTML-escaped; every POST is protected by a **CSRF token**; passwords are stored with `password_hash` (bcrypt).
* Validation exists at both levels: PHP form checks and database `CHECK` / `UNIQUE` / foreign-key constraints.
* Database errors are translated to friendly messages (no raw SQL errors shown to users).
