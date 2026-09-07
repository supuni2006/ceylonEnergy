# Ceylon Energy — Gallery Backend

Project photos are stored in **Cloudinary** (which also delivers and
optimises them) and the gallery structure — locations, projects, which
photo belongs where — is stored in **MongoDB Atlas**.

---

## Why this fixes the 404 errors

The old site built its image URLs like this:

```
assets/images/completed-projects/project-1.jpg
assets/images/completed-projects/project-63.jpg
```

Those filenames were hardcoded in `assets/js/main.js`, but **no file with
that name exists anywhere in the project**. The real photos are at:

```
assets/images/completed-projects/loc-rathnapura/proj-belihuloya-project-01/photo-005.png
```

So every single request 404'd. The browser then fell back to
`dummy.png`, which is why the gallery looked half-broken.

`main.js` no longer hardcodes anything — it asks this backend what
photos exist and gets back working URLs.

---

## One-time setup

### 1. Install

```bash
npm install
```

### 2. Create your `.env`

```bash
cp .env.example .env
```

Then open `.env` and fill in the real values. Where to find each one:

| Setting | Where to get it |
|---|---|
| `MONGODB_URI` | Atlas → your cluster → **Connect** → **Drivers** → copy the string, then replace `<db_password>` with your real password |
| `CLOUDINARY_CLOUD_NAME` | Cloudinary Console → Settings → API Keys → "Cloud name" at the top |
| `CLOUDINARY_API_KEY` | same page |
| `CLOUDINARY_API_SECRET` | same page (click the eye icon to reveal it) |
| `ADMIN_API_TOKEN` | make one up — see below |

Generate a strong admin token:

```bash
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

> **If your database password contains any of** `@ : / ? # [ ] %` **it must be
> percent-encoded in the URI.** For example `p@ssw0rd` becomes `p%40ssw0rd`.
> Otherwise the driver misreads the string and the connection fails with a
> confusing authentication error.

### 3. Allow your server to reach Atlas

In Atlas → **Network Access** → **Add IP Address**, add the IP of
wherever the backend runs. Atlas rejects every connection from an
unlisted IP, and that is the single most common reason this fails.

### 4. Check both services answer

```bash
npm run check-db
```

You want two OK lines. If either fails, this tells you which one and why,
so you are not guessing.

---

## Moving your existing 51 photos to Cloudinary

Preview first — this uploads nothing and does not even need `.env`:

```bash
npm run migrate -- --dry
```

It reads `assets/docs/gallery.json` and reports what it would do:

```
  Summary
   Locations created: 7
   Projects created:  16
   Photos uploaded:   51
   Missing on disk:   0
```

When that looks right, do it for real:

```bash
npm run migrate
```

This is **safe to re-run**. Each photo remembers the file it came from,
so a second run skips what it already uploaded instead of creating
duplicates. If the connection drops halfway, just run it again.

Then write the static fallback file:

```bash
npm run export
```

---

## Running it

```bash
npm start          # production
npm run dev        # restarts on file changes
```

Check it is alive:

```bash
curl http://localhost:5000/api/health
curl http://localhost:5000/api/projects
```

---

## Pointing the website at the API

In `index.html`, before `main.js` loads, add:

```html
<script>window.CE_GALLERY_API = "https://your-api-domain.com";</script>
```

If you leave this out, the site reads `assets/data/projects.json`
instead — which still works, it is just only as fresh as the last time
you ran `npm run export`.

Also add your website's address to `ALLOWED_ORIGINS` in `.env`, or the
browser will block the request:

```
ALLOWED_ORIGINS=https://ceylonenergyservices.com,https://www.ceylonenergyservices.com
```

---

## How the site loads the gallery

```
1. the API  (window.CE_GALLERY_API)   -- always current
2. assets/data/projects.json          -- fallback if the API is down
3. a short "being updated" message    -- rather than broken images
```

That ordering is deliberate: a backend outage should degrade the gallery
to slightly-stale, never to a grid of broken thumbnails.

---

## API reference

### Public — no token

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/health` | Is the server up, is Mongo connected |
| `GET` | `/api/projects` | The whole gallery |
| `GET` | `/api/projects/:locationSlug` | One location |

### Admin — requires `Authorization: Bearer <ADMIN_API_TOKEN>`

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/projects/locations` | Add a location |
| `POST` | `/api/projects/:loc/projects` | Add a project |
| `POST` | `/api/projects/:loc/:proj/photos` | Upload photos (field name `photos`, up to 20, 10MB each) |
| `DELETE` | `/api/projects/:loc/:proj/photos/:photoId` | Remove one photo |
| `DELETE` | `/api/projects/:loc/:proj` | Remove a project and its photos |
| `DELETE` | `/api/projects/:loc` | Remove a location and everything in it |

Example upload:

```bash
curl -X POST http://localhost:5000/api/projects/loc-colombo/proj-project-01/photos \
  -H "Authorization: Bearer YOUR_ADMIN_API_TOKEN" \
  -F "photos=@/path/to/photo1.jpg" \
  -F "photos=@/path/to/photo2.jpg"
```

Every photo comes back with three ready-made URLs:

```json
{
  "id": "6a1f...",
  "caption": "Colombo — Project 01",
  "thumb":  "https://res.cloudinary.com/<cloud>/image/upload/c_fill,f_auto,q_auto,w_400,h_300/v1/...",
  "medium": "https://res.cloudinary.com/<cloud>/image/upload/c_limit,f_auto,q_auto,w_900/v1/...",
  "large":  "https://res.cloudinary.com/<cloud>/image/upload/c_limit,f_auto,q_auto,w_1600/v1/..."
}
```

One file is stored; the three sizes are generated by Cloudinary on
request. `f_auto` sends WebP/AVIF to browsers that support them and JPG
to those that don't, and `q_auto` picks the smallest quality that still
looks clean — so a phone downloads a fraction of what a 3MB original
would cost.

---

## The admin panel

`admin/projects.php` reads and writes the same gallery, so it needs the
backend running:

```bash
npm start          # leave this running in one terminal
./serve.sh         # the PHP site in another
```

Then open `http://localhost:8000/admin/projects.php`.

How the two halves split the work:

- **Listing** the gallery reads `assets/data/projects.json` directly, so
  the admin screen still shows everything when the backend is stopped.
- **Adding, uploading and deleting** go through the API, which updates
  Cloudinary and MongoDB together and then rewrites that same JSON file.

That second point means you do **not** need to run `npm run export` after
using the admin panel — it refreshes the file itself. `npm run export`
stays useful for regenerating the file by hand, e.g. after editing the
database directly.

If the backend is not running, the page shows a red banner saying so, and
any upload fails with a message telling you to start it. Nothing is
silently lost.

`GALLERY_API_BASE` in `.env` tells the admin panel where the API is. The
PHP side reads that file directly, so both halves share one set of
credentials.

> **Uploads now go to Cloudinary, not to disk.** Nothing is written to
> `assets/images/completed-projects/` any more, and deleting a photo
> removes it from Cloudinary permanently — there is no local backup copy
> to restore from, unlike the old version.

---

## Shrinking the git repository

Your last push was rejected because `assets.zip` (174MB) and `admin.zip`
(190MB) are over GitHub's 100MB-per-file limit. Both are now in
`.gitignore`.

Once the migration is done and the gallery is confirmed working from
Cloudinary, you can drop the photo files from git too:

```bash
git rm -r --cached assets/images/completed-projects
echo "assets/images/completed-projects/" >> .gitignore
git commit -m "Serve project photos from Cloudinary"
```

`--cached` removes them from git only — **the files stay on your
computer.** Do this only after `npm run export` and after checking the
live gallery, since until then the fallback file still points at these
local files.

---

## When something is wrong

| What you see | What it usually means |
|---|---|
| `Missing required environment variables` | `.env` does not exist or a line is blank — copy it from `.env.example` |
| MongoDB fails, mentions IP / allowlist | Add your server's IP under Atlas → Network Access |
| MongoDB fails, authentication error | Wrong password, or a special character that needs percent-encoding |
| Cloudinary fails on `npm run check-db` | Cloud name, API key or secret is wrong |
| Gallery empty, browser console shows a CORS error | Add your site's address to `ALLOWED_ORIGINS` |
| Gallery shows "being updated" | Both the API and `projects.json` failed — check `npm run check-db` |
| `413` on upload | Image over 10MB, or more than 20 files at once |
