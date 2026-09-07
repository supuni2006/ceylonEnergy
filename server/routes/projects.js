/**
 * Gallery API.
 *
 *   Public (no token)
 *     GET    /api/projects                         whole gallery tree
 *     GET    /api/projects/:locationSlug           one location
 *
 *   Admin (Authorization: Bearer <ADMIN_API_TOKEN>)
 *     POST   /api/projects/locations                          add a location
 *     POST   /api/projects/:locSlug/projects                  add a project
 *     POST   /api/projects/:locSlug/:projSlug/photos          upload photos
 *     DELETE /api/projects/:locSlug/:projSlug/photos/:photoId remove one photo
 *     DELETE /api/projects/:locSlug/:projSlug                 remove a project
 *     DELETE /api/projects/:locSlug                           remove a location
 */
const express = require('express');
const multer = require('multer');

const Location = require('../models/Location');
const { requireAdmin } = require('../middleware/auth');
const { buildVariants, uploadBuffer, deleteImage } = require('../config/cloudinary');

const router = express.Router();

// Files are held in memory and streamed straight to Cloudinary — nothing
// is ever written to the server's disk, so there is no upload folder to
// run out of space or to back up.
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 10 * 1024 * 1024, files: 20 }, // 10MB each, 20 per request
  fileFilter: (req, file, cb) => {
    if (/^image\/(jpeg|png|webp|avif)$/.test(file.mimetype)) return cb(null, true);
    cb(new Error('Only JPG, PNG, WebP or AVIF images are allowed.'));
  },
});

/** Turn a name into a URL-safe slug: "Belihuloya Site #2" -> "belihuloya-site-2". */
function slugify(str) {
  return (
    String(str)
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'item'
  );
}

/**
 * Shape a stored document into what the browser needs: every photo
 * carries ready-to-use thumb/medium/large URLs, so the frontend never
 * has to know how Cloudinary builds URLs.
 */
function serializeLocation(loc) {
  const projects = [...loc.projects]
    .sort((a, b) => a.order - b.order)
    .map((proj) => {
      const photos = [...proj.photos]
        .sort((a, b) => a.order - b.order)
        .map((photo) => ({
          id: String(photo._id),
          caption: photo.caption || '',
          width: photo.width,
          height: photo.height,
          ...buildVariants(photo.publicId),
        }));

      return {
        id: String(proj._id),
        slug: proj.slug,
        name: proj.name,
        description: proj.description || '',
        photoCount: photos.length,
        cover: photos[0] || null,
        photos,
      };
    });

  return {
    id: String(loc._id),
    slug: loc.slug,
    name: loc.name,
    projectCount: projects.length,
    photoCount: projects.reduce((sum, p) => sum + p.photoCount, 0),
    cover: projects.find((p) => p.cover)?.cover || null,
    projects,
  };
}

/** Wrap an async handler so a rejected promise reaches Express's error handler. */
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

/* ------------------------------------------------------------------ */
/* Public reads                                                        */
/* ------------------------------------------------------------------ */

router.get(
  '/',
  wrap(async (req, res) => {
    const locations = await Location.find({ published: true }).sort({ order: 1, name: 1 }).lean();
    res.set('Cache-Control', 'public, max-age=60');
    res.json({
      ok: true,
      count: locations.length,
      locations: locations.map(serializeLocation),
    });
  })
);

router.get(
  '/:locSlug',
  wrap(async (req, res) => {
    const loc = await Location.findOne({ slug: req.params.locSlug, published: true }).lean();
    if (!loc) return res.status(404).json({ ok: false, error: 'Location not found.' });
    res.json({ ok: true, location: serializeLocation(loc) });
  })
);

/* ------------------------------------------------------------------ */
/* Admin writes                                                        */
/* ------------------------------------------------------------------ */

router.post(
  '/locations',
  requireAdmin,
  wrap(async (req, res) => {
    const name = String(req.body.name || '').trim();
    if (!name) return res.status(400).json({ ok: false, error: 'A location name is required.' });

    const slug = slugify(name);
    if (await Location.exists({ slug })) {
      return res.status(409).json({ ok: false, error: 'That location already exists.' });
    }

    const loc = await Location.create({ slug, name, projects: [] });
    res.status(201).json({ ok: true, location: serializeLocation(loc.toObject()) });
  })
);

router.post(
  '/:locSlug/projects',
  requireAdmin,
  wrap(async (req, res) => {
    const name = String(req.body.name || '').trim();
    if (!name) return res.status(400).json({ ok: false, error: 'A project name is required.' });

    const loc = await Location.findOne({ slug: req.params.locSlug });
    if (!loc) return res.status(404).json({ ok: false, error: 'Location not found.' });

    const slug = slugify(name);
    if (loc.projects.some((p) => p.slug === slug)) {
      return res.status(409).json({ ok: false, error: 'That project already exists here.' });
    }

    loc.projects.push({
      slug,
      name,
      description: String(req.body.description || '').trim(),
      order: loc.projects.length,
      photos: [],
    });
    await loc.save();

    res.status(201).json({ ok: true, location: serializeLocation(loc.toObject()) });
  })
);

router.post(
  '/:locSlug/:projSlug/photos',
  requireAdmin,
  upload.array('photos', 20),
  wrap(async (req, res) => {
    if (!req.files || !req.files.length) {
      return res.status(400).json({ ok: false, error: 'No images were uploaded.' });
    }

    const loc = await Location.findOne({ slug: req.params.locSlug });
    if (!loc) return res.status(404).json({ ok: false, error: 'Location not found.' });

    const proj = loc.projects.find((p) => p.slug === req.params.projSlug);
    if (!proj) return res.status(404).json({ ok: false, error: 'Project not found.' });

    const folder = `${require('../config/env').env.cloudinary.folder}/${loc.slug}/${proj.slug}`;
    let order = proj.photos.length;

    // Uploaded one at a time rather than in parallel: a batch of 20 large
    // photos fired at once is what trips Cloudinary's rate limit on the
    // free tier, and a partial failure there is messy to unwind.
    for (const file of req.files) {
      const uploaded = await uploadBuffer(file.buffer, { folder });
      proj.photos.push({ ...uploaded, order: order++, caption: '' });
    }

    await loc.save();
    res.status(201).json({
      ok: true,
      added: req.files.length,
      location: serializeLocation(loc.toObject()),
    });
  })
);

router.delete(
  '/:locSlug/:projSlug/photos/:photoId',
  requireAdmin,
  wrap(async (req, res) => {
    const loc = await Location.findOne({ slug: req.params.locSlug });
    if (!loc) return res.status(404).json({ ok: false, error: 'Location not found.' });

    const proj = loc.projects.find((p) => p.slug === req.params.projSlug);
    if (!proj) return res.status(404).json({ ok: false, error: 'Project not found.' });

    const photo = proj.photos.id(req.params.photoId);
    if (!photo) return res.status(404).json({ ok: false, error: 'Photo not found.' });

    const { publicId } = photo;
    photo.deleteOne();
    await loc.save();

    // Cloudinary is cleaned up after MongoDB, not before: if this call
    // fails the database is already correct and the site looks right —
    // the leftover is an orphaned file, not a broken image.
    try {
      await deleteImage(publicId);
    } catch (err) {
      console.warn(`  Removed from DB but not from Cloudinary (${publicId}): ${err.message}`);
    }

    res.json({ ok: true, location: serializeLocation(loc.toObject()) });
  })
);

router.delete(
  '/:locSlug/:projSlug',
  requireAdmin,
  wrap(async (req, res) => {
    const loc = await Location.findOne({ slug: req.params.locSlug });
    if (!loc) return res.status(404).json({ ok: false, error: 'Location not found.' });

    const proj = loc.projects.find((p) => p.slug === req.params.projSlug);
    if (!proj) return res.status(404).json({ ok: false, error: 'Project not found.' });

    const publicIds = proj.photos.map((p) => p.publicId);
    proj.deleteOne();
    await loc.save();

    for (const id of publicIds) {
      try {
        await deleteImage(id);
      } catch (err) {
        console.warn(`  Could not delete ${id} from Cloudinary: ${err.message}`);
      }
    }

    res.json({ ok: true, removedPhotos: publicIds.length });
  })
);

router.delete(
  '/:locSlug',
  requireAdmin,
  wrap(async (req, res) => {
    const loc = await Location.findOne({ slug: req.params.locSlug });
    if (!loc) return res.status(404).json({ ok: false, error: 'Location not found.' });

    const publicIds = loc.projects.flatMap((p) => p.photos.map((ph) => ph.publicId));
    await loc.deleteOne();

    for (const id of publicIds) {
      try {
        await deleteImage(id);
      } catch (err) {
        console.warn(`  Could not delete ${id} from Cloudinary: ${err.message}`);
      }
    }

    res.json({ ok: true, removedPhotos: publicIds.length });
  })
);

module.exports = router;
// Exported for tests: both are pure functions, worth checking directly.
module.exports.slugify = slugify;
module.exports.serializeLocation = serializeLocation;
