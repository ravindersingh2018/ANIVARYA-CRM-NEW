# Anivarya CRM (starter v0.1)
Plain PHP 8+ and MySQL. No Composer or Node build needed.

## Put it on Hostinger
1. Push this folder to GitHub, or upload the files into `public_html` (or a subdomain folder such as `crm.anivarya.com`).
2. In hPanel: Databases > MySQL Databases > create a database and user. Note the name, user and password.
3. Open your CRM address in the browser. The installer appears. Enter host `localhost`, the DB name, user and password, then your admin email and password.
4. Log in. Three companies are pre-created: Kanu Pharma, Win2age Pharma, Anivarya Products Private Limited. Rename them under Settings.

## What works now
Login, companies (editable, add more), company switcher, customers (add, search, CSV import, duplicates merged by mobile/email), orders (add, CSV import, channel tracking), dashboard (revenue by channel, repeat buyers), Meta/Google hashed audience download (only customers marked "allowed to message").

## Not built yet (next steps)
Pipeline and lead scoring, workflows, WhatsApp, live Meta/Google/Shopify/Amazon connectors, GST invoices, roles beyond admin, Hindi, PWA, report builder. See ANIVARYA_CRM_BUILD_PROMPT.md.
Marketplace buyers (Amazon, Flipkart, Blinkit, 1mg) are stored as orders without a customer when no mobile number is in the report.
