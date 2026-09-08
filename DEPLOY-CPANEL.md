# Putting this site live on cPanel (ceylonenergyservices.com)

The whole site is PHP and ordinary files. There is no database to create,
no Node.js app to keep running, and no outside service to sign up for.
Deploying is: **upload the folder, set two permissions, choose an admin
password.** That is the entire job.

---

## What this site is made of

| Part | What it is | Notes |
|---|---|---|
| The website | `index.html`, `style.css`, `assets/` | Plain files. Apache serves them as-is. |
| The admin panel | `admin/` | PHP. Where you add project photos, awards and the company profile. |
| The gallery | `assets/images/completed-projects/` + `assets/data/projects.json` | The photos, and a list saying which photo belongs to which project. |

The photos live in folders on your own hosting, one folder per location
and one inside that per project:

```
assets/images/completed-projects/
├── loc-colombo/
│   ├── proj-project-01/
│   │   ├── photo-001.png          <- the original you uploaded
│   │   ├── photo-002.png
│   │   └── thumbs/                <- small copies, made automatically
│   │       ├── photo-001-400.jpg      (grid cards)
│   │       ├── photo-001-900.jpg      (photo view)
│   │       └── photo-001-1600.jpg     (full screen)
│   └── proj-wellampitiya/
└── loc-kaluthara/
```

`assets/data/projects.json` describes that tree — the readable names,
and which photo goes where. The admin panel rewrites it whenever you add
or remove a photo, and `assets/js/main.js` reads it when a visitor opens
the site. That is the entire moving part.

Originals are never modified. The three smaller copies exist so a visitor
on a phone downloads a 30KB thumbnail instead of a 3MB original.

---

## Step 1 — Upload the files

Put everything into **`public_html`**, so that `index.html` sits directly
inside it:

```
public_html/
├── .htaccess
├── .user.ini
├── index.html
├── style.css
├── admin/
├── assets/
├── contact/
└── storage/
```

The quickest way is to upload a `.zip` of the project into `public_html`
and use File Manager's **Extract**. Then delete the zip.

If cPanel asks which PHP version to use: **PHP 8.0 or newer**, and make
sure `gd` is ticked under **Select PHP Version → Extensions**. That is
the part that shrinks photos for the web. The site still works without
it — it just serves the full-size originals, which is slow on a phone.

> **Hidden files.** `.htaccess` and `.user.ini` start with a dot, so File
> Manager hides them by default and you will think they did not upload.
> Turn them on: **Settings** (top right) → tick **Show Hidden Files
> (dotfiles)** → **Save**.

Both matter:

- `.htaccess` — stops dotfiles being downloaded, switches off directory
  listing, and tells browsers to cache the photos.
- `.user.ini` — raises PHP's upload limit from the stock 2MB, which
  otherwise rejects photos before any of this code runs.

---

## Step 2 — Make two folders writable

The admin panel saves uploaded photos into your hosting, so PHP needs
permission to write in two places:

| Path | Permission |
|---|---|
| `public_html/assets/images/completed-projects` | `755` |
| `public_html/assets/data/projects.json` | `644` |

In File Manager: right-click the folder or file → **Change Permissions**
→ set the number → tick **Recurse into subdirectories** for the folder.

Most cPanel accounts already have these right. If yours does not, an
upload appears to do nothing at all — no error, no photo — which is why
it is worth checking now rather than later.

**You do not have to guess.** Once you can log in, open
`https://ceylonenergyservices.com/admin/check-env.php`. It lists every
folder the site writes to and says plainly whether this server allows it,
along with your PHP version, whether `gd` is on, and your upload limits.

---

## Step 3 — Create your admin password

Open:

```
https://ceylonenergyservices.com/admin/setup.php
```

Choose a password. It is saved, hashed, into `admin/data/auth.php` on the
server. This page only works once — afterwards it sends you to the login
page instead.

Then log in at `https://ceylonenergyservices.com/admin/login.php`.

> Forgotten it later? There is no reset email. Delete
> `admin/data/auth.php` in File Manager and `setup.php` will let you
> choose a new one.

---

## Step 4 — Check the gallery

Open the site and scroll to **Our Projects**. You should see the
locations, and clicking through should show the photos.

If the photos are missing or broken, the list and the folders have
drifted apart — usually because photos were copied in by hand. Log in and
open:

```
https://ceylonenergyservices.com/admin/rebuild-gallery.php
```

Press **Rescan folders now**. It reads the folders, rewrites
`projects.json` to match, and makes any missing small copies. It is safe
to run whenever, and it never deletes photos.

---

## Adding photos day to day

**A few at a time —** log in, open **Projects**, and use the upload form
under the project you want. The website updates immediately.

**A lot at once —** the form takes one photo at a time, so for a big
batch use File Manager instead:

1. Go to `public_html/assets/images/completed-projects/`.
2. Open the location folder, then the project folder inside it.
   To start new ones, create folders named like the existing ones:
   `loc-galle`, and inside it `proj-new-site`. Lower case, words joined
   by dashes, no spaces.
3. Upload the JPG or PNG photos into the project folder.
4. Open **Rescan gallery folders** in the admin panel and press the
   button.

A folder made this way is named after itself — `loc-galle` becomes
"Galle". To control the spelling (say "Galle Fort"), add the location on
the **Projects** page first and it will be kept.

---

## Backups

Removing a photo, project or location in the admin panel does not erase
anything. The files are moved to:

```
public_html/storage/backups/deleted-gallery/
```

named with the date and time they were removed. To undo a mistake, copy
the file back into its project folder and run the rescan. Clear that
folder out yourself when you are sure you no longer need what is in it —
nothing empties it automatically.

---

## When something is wrong

| What you see | What it means | Fix |
|---|---|---|
| Upload seems to do nothing | The photo folder is not writable | Step 2. `check-env.php` names the exact folder |
| "Could not save the photo into…" | Same thing, said out loud | Step 2 |
| Photos show but look slow and huge | `gd` is switched off, so no small copies were made | cPanel → Select PHP Version → Extensions → tick `gd`, then run the rescan |
| Broken images in the gallery | The list names photos that are not on the server | Run **Rescan gallery folders** |
| Gallery says "being updated" | `assets/data/projects.json` is missing or unreadable | Check it is there and set to `644`, then run the rescan |
| "That photo is larger than…" | Bigger than the 10MB limit in the panel | Shrink the photo, or raise `MAX_IMAGE_BYTES` in `admin/inc/config.php` |
| Big photos fail, small ones work | PHP's own upload limit | `.user.ini` raises it — make sure that file uploaded. PHP caches it for a few minutes, so wait before retesting |
| The admin panel is a wall of unstyled text | `admin/admin.css` did not upload | Re-upload the `admin` folder |
| `https://…/.user.ini` downloads instead of refusing | `.htaccess` did not upload | Step 1 — turn on hidden files and check |

---

## Moving the site somewhere else

Copy `public_html` across. That is all of it: the photos, the list, the
awards, the company profile and the login. There is no database export
and no service to reconnect, because there was never anything outside
this folder.

---

## Running it on your own computer first

You need PHP installed; nothing else.

```bash
./serve.sh
```

That starts PHP's built-in server on <http://localhost:8000> with the
same upload limits as production (`local-php.ini`), so a photo that
uploads locally will upload on the server too.

`serve.sh` and `local-php.ini` are for local use only. They do nothing on
cPanel, and `.htaccess` blocks them from being served.
