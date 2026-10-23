# Backend setup

Install the backend dependencies from this directory:

```sh
composer install
```

The password reset endpoint sends email through SMTP. Configure these
environment variables in the PHP/Apache environment before using the endpoint:

- `LMS_SMTP_HOST`: SMTP server hostname.
- `LMS_SMTP_USER`: SMTP account username and sender address.
- `LMS_SMTP_PASS`: SMTP account password or provider app password.
- `LMS_SMTP_PORT`: `587` for STARTTLS or `465` for SMTPS.

The endpoint uses example credentials as placeholders when these variables are
not configured. Replace them with valid SMTP settings before sending real email.
