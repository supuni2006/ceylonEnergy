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

This is the **Create Application** form. Do the checks in order — the
first one decides whether any of the rest is worth doing.

**A1. Open the "Node.js version" dropdown before anything else.**

The form defaults to **10.24.1**, and this project *cannot* run on it.
It needs **Node 18 or newer**, for three separate reasons:

- Express 5 (the web framework) requires Node 18+.
- `server/server.js` uses `fetch()`, which only exists as a built-in
  from Node 18 on. On Node 10 it is an instant crash at startup.
- Mongoose 8 (the MongoDB driver) requires Node 16+.

So: open that dropdown and pick the **highest version offered** (20 or
22 if they are there, otherwise 18).

**If the dropdown's highest option is below 18, stop here.** Nothing you
type into the rest of this form will make it work. Either ask your host
to enable a newer Node.js, or use **Option B** below.

**A2. Create the subdomain — in a second browser tab.**

The "Application URL" dropdown currently only offers
`ceylonenergyservices.com`, because `api.ceylonenergyservices.com` does
not exist yet. Leave this form open, and in a new tab go to
cPanel → **Domains** → **Create A Domain** → enter
`api.ceylonenergyservices.com` → **Submit**.

Then come back to this tab and **reload the page** so the new subdomain
appears in the dropdown. (Reloading means re-picking the Node version
from A1 — that is fine, nothing is lost.)

> Prefer not to make a subdomain? You can instead leave the dropdown on
> `ceylonenergyservices.com` and type `nodeapi` into the box next to it,
> giving `https://ceylonenergyservices.com/nodeapi`. That works too —
> the server now trims the folder prefix off incoming requests by
> itself. Its one advantage is that your existing SSL certificate
> already covers it, so you skip step A8. Everything else below is the
> same, with `https://ceylonenergyservices.com/nodeapi` wherever this
> guide says `https://api.ceylonenergyservices.com`.

**A3. Fill in the form.**

| Field | What to put | Why |
|---|---|---|
| Node.js version | The highest offered, **never below 18** | See A1 |
| Application mode | **Production** | Sets `NODE_ENV=production`, which hides internal error details from strangers. The form defaults to Development |
| Application root | `ceylon-api` | A folder in your home directory, **not** inside `public_html`. cPanel creates it for you. Keeping the backend out of the web folder means nobody can read its source over the internet |
| Application URL | `api.ceylonenergyservices.com` | The subdomain from A2 |
| Application startup file | `server/server.js` | The file `npm start` runs. Note the folder — it is not `app.js` |

**A4. Add the environment variables — this is the "add env in cPanel" part.**

Use the **Environment variables** table at the bottom of this same form.
Click **ADD VARIABLE**, type the name and value, click **DONE**, and
repeat. cPanel injects these straight into the app, so the backend needs
no `.env` file of its own.

| Name | Value |
|---|---|
| `MONGODB_URI` | Your full Atlas connection string |
| `MONGODB_DB_NAME` | `ceylonenergy` |
| `CLOUDINARY_CLOUD_NAME` | From Cloudinary → Settings → API Keys |
| `CLOUDINARY_API_KEY` | Same page |
| `CLOUDINARY_API_SECRET` | Same page |
| `CLOUDINARY_FOLDER` | `ceylon-energy/completed-projects` |
| `ADMIN_API_TOKEN` | Your long random token — **identical** to the one in `public_html/.env` |
| `ALLOWED_ORIGINS` | `https://ceylonenergyservices.com,https://www.ceylonenergyservices.com` — list **both** spellings; a browser treats `www.` as a different site |

Three things not to do:

- **Do not add `GALLERY_API_BASE` here.** This is the most natural
  mistake on this page and it costs an evening. That setting tells the
  *PHP admin panel* where to find the backend — but PHP never sees
  cPanel's environment variables, which belong to the Node process
  alone. Setting it here changes nothing at all, and the panel goes on
  reporting `localhost:5050` while the value sits in front of you
  looking correct. It goes in `public_html/.env`, and only there.
- **Do not add `PORT`.** Passenger assigns the port itself and ignores
  yours; setting it only confuses you later.
- **Do not add `NODE_ENV`.** The "Application mode" dropdown already
  sets it.

And one thing to watch: if your MongoDB password contains any of
`@ : / ? # [ ] %`, it must be percent-encoded inside the URI (`p@ss`
becomes `p%40ss`), or the driver reads the string wrong and the
connection fails with a confusing authentication error.

Now click **CREATE** (top right).

**A5. Upload the backend files.**

cPanel has just created `/home/YOURUSER/ceylon-api`. Open **File
Manager**, go into it, and upload:

```
ceylon-api/
├── package.json
├── package-lock.json
└── server/          (the whole folder, with its config/ middleware/ models/ routes/ subfolders)
```

Do **not** upload `node_modules` — it is large and the next step builds
it properly for this server's Node version. If cPanel put a sample
`app.js` in there, ignore it; your startup file points at
`server/server.js`.

**A6. Install the dependencies.**

Back on Setup Node.js App, open your app and click **Run NPM Install**.
It reads `package.json`, so it will fail if step A5 has not finished.
Wait for it to report success.

**A7. Let the server reach MongoDB Atlas.**

Atlas → **Network Access** → **Add IP Address**. Add your cPanel
server's IP, shown in cPanel's right-hand sidebar as "Shared IP
Address".

Do not skip this. Atlas rejects unknown addresses by default, and the
backend calls `connectDB()` *before* it starts listening — so a blocked
IP means the app exits at startup and every page on the subdomain shows
a cPanel error instead of your API.

**A8. Wait for the subdomain's SSL certificate.**

A brand-new subdomain has no HTTPS certificate for a few minutes, and
until it does, the PHP panel's request will fail with an SSL error
rather than a helpful message. Go to cPanel → **SSL/TLS Status**, tick
`api.ceylonenergyservices.com`, and click **Run AutoSSL**. Wait until it
shows a valid certificate. (Skip this if you used the `/nodeapi` folder
option in A2 — your main certificate already covers it.)

**A-help. "Directory should not contain spaces" and CREATE will not go through.**

This one wastes afternoons, because the field it names usually looks
perfectly clean. Two things to know first: the red toast **stays on
screen until you click its ✕**, so what you are looking at may be the
previous attempt rather than a new one — dismiss it before judging. And
a pasted value is the usual culprit: copying a name out of a table or a
chat message drags an invisible trailing space along with it.

Find it by halving the form instead of guessing:

1. Hard-reload the page (**Cmd/Ctrl + Shift + R**) to clear any stale
   form state, and dismiss the toast.
2. Fill in **only the five fields at the top** — version, mode, root,
   URL, startup file — and **delete every environment-variable row**.
   Click **CREATE**.

That splits the problem in half:

- **It creates.** The bad value was in an environment variable, not a
  path. Add them back one at a time from the app's own page. The usual
  offender is `ALLOWED_ORIGINS`, because a comma-separated list is
  natural to type as `a.com, b.com` — that space after the comma has to
  go: `https://ceylonenergyservices.com,https://www.ceylonenergyservices.com`
- **It still fails.** The problem is one of the five fields. Clear each
  one with **Cmd/Ctrl + A** then **Delete**, and retype it by hand —
  never paste. If it still refuses, create the folder in File Manager
  first, then type the first few letters into Application root and
  **click the folder from the autocomplete dropdown** rather than
  finishing the word. A value chosen from that list cannot contain
  stray whitespace.

If cPanel keeps refusing after all of that, do not keep fighting it —
Option B below gets the same backend running in about five minutes.

**A9. Start it and check.**

Click **Restart** on the app, then open this in your browser:

```
https://api.ceylonenergyservices.com/api/health
```

You are looking for:

```json
{"ok":true,"service":"ceylon-energy-api","mongo":"connected","cloudinary":"configured"}
```

If you get that, the hard part is done — go to Step 4.

If not, read what you got:

| What you see | What it means |
|---|---|
| `"mongo":"disconnected"` | The Atlas IP allowlist (A7), or a wrong/badly-encoded `MONGODB_URI` |
| `"cloudinary":"missing"` | A typo in one of the `CLOUDINARY_*` variable names |
| A cPanel error page, not JSON | The app did not start. Open the **stderr log** link on the app's page. A missing variable is printed there as a plain list; `SyntaxError` or `fetch is not defined` means the Node version is below 18 (back to A1) |
| `404` with `{"ok":false,"error":"No route for GET /..."}` | The app is running fine, you just asked for the wrong path. Check you typed `/api/health` |


### Option B — no Node.js in your cPanel (or cPanel will not cooperate)

Host the backend somewhere that runs Node for free. Render is the usual
choice: **New → Web Service** → connect this GitHub repository →
Runtime **Node**, Build command `npm install`, Start command
`npm start`. Put every variable from A4 into its **Environment** tab —
`ALLOWED_ORIGINS` and `ADMIN_API_TOKEN` included, `PORT` excluded, since
Render sets that itself and `server/config/env.js` already reads it.

Allow Render's outbound IPs in Atlas → Network Access, the same way as
step A7.

You get a URL like `https://ceylon-energy-api.onrender.com`. That is
what goes into `GALLERY_API_BASE` — nothing else in this guide changes,
because the PHP panel does not care where the backend lives, only that
it can reach it over HTTPS.

> **The one catch with a free tier:** it sleeps after about 15 minutes
> of no traffic and takes up to a minute to wake. The admin panel's
> health check gives up after 5 seconds, so the first visit after a
> quiet spell shows the red banner even though nothing is wrong —
> wait a moment and reload and it clears. Uploads themselves wait far
> longer (2 minutes) and go through fine. A paid always-on instance, or
> getting cPanel's own Node.js working, avoids the annoyance entirely.

---

## Step 4 — Point the panel at the real backend

Go back to `public_html/.env` and set `GALLERY_API_BASE` to the address
that returned JSON in step A9 — **without** the `/api` part and
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
| `401` / `Unauthorized` on upload | The two `ADMIN_API_TOKEN` values do not match | Copy-paste one string into `public_html/.env` **and** the cPanel environment-variables table. Watch for a trailing space |
| The subdomain shows a cPanel error, not JSON | The Node app crashed at startup | Open the **stderr log** on the Setup Node.js App page. `fetch is not defined` or a `SyntaxError` means the Node version is below 18 — see step A1 |
| `SSL certificate problem` in the banner | The subdomain has no certificate yet | cPanel → SSL/TLS Status → Run AutoSSL (step A8) |
| `404` from the API | `GALLERY_API_BASE` has `/api` or a trailing slash on the end | Remove it — see Step 4 |
| Upload fails on big photos | PHP's upload limit | `.user.ini` in `public_html` raises it. Give it a few minutes — PHP caches that file |
| Gallery lists photos but nothing can be added | Normal when the backend is down | That list comes from `assets/data/projects.json` on disk, not from the API |

---

## Recap: which file goes where

| File | Location | Holds | Needed by |
|---|---|---|---|
| `.env` | `public_html/.env` | `GALLERY_API_BASE`, `ADMIN_API_TOKEN` | The PHP admin panel |
| *(no file)* | The **Environment variables** table on the Setup Node.js App page | MongoDB, Cloudinary, `ADMIN_API_TOKEN`, `ALLOWED_ORIGINS` | The Node backend |
| `.htaccess` | `public_html/.htaccess` | The rules that stop `.env` being downloaded | Apache |

`public_html/.env` is not in git — `.gitignore` blocks it, on purpose.
It is created by hand on the server, once. Together with the cPanel
environment-variables table, those are the only two places your real
passwords exist.

If you would rather keep the backend's settings in a file than in the
cPanel table, put a second `.env` at `ceylon-api/.env` with the same
names and values — the backend loads `dotenv`, so it reads that file
too. Use one or the other, not both, or you will spend an afternoon
wondering which value is winning.
