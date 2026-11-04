# BentaHub: Campus Marketplace with a Deal Workflow & Seller Trust Engine

BentaHub is a Facebook-Marketplace-style app for students: sellers post items, buyers chat, they agree on a meetup at a **safe zone**, and after the deal the buyer reviews the seller. Built for **Database Management Systems 1** (PHP + PDO + MySQL).

| | |
| :--- | :--- |
| **Course** | Database Management Systems 1 (DBMS 1) |
| **Track** | Independent Track, 5 members |
| **Stack** | PHP 8+ (PDO), MySQL 8.0 / 8.4 LTS, plain HTML/CSS (no external CDN) |
| **Scale** | 15 tables (minimum for 5 members: 14), 12 triggers, 3 procedures, 1 function, 1 view |

## Unique major feature: Deal Workflow + Automated Seller Trust Engine

The rules live **inside MySQL** (`database/features_deal_workflow.sql`, `features_trust_engine.sql`, `features_price_alerts.sql`), so no screen can bypass them.

| Part | Owner | What happens automatically |
| :--- | :--- | :--- |
| **A. Deal workflow** | Espiritu | A meetup moving to **confirmed** reserves the listing, **completed** marks it sold, **cancelled/deleted** reopens it. |
| **B. Trust engine** | Delvo | Only **completed** meetups can be reviewed, only by that deal's buyer, once. Every insert/edit/delete of a review **recomputes the seller's average and tier** and logs tier changes. |

Tier rules (`fn_trust_tier`): **Gold** = 4+ reviews and avg ≥ 4.50 · **Silver** = 2+ reviews and avg ≥ 4.00 · **Bronze** = everyone else.

**Tricky edge cases to demo**
1. A second buyer tries to confirm a meetup for an item that is already reserved → blocked by the database.
2. A seller with a single 5-star review stays **Bronze** (too little evidence); Matt (4 reviews, avg 4.75) is **Gold**.
3. Reviewing a *pending* meetup, reviewing twice, or a seller chatting on their own listing → refused with a clear message.

## Group roster & ownership matrix

| Member | GitHub | Tables | Complete resource (CRUD) | Screen | Join report |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Ijan** (Lead) | [`@matytyyy`](https://github.com/matytyyy) | `users`, `roles`, `user_roles` | Users + role assignment | `IJAN.php` | **INNER JOIN**: verified sellers with roles |
| **Ib-Ib** | [`@EsuMikuroTheCreeper`](https://github.com/EsuMikuroTheCreeper) | `categories`, `item_conditions`, `safe_meetup_zones` | Safe zones (+ categories, conditions) | `ibib.php` | **LEFT JOIN**: every safe zone with its meetup count |
| **Dinglasan** | [`@enzo706-arc`](https://github.com/enzo706-arc) | `listings`, `listing_images`, `price_drop_alerts` | Listings (+ images, alerts) | `dinglasan.php` | **RIGHT JOIN**: every category with items and alerts |
| **Espiritu** | [`@leyespiritu21`](https://github.com/leyespiritu21) | `chat_threads`, `chat_messages`, `meetup_schedules` | Meetup schedules (+ chats, messages) | `espiritu.php` | **INNER JOIN**: chats that led to a meetup |
| **Delvo** | [`@Silthia`](https://github.com/Silthia) | `seller_trust_scores`, `reviews`, `audit_logs` | Reviews (scores computed, audit read-only) | `DELVO.php` | **FULL OUTER JOIN** (LEFT ∪ RIGHT): trust scores vs audit activity |

Every report screen has a **"View the SQL"** panel. The standalone `.sql` versions are in `database/queries/`.

## How to run

1. **Database** (MySQL Workbench or CLI). Run the three files **in this order**:
   `schema.sql` → `features_deal_workflow.sql` → `features_trust_engine.sql` → `features_price_alerts.sql` → `seed.sql` (all in `database/`)
   (CLI: `mysql -u root -p < database/schema.sql`, then each of the others in that order.)
   Re-running `seed.sql` alone resets the demo data.
2. **Config**: copy `.env.example` to `.env` and fill in your MySQL password. `.env` is git-ignored.
3. **Start the app** (needs PHP 8.0+ with `pdo_mysql`): from the project folder run
   `php -S localhost:8000` and open <http://localhost:8000>. (With XAMPP, put the folder in `htdocs`.)
4. Demo users all use the password `password123`. The **Acting as** switch in the top bar decides who is recorded in the audit log.

## Demo script (about 5 minutes)

1. **Dashboard** → live counts and the five slices.
2. **Chats & Meetups** (Espiritu) → confirm a meetup: the listing flips to *Reserved*; try a second buyer: blocked.
3. **Complete** the meetup → listing *Sold*. **Trust & Audit** (Delvo) → leave a review and watch the tier update.
4. Open any report → **View the SQL** (INNER / LEFT / RIGHT / FULL OUTER).
5. Close with `docs/ER_DIAGRAM.md` and the request flow: screen → PHP page → PDO → MySQL → trigger → back.

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
├── docs/                     # ER_DIAGRAM.md (Mermaid), bentahub_er_diagram.txt (DBML)
├── .env.example
└── README.md
```

## Security & quality notes

* All SQL uses **PDO prepared statements**; output is HTML-escaped; every POST is protected by a **CSRF token**; passwords are stored with `password_hash` (bcrypt).
* Validation exists at both levels: PHP form checks and database `CHECK` / `UNIQUE` / foreign-key constraints.
* Database errors are translated to friendly messages (no raw SQL errors shown to users).

## Git workflow (graded)

Commit from your **own machine and GitHub account**, use feature branches and pull requests, merge with a **regular merge commit** (never squash), and write meaningful messages such as `feat: add trust tier trigger`.
