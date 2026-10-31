# BentaHub — Automated FEFO & Seller Verification Engine (DBMS 1)

## Course & Project Details
* **Course:** Database Management Systems 1 (DBMS 1)
* **System Title:** BentaHub
* **Track:** Independent Track (DBMS 1 - 5 Members)
* **Unique Major Feature:** Dynamic Seller Trust Rating & Automated Meetup Expiration Engine

---

## Group Roster & Ownership Matrix

| Member Name | GitHub Username | Assigned Tables | Complete Resource | Assigned JOIN Report & Type | Feature Area |
|---|---|---|---|---|---|
| Member 1 (IJAN) | @matytyyy | `users`, `roles`, `user_roles` | User Profile & Roles | **INNER JOIN:** Active verified sellers with roles | Core & Auth |
| Member 2 (IB-IB) | @EsuMikuroTheCreeper | `categories`, `item_conditions`, `safe_meetup_zones` | Safe-Zone Config | **LEFT JOIN:** Safe zones with scheduled meetup counts | Master Data |
| Member 3 (DINGLASAN)| @enzo706-arc| `listings`, `listing_images`, `price_drop_alerts` | Item Catalog | **RIGHT JOIN:** Categories with price drop alerts | Item Catalog |
| Member 4 (ESPIRITU) |@leyespiritu21 | `chat_threads`, `chat_messages`, `meetup_schedules` | Inquiries & Chat | **INNER JOIN:** Active chats with confirmed meetups | Chat & Social |
| Member 5 (SIGRID)| @Silthia | `seller_trust_scores`, `audit_logs` | Trust & Audit Engine | **FULL OUTER JOIN (UNION):** Trust tiers vs audit logs | Trust & Audit |

---

## How to Run locally

1. Clone repository: `git clone <repo-url>`
2. Open **MySQL Workbench**.
3. Load and execute `database/schema.sql` to create `bentahub_db` and all 14 tables.
4. Load and execute `database/seed.sql` to insert sample demo records.
5. Execute individual member queries located under `src/queries/`.