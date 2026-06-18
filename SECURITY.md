# Security Policy

This repository is a clean installable source package.

It intentionally excludes:

- payment gateways
- merchant IDs or API keys
- payment notify handling
- collection QR codes
- production database credentials
- production order, card, and customer data

## Deployment Checklist

1. Install on a fresh database.
2. Delete or restrict `install.php` after installation.
3. Keep `config.php` out of version control.
4. Use HTTPS in production.
5. Change the admin password and safe code regularly.
6. Back up the database before upgrades.

## Reporting Issues

Open a GitHub issue with reproduction steps. Do not include secrets, private keys, database dumps, payment credentials, or customer data.
