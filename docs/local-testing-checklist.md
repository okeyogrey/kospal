# KOSPAL local testing checklist

Manual acceptance cases for local Herd/MySQL setups. Seed demo data first:

```bash
php artisan migrate:fresh --seed
```

Demo password for all seeded users: `password`

| Email | Role |
| --- | --- |
| `admin@kospal.test` | Platform super-admin |
| `owner@kospal.test` | Business owner (Pro) |
| `manager@kospal.test` | Manager |
| `cashier@kospal.test` | Cashier |
| `clerk@kospal.test` | Inventory clerk |
| `other@kospal.test` | Owner of a second isolated business |

---

## Shared UI / accessibility smoke

- [ ] Skip link (`Skip to main content`) appears on Tab from the shell and moves focus to `#main-content`
- [ ] Language selector is keyboard operable (Enter/Space open, arrows/Escape, Enter select)
- [ ] Empty, loading, error, and unauthorized states show clear headings and readable contrast
- [ ] Forms expose visible labels; invalid fields show accessible error text
- [ ] Layout remains usable at ~375px and ~1280px widths

---

## Owner (`owner@kospal.test`)

- [ ] Login lands on dashboard with today’s/week/month metrics for the business timezone
- [ ] Switch language to French and Kirundi; labels update; preference survives logout/login
- [ ] Create product, category, supplier; set opening stock; adjust stock
- [ ] Complete a POS sale (cash/mobile), view receipt/PDF invoice, void a sale with reason
- [ ] Add customer from POS and reuse on next sale
- [ ] Record expense with PDF/image receipt; download receipt; delete expense
- [ ] Invite manager/cashier/clerk; update role; deactivate membership
- [ ] Create additional branch (within plan); transfer stock draft → dispatch → receive
- [ ] Open reports; apply date/branch filters; export CSV when plan allows
- [ ] Submit offline subscription upgrade request with transaction code
- [ ] Confirm `other@kospal.test` data is never visible (tenant isolation)

---

## Manager (`manager@kospal.test`)

- [ ] Can open dashboard, catalog, inventory, sales history, expenses, reports, customers
- [ ] Can complete POS sale and record expenses
- [ ] Can dispatch/receive stock transfers
- [ ] Cannot access platform routes (`/platform/...`) → 403/unauthorized
- [ ] Cannot delete the only owner membership or administer platform settings
- [ ] Branch switcher only lists allowed branches

---

## Cashier (`cashier@kospal.test`)

- [ ] Dashboard and POS/sales history are available for assigned branch
- [ ] Can search products with stock and complete a sale
- [ ] Cannot manage staff, branches, subscription, or platform settings
- [ ] Cannot download expense receipts / manage expenses categories
- [ ] Cannot create products or perform inventory adjustments
- [ ] Unauthorized page/state appears for blocked nav destinations

---

## Inventory clerk (`clerk@kospal.test`)

- [ ] Can manage products, categories, suppliers, inventory, and transfers
- [ ] Low-stock page lists items under reorder level
- [ ] Cannot access POS checkout, staff admin, subscription billing, or platform admin
- [ ] Cannot void sales or export restricted reports beyond role policy

---

## Platform super-admin (`admin@kospal.test`)

- [ ] Platform nav: subscription review + payment instructions
- [ ] Approve and reject pending subscription requests with reviewer notes
- [ ] Update payment instructions; changes visible on business subscription page
- [ ] Patch a business plan/subscription status from platform tools
- [ ] Does not inherit implicit owner powers inside a tenant workspace without membership
- [ ] Sensitive platform writes are throttled under abuse (rapid submit returns 429)

---

## Language, money, and dates

- [ ] KES amounts show 2 decimal places; BIF shows whole francs; USD shows 2 decimals
- [ ] Business created with Kenya defaults to `Africa/Nairobi`; Burundi to `Africa/Bujumbura`
- [ ] Dashboard “today” rolls at local midnight (not UTC) for the seeded business timezone
- [ ] Report date filters treat selected calendar days in the business timezone

---

## Validation / uploads / rate limits

- [ ] Login locks after repeated failures (Fortify login limiter)
- [ ] Oversized or `.txt` receipt uploads are rejected
- [ ] Required POS/expense fields fail closed with readable validation errors
- [ ] Bursting sensitive POS or invitation posts eventually returns HTTP 429

---

## Backup / restore smoke (local)

- [ ] `mysqldump` backup of `kospal` restores into a scratch database
- [ ] After restore, seed users can still log in and open dashboard
