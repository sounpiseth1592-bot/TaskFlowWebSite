# TaskFlow

A small Laravel task manager with projects, priorities, due dates, and a responsive installable web app. The server uses Laravel sessions and SQLite by default; the browser keeps a local task cache and queues task changes in IndexedDB while offline.

## Run locally

Requirements: PHP 8.3+, Composer, Node.js, and npm.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000), create an account, and add a project to get started. For local development with asset hot reloading, run `npm run dev` in a second terminal.

## Deploy a free preview to Render

The root-level `render.yaml` defines a Docker web service and a PostgreSQL database in Singapore. From Render, create a Blueprint and connect this repository; keep the Blueprint root directory set to the repository root.

Render will prompt for `APP_KEY`, `APP_URL`, and `ADMIN_EMAIL`. Generate an application key with `php artisan key:generate --show`, enter it directly in Render, and set `APP_URL` to the HTTPS URL Render assigns to the service. Do not put these values in `render.yaml` or commit them. Register the account whose email matches `ADMIN_EMAIL` to access the admin dashboard.

This free preview is not durable storage: Render's free filesystem is ephemeral, so uploaded profile photos can disappear after a restart or redeploy. Render's free PostgreSQL database expires 30 days after creation and is deleted after a further 14-day grace period unless upgraded. Do not use this configuration for production or valuable user data. Choose a paid persistent storage/database setup before public production use.

To publish future changes, commit them and push the branch to GitHub. Render is configured to auto-deploy changes pushed to its connected branch. A separately configured VPS does not update automatically when you push; deploy the code and built assets there while preserving its `.env`, database, and uploaded files. On macOS with Homebrew, authenticate GitHub without sharing a token:

```bash
brew install gh
gh auth login --hostname github.com --git-protocol https --web
gh auth setup-git
```

Then push your branch with `git push origin main`. Alternatively, configure an SSH key for GitHub and switch the remote to SSH. Never paste a GitHub token into chat or commit it to the repository.

## What is included

- Email and password registration, sign-in, and sign-out.
- Google sign-in is a separate sign-in option shown on the login page, not on registration. A first-time Google login creates a TaskFlow account automatically and sends a sign-in notification to the verified Google email address.
- User-owned projects with a name and color.
- Task create, edit, complete, and delete; notes, due dates, and low/medium/high priority.
- All, today, overdue, and completed filters, plus a light/dark theme.
- Session-authenticated JSON endpoints: `GET/POST /projects`, `PATCH/DELETE /projects/{id}`, `GET/POST /projects/{id}/tasks`, `PATCH/DELETE /projects/{id}/tasks/{taskId}`, and `GET /tasks?due=today` (also `overdue`, `done`, and `all`).
- `GET /api/project-icons` returns public `{name, url}` entries for every supported image in `public/images/icon-new-project/`, for use in project icon pickers.
- Sanctum API endpoints: `GET /api/assets` returns all 13 avatar images, feature/language icons, and brand images; authenticated `/api/user`, `/api/profile`, `/api/projects`, and `/api/tasks` endpoints provide the signed-in user's data and project/task operations. Google sign-in can return a 30-day bearer token and JSON user data by starting at `/auth/google?format=json`. Send that token as `Authorization: Bearer <token>` and revoke it with `POST /api/logout`.
- Browser task cache and pending task mutations in IndexedDB. Changes sync when the app is online again; project changes require a connection.
- An installable PWA shell and optional one-hour task reminders through browser notifications.

Reminders use the browser Notification API. Permission must be enabled by the user, and browser support and background timer behavior vary by device; reopen the app to refresh scheduled reminders after it has been closed.

### Google sign-in and email notification

Google sign-in uses Laravel Socialite's server-side OAuth redirect flow. Configure `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` in `.env`. Google must return a verified email for sign-in to complete.

Google's OAuth callback and verified-email claim authenticate the user directly; there is no extra email verification code. TaskFlow then emails a successful sign-in notification to the verified Google email address. The configured SMTP Gmail account is the sender; it can be different from the recipient. Configure Gmail SMTP in the ignored `.env` file to deliver notifications. For Gmail, use `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, and `MAIL_SCHEME=smtp` (Symfony negotiates STARTTLS on this connection); set `MAIL_MAILER=smtp`, `MAIL_USERNAME` to the sender Gmail address, `MAIL_PASSWORD` to that account's Google App Password, and `MAIL_FROM_ADDRESS` to the sender Gmail address. For implicit TLS, use `MAIL_SCHEME=smtps` with port 465. Google requires 2-Step Verification before creating an App Password. Do not use your normal Google account password. Never commit or share the App Password. Run `php artisan config:clear` after changing `.env`.

In local development, `MAIL_MAILER=log` records the notification in the Laravel log instead of sending it to Gmail, and the API reports `notification_sent: false`. If SMTP notification delivery fails, Google sign-in still completes; the failure is logged and the API also reports `notification_sent: false`.

In Google Cloud Console, configure the OAuth consent screen and add the exact callback URL as an authorized redirect URI, for example `http://localhost:8000/auth/google/callback` (or `http://127.0.0.1:8000/auth/google/callback` if that is the host used in the browser). Use the same callback in `GOOGLE_REDIRECT_URI`. Production must use HTTPS. Never commit or share the Google client secret.

## Tests

```bash
php artisan test
```
# TaskFlowWebSite
