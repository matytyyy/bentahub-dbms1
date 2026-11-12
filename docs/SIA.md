# BentaHub: SIA part (Node.js MVC + REST API)

The DBMS 1 schema, triggers and seed data are unchanged. The Node backend talks to the same `bentahub_db`,
so the deal workflow and trust engine (MySQL triggers) still enforce the rules. A rule blocked by a trigger returns **409**.

## Run
1. Run the SQL files in the same order as the main README.
2. Add `JWT_SECRET=anything-long` to the root `.env` (optional; there is a dev default).
3. `cd backend && npm install && npm start`, then open <http://localhost:3000>.
4. Log in: `matt.ijan@bentahub.ph` / `password123` (Admin + Seller). Other seed users use the same password.

## Folder layout
`backend/models` (SQL, validation, rules) · `backend/controllers` (request, response, status codes) · `backend/routes` · `backend/middleware` · `frontend/` (plain HTML/JS using `fetch`).

## SIA ownership
| Member | Resource (GET, POST, PUT/PATCH, DELETE) | Join report (GET) | Frontend screen |
| :--- | :--- | :--- | :--- |
| Ijan | `/api/users` | INNER JOIN `/api/reports/verified-sellers` | `users.js` |
| Ib-Ib | `/api/safe-zones` | LEFT JOIN `/api/reports/zone-meetups` | `zones.js` |
| Dinglasan | `/api/listings` | RIGHT JOIN `/api/reports/category-alerts` | `listings.js` |
| Espiritu | `/api/meetups` (+ PATCH `/:id/status`) | INNER JOIN `/api/reports/chats-meetups` | `meetups.js` |
| Gemina | `/api/reviews`, `/api/trust-scores`, `/api/auth` | FULL OUTER JOIN `/api/reports/trust-vs-audit` | `reviews.js` |
| Cabugnason | `/api/threads`, `/api/messages` (PATCH) | LEFT JOIN `/api/reports/threads-message-counts` | `chats.js` |

Delvo is DBMS 1 only. His trust-engine SQL is reused by Gemina's Model.

## Endpoints
All endpoints except `POST /api/auth/login` need `Authorization: Bearer <token>`.
Every list is `GET /api/<resource>`, one item is `GET /api/<resource>/:id`, create is `POST` (201), update is `PUT` (or `PATCH` for messages and meetup status), delete is `DELETE`.

## Status codes
200 ok · 201 created · 400 invalid input · 401 not logged in / bad token · 403 not allowed (for example, only an Admin can delete users; only the seller or an Admin can change a listing) · 404 not found · 409 blocked by a MySQL trigger, duplicate, or a row still in use · 500 server error.

JSON shape: `{ "success": true, "data": ... }` or `{ "success": false, "message": "..." }`.
