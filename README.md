# Ceylon Energy Services — React

A full React (Vite) rebuild of the Ceylon Energy & Engineering Services
website. All PHP has been removed — this is a static, client-side
React/JS app you can deploy anywhere (Vercel, Netlify, GitHub Pages,
S3, cPanel static hosting, etc).

## What changed from the original

- **No PHP.** The old site was static HTML/CSS/JS plus one PHP script
  (`contact/vanilla-form.php`) that emailed form submissions via
  PHP's `mail()`. That's gone — see **Contact form** below.
- Every section (Nav, Hero, About, What We Do, Packages, Projects
  gallery + lightbox, Contact, Footer) is now a React component in
  `src/components/`.
- The Three.js hero animation and GSAP scroll reveals are ported into
  React hooks (`src/hooks/`) so they mount/unmount cleanly with the
  component lifecycle.
- The project gallery (locations → projects → photos) is driven by
  React state instead of manual DOM manipulation. Edit
  `src/data/albums.js` to add/remove locations, projects, or photos —
  same structure as the original `ALBUMS` array in `main.js`.
- All the original visual design (`main.css`), fonts, and images are
  reused as-is.

## Contact form

The contact form no longer has a server to send mail from. It's wired
for **EmailJS** (https://www.emailjs.com), a free service that sends
email straight from the browser with no backend:

1. Create a free EmailJS account, an email service, and a template
   with `name`, `email`, `subject`, `tel`, `message` variables.
2. Open `src/components/Contact.jsx` and fill in:
   ```js
   const EMAILJS_SERVICE_ID = "your_service_id";
   const EMAILJS_TEMPLATE_ID = "your_template_id";
   const EMAILJS_PUBLIC_KEY = "your_public_key";
   ```
3. Rebuild and deploy.

Until you configure those, submitting the form falls back to opening
the visitor's email client with the message pre-filled to
`info@ceylonenergyservices.com`, so nothing is broken out of the box —
it's just not automatic yet.

## Getting started

```bash
npm install
npm run dev       # local dev server
npm run build      # production build → dist/
npm run preview    # preview the production build locally
```

## Project structure

```
public/
  images/            all site images (unchanged from original)
  type/               icon fonts (Font Awesome, Budicons, Glacial Indifference)
src/
  components/         Navbar, Hero, About, WhatWeDo, Packages,
                       Projects, Lightbox, Contact, Footer
  hooks/              useHeroCanvas (Three.js), useScrollReveal (GSAP)
  data/albums.js       project gallery data — edit this to update photos
  index.css           full site stylesheet (from the original theme)
  App.jsx / main.jsx   app entry
```

## Deploying

`npm run build` produces a static `dist/` folder — upload it to any
static host. No PHP, no Node server, and no database required at
runtime.
