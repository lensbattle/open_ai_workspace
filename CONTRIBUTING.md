# Contributing

## Local setup

1. Copy `.env.example` to `.env` and run `php artisan key:generate`.
2. Set `GEMINI_API_KEY` for live AI replies. Tests use fakes and do not need a key.
3. Run `php artisan migrate`.
4. Run `npm install` and `npm run build`.

## Before opening a pull request

- Run `vendor/bin/pint --dirty --format agent`.
- Run `php artisan test --compact`.
- Run `npm run build`.

Use a focused branch name such as `feature/chat-history` or `fix/login-validation`. Keep pull requests small, describe the behavior changed, and include test coverage for new request paths.
