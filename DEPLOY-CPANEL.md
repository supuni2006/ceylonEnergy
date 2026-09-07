# Putting this site live on cPanel (ceylonenergyservices.com)

This is the answer to two questions at once:

1. **Where does the `.env` file go in cPanel?**
2. **Why does the admin panel say _"Adding and removing photos will not
   work yet… nothing is listening at http://localhost:5050"_, and how do
   I fix it?**

They are related, but they are not the same problem. Read the next
section before doing anything — it takes two minutes and saves an hour.

---

## First, understand what you are looking at

This project is **two programs**, not one:

| | What it is | Where it runs now |
|---|---|---|
| **The website + admin panel** | HTML and PHP (`index.html`, `admin/`) | Already on cPanel — working |
| **The gallery API ("the backend")** | Node.js (`server/`, `package.json`) | **Not running anywhere** — this is the problem |

The admin panel can *show* the gallery on its own, because it reads the
list from a file on disk (`assets/data/projects.json`). But **adding or
deleting a photo** has to go through the Node backend, because that is
the half that talks to Cloudinary (the photos) and MongoDB Atlas (the
list).

The red banner is the panel saying: *"I looked for the backend at
`http://localhost:5050` and found nothing there."*

`localhost:5050` is the address you use **on your own laptop** while
developing. On the cPanel server there is no such thing — nothing is
running on port 5050. So there are two things to fix:

- **Put a `.env` file where the PHP panel can read it** (this section is
  the "where does .env go" answer), and
- **Get the Node backend actually running, then point `GALLERY_API_BASE`
  at its real public address** instead of `localhost:5050`.

Doing only the first one will change the error message but will not make
uploads work. You need both.

---

## Step 1 — Create `public_html/.env`

**The location: `public_html/.env`** — the same folder that contains
`index.html` and the `admin` folder. Not inside `admin/`, not inside
`server/`. One level *above* `admin/`.

Why there: the panel builds the path itself in
`admin/inc/config.php` — `ADMIN_ROOT` is `.../admin`, `SITE_ROOT` is the
folder above it, and `admin/inc/api.php` reads `SITE_ROOT . '/.env'`.
So if your admin panel is at
`ceylonenergyservices.com/admin/projects.php`, the file must be at
`public_html/.env`.

```
public_html/
├── .env          <-- create this file
├── .htaccess
├── index.html
├── admin/
│   ├── projects.php
│   └── inc/
└── assets/
```

### How to create it in File Manager

1. cPanel → **File Manager**.
2. Top-right **Settings** → tick **Show Hidden Files (dotfiles)** →
   **Save**. (Without this, files starting with `.` are invisible and you
   will think your file vanished.)
3. Open **public_html**.
4. Click **+ File**, name it exactly `.env` (leading dot, no `.txt`),
   **Create New File**.
5. Right-click `.env` → **Edit** → **Edit** again if it warns about
   encoding.
6. Paste the block below, fill in your real values, **Save Changes**.

### What to put in `public_html/.env`

The PHP panel reads **only two** settings out of this file
(`GALLERY_API_BASE` and `ADMIN_API_TOKEN` — nothing else). So keep your
database and Cloudinary passwords **out** of this copy: this file sits
inside your public web folder, and the fewer secrets it holds, the less
there is to lose if it ever gets exposed.

```dotenv
# Where the Node backend lives. Fill this in AFTER Step 3 below.
# It must be the real public address, never localhost.
GALLERY_API_BASE=https://api.ceylonenergyservices.com

# The shared password between the admin panel and the backend.
# Must be character-for-character identical to the ADMIN_API_TOKEN the
# backend uses, or every upload comes back "401 Unauthorized".
ADMIN_API_TOKEN=paste_your_long_random_token_here
```

Generate the token **once** and reuse the same value in both places:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

No Node on your laptop? Any 40+ character random string of letters and
numbers works. Do **not** leave it as
`replace_with_a_long_random_string` — the backend refuses to start with
that value, on purpose, because it is published in `.env.example`.

---

## Step 2 — Check the file is not readable from the internet

This matters. By default Apache is perfectly happy to serve a file called
`.env` to anybody who asks for it.

Open this in your browser:

```
https://ceylonenergyservices.com/.env
```

- **403 Forbidden / "Access denied"** — correct, move on.
- **The file's contents appear, or it downloads** — your `.htaccess` is
  not in place. Make sure `public_html/.htaccess` contains the
  `<FilesMatch "^\.">` block from this repository (it is in the
  `.htaccess` file at the project root — upload that file too), then
  test again. **Do not skip this**, and if the contents were visible,
  change the token afterwards.

---

## Step 3 — Get the Node backend running

Check cPanel first: **Software → Setup Node.js App**.

- **The icon is there** → follow **Option A**.
- **The icon is missing** → your hosting plan has no Node.js. Follow
  **Option B**. Nothing you do in File Manager can create Node support.

### Option A — cPanel's "Setup Node.js App"

**A1. Make a subdomain for the API.**
cPanel → **Domains** → **Create A Domain** →
`api.ceylonenergyservices.com`. Using a subdomain avoids a whole class of
confusing path problems that come from running a Node app inside a
sub-folder of the main site.

**A2. Upload the backend outside `public_html`.**
Create a folder `ceylon-api` in your home directory (the level *above*
`public_html`) and upload into it:

```
ceylon-api/
├── package.json
├── package-lock.json
└── server/
```

Keeping it out of `public_html` means nobody can browse to your backend
source code. Do **not** upload `node_modules` — cPanel builds it for you
in step A4.

**A3. Create the app.**
Setup Node.js App → **Create Application**:

| Field | Value |
|---|---|
| Node.js version | 18 or newer (the project requires ≥18) |
| Application mode | Production |
| Application root | `ceylon-api` |
| Application URL | `api.ceylonenergyservices.com` |
| Application startup file | `server/server.js` |

Click **Create**.

**A4. Install the dependencies.**
On the app's page, click **Run NPM Install**. Wait for it to finish.

**A5. Give the backend its settings.**
The backend needs *all* the credentials (unlike the PHP panel, which
needed two). Two ways — pick one:

*Easier:* create a second `.env`, this time at `ceylon-api/.env` (same
File Manager steps as before, just a different folder):

```dotenv
NODE_ENV=production
ALLOWED_ORIGINS=https://ceylonenergyservices.com,https://www.ceylonenergyservices.com

MONGODB_URI=mongodb+srv://USER:PASSWORD@yourcluster.xxxxx.mongodb.net/?retryWrites=true&w=majority
MONGODB_DB_NAME=ceylonenergy

CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
CLOUDINARY_FOLDER=ceylon-energy/completed-projects

# EXACTLY the same string as in public_html/.env
ADMIN_API_TOKEN=paste_the_same_long_random_token_here
```

*Or:* use the **Environment variables** box on the Setup Node.js App
page and add the same names and values one by one. That is what your
"Add Env In cPanel" search was about — but note it configures the **Node
app only**. It does nothing for the PHP admin panel, which is why Step 1
exists.

Two notes on the values:

- If your MongoDB password contains any of `@ : / ? # [ ] %`, it must be
  percent-encoded in the URI (`p@ss` → `p%40ss`), or the driver silently
  reads the string wrong.
- Leave `PORT` out entirely. cPanel/Passenger assigns the port itself;
  setting `PORT=5050` here helps nothing.

**A6. Allow the server to reach MongoDB Atlas.**
Atlas → **Network Access** → **Add IP Address**. Add your cPanel server's
IP (shown in cPanel's right-hand sidebar as "Shared IP Address"). If you
skip this, the backend starts but every request hangs and then fails —
Atlas blocks unknown addresses by default.

**A7. Start it and check.**
Click **Restart** (or **Start**), then open:

```
https://api.ceylonenergyservices.com/api/health
```

You want JSON that looks like:

```json
{"ok":true,"service":"ceylon-energy-api","mongo":"connected","cloudinary":"configured"}
```

- `"mongo":"disconnected"` → the Atlas IP allowlist (A6) or a wrong
  `MONGODB_URI`.
- A cPanel error page instead of JSON → the app did not start. The
  **stderr log** link on the Setup Node.js App page names the reason; a
  missing variable is printed as a plain list of what is missing.

### Option B — no Node.js in your cPanel

Host the backend somewhere that does Node for free (Render, Railway,
Fly.io — Render's free tier is the usual choice) and deploy this same
repository there with start command `npm start`. Set every variable from
A5 in that host's own environment-variables screen. It gives you a URL
like `https://ceylon-energy-api.onrender.com` — that URL is what goes
into `GALLERY_API_BASE`.

Everything else in this guide is unchanged. The PHP panel does not care
where the backend lives, only that it can reach it over HTTPS.

---

## Step 4 — Point the panel at the real backend

Go back to `public_html/.env` and set `GALLERY_API_BASE` to the address
that returned JSON in step A7 — **without** the `/api` part and
**without** a trailing slash:

```dotenv
GALLERY_API_BASE=https://api.ceylonenergyservices.com
```

The panel adds `/api/health`, `/api/projects` and so on by itself. If you
write `https://api.ceylonenergyservices.com/api`, it ends up asking for
`/api/api/health` and you get a 404.

Save, then reload `https://ceylonenergyservices.com/admin/projects.php`.
The red banner should be gone. Add a test location to confirm writes work
end to end, then delete it.

---

## If it still does not work

The panel tries hard to tell you what is wrong. Match its wording here:

| The banner says | What it means | Fix |
|---|---|---|
| `Nothing is listening at http://localhost:5050` | `.env` was not found, or `GALLERY_API_BASE` is still the default | The file must be `public_html/.env` exactly, with hidden files shown so you can confirm it is there. Check for `.env.txt` — File Manager adds that silently on some setups |
| `Nothing is listening at https://api...` | The file is being read (good) but the backend is down | Restart the app in Setup Node.js App and re-check `/api/health` |
| `ADMIN_API_TOKEN is not set in .env` | The panel found the file but not that key | Check for a typo in the name, and that there are no spaces around the `=` |
| `401` / `Unauthorized` on upload | The two `ADMIN_API_TOKEN` values do not match | Copy-paste the same string into both `.env` files. Watch for a trailing space |
| `404` from the API | `GALLERY_API_BASE` has `/api` or a trailing slash on the end | Remove it — see Step 4 |
| Upload fails on big photos | PHP's upload limit | `.user.ini` in `public_html` raises it. Give it a few minutes — PHP caches that file |
| Gallery lists photos but nothing can be added | Normal when the backend is down | That list comes from `assets/data/projects.json` on disk, not from the API |

---

## Recap: which file goes where

| File | Location | Holds | Needed by |
|---|---|---|---|
| `.env` | `public_html/.env` | `GALLERY_API_BASE`, `ADMIN_API_TOKEN` | The PHP admin panel |
| `.env` | `ceylon-api/.env` (or the cPanel env-vars box) | MongoDB, Cloudinary, `ADMIN_API_TOKEN`, `ALLOWED_ORIGINS` | The Node backend |
| `.htaccess` | `public_html/.htaccess` | The rules that stop `.env` being downloaded | Apache |

Neither `.env` is in git — `.gitignore` blocks them, on purpose. They are
created by hand on the server, once, and they are the only place your
real passwords exist.
