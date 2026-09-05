# Mobile API

The mobile application uses JSON endpoints under `/api/v1`. Protected endpoints require:

```http
Authorization: Bearer <token>
Accept: application/json
```

Obtain a token with `POST /api/v1/auth/login`:

```json
{
  "email": "guest@example.com",
  "password": "your-password",
  "device_name": "ios-app"
}
```

The response contains a bearer `token` and the authenticated `user`. Tokens are stored only as hashes. Call `POST /api/v1/auth/logout` to revoke the current token.

## Public catalogue

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/properties` | Published properties; optional `city`, `guests`, `establishment_id` filters. |
| GET | `/properties/{slug}` | Published property detail. |
| POST | `/properties/{slug}/availability` | Availability check with `check_in` and `check_out` ISO dates. |

## Customer

Customer tokens can access `GET /me`, `PUT /me`, `GET /customer/reservations`, `GET /customer/reservations/{id}`, and `POST /customer/properties/{id}/favorite`.

Customer reservation endpoints only return reservations that belong to the authenticated customer account.

## Concierge and admin

Concierge and admin tokens can access the tenant-scoped staff endpoints:

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/staff/reservations` | Reservation list for the current workspace. |
| GET | `/staff/reservations/{id}` | Reservation detail. |
| PATCH | `/staff/reservations/{id}/status` | Update `status`; optional `notes`. |
| POST | `/staff/reservations/{id}/payment-link` | Queue a payment-link email and return the checkout URL. |

The payment-link endpoint is unavailable once a reservation is confirmed or a payment attempt has been verified. This matches the admin web interface.

Admin tokens additionally have `GET /admin/properties` for the workspace property list.

## Response conventions

Successful resource collections and objects use a `data` key. Validation errors return Laravel's standard JSON `message` and `errors` structure. Missing or invalid tokens return `401`; role and ownership violations return `403`.