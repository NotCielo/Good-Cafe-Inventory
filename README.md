# Good Cafe Inventory

A responsive inventory workspace with a four-digit keypad, staff/admin access, stock tracking, a purchasing list, and an audit trail. The frontend is deployed on Vercel; all inventory and purchase data is stored in Supabase Postgres.

## Branding

The interface follows [Good Cafe & Co. on Instagram](https://www.instagram.com/goodcafeph/): the original smiling logo, lime green (#5EC600), white surfaces, and rounded headings. The high-resolution logo already present in the repository is reused without modification. Small text uses a darker green for readable contrast.

## Everyday workflow

- Admins create and edit items and two-level categories, and archive or restore records.
- Both roles add or remove stock with a mandatory typed name. Each adjustment and audit entry commits together; quantities cannot go below zero.
- Items at or below their reorder level appear on Needs to buy automatically. You can also flag an item or add a one-off purchase.
- Logging a purchase records the purchase without changing stock. Use Update stock when the delivery arrives. One-off purchases are completed when purchased.
- Search, category/status/location filters, CSV exports, and desktop/mobile layouts are included.
- The workspace locks after 15 minutes of inactivity. PINs are checked server-side with bcrypt and persistent attempt limits. No PINs or privileged keys are bundled in the browser.

## Development

1. Run npm ci.
2. Copy .env.example to .env.local and supply the Supabase project URL and publishable key.
3. Run npm run dev. Use npm run build for the production bundle.

Only VITE_SUPABASE_URL and VITE_SUPABASE_PUBLISHABLE_KEY are public. Never use a service-role key in a VITE_ variable.

## Backend

The deployed inventory Edge Function is in supabase/functions/inventory/index.ts. The gateway JWT check is disabled because the unlock action is the authentication entry point. Every other action independently validates the access token with Supabase Auth, checks the server-managed cafe_id and cafe_role claims, and enforces role permissions. Direct table and RPC access is revoked from anon and authenticated roles. RLS is enabled with no client policies, intentionally keeping data access inside the function.

The initial schema for a **fresh, empty** project is database/supabase.sql. It is not a repeatable migration. Existing databases must be inspected before applying changes. Production Supabase project: ybkuqxpiivwprakvidiz (Singapore).

PIN hashes are stored only in private.cafe_access. The corresponding internal Auth accounts have random, unknown passwords and server-managed role metadata. PIN setup and rotation must be performed by an authorized operator through the Supabase SQL editor; do not put PIN values in source files.

## Verification

The Playwright test uses a real Supabase connection. Add TEST_ADMIN_PIN and TEST_STAFF_PIN to your private .env.local, install Chromium with npx playwright install chromium, then run npm test. It creates records with the __Verification prefix; remove those test records afterward. Tests cover the PIN UI, stock audit, negative-stock prevention, concurrent updates, purchase/stock separation, staff UI, and mobile overflow. Session credentials are kept in memory only.

## Deployment

Vercel project: good-cafe-inventory. Framework: Vite. Build: npm run build. Output: dist. Configure the two VITE_ variables on the intended deployment environment, and deploy the Edge Function separately when backend code changes. The current setup starts with an empty inventory and the original 11 categories, per the owner’s instruction.

The original PHP/MySQL application is retained in legacy/ for reference and excluded from deployment. It is no longer the active app. Production no longer requires the MYSQL* environment variables.
