# Backend setup

Install the backend dependencies from this directory:

```sh
composer install
```

Configure these environment variables in the PHP/Apache environment. Do not put
credentials directly in PHP files:

- `LMS_DB_USER`: MySQL username.
- `LMS_DB_PASS`: MySQL password (set it to an empty value only if the local
  database account has no password).
- `LMS_DB_HOST`: database host; defaults to `127.0.0.1`.
- `LMS_DB_NAME`: database name; defaults to `lmsdb`.
- `LMS_TURNSTILE_SECRET_KEY`: Cloudflare Turnstile secret key used by login.

The password reset endpoint sends email through SMTP. Configure these
additional variables before using that endpoint:

- `LMS_SMTP_HOST`: SMTP server hostname.
- `LMS_SMTP_USER`: SMTP account username and sender address.
- `LMS_SMTP_PASS`: SMTP account password or provider app password.
- `LMS_SMTP_PORT`: `587` for STARTTLS or `465` for SMTPS.
- `LMS_SMTP_FROM_NAME`: optional sender display name; defaults to `LMS`.

If any required value is missing, the corresponding service fails closed and
returns a generic error rather than using an example credential.
