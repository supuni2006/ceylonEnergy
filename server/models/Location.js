/**
 * Gallery data model — the same shape the site already uses:
 *
 *   Location ("Rathnapura")
 *     └── Project ("Belihuloya project 01")
 *           └── Photo (one image stored in Cloudinary)
 *
 * Projects and photos are embedded rather than kept in their own
 * collections. The gallery is always read as a whole tree ("give me
 * every location with its projects and photos"), so embedding means one
 * query instead of three, and the whole document stays far below
 * MongoDB's 16MB limit because only Cloudinary ids are stored here —
 * never image bytes.
 */
const mongoose = require('mongoose');

const photoSchema = new mongoose.Schema(
  {
    // Cloudinary's identifier, e.g. "ceylon-energy/completed-projects/abc123".
    // This is what we use to build URLs and to delete the image later.
    publicId: { type: String, required: true },

    // The plain secure_url Cloudinary returned at upload time. Kept as a
    // fallback so the site still works if URL-building ever changes.
    url: { type: String, required: true },

    width: Number,
    height: Number,
    format: String,
    bytes: Number,

    // Shown as the image's alt text on the site.
    caption: { type: String, default: '' },

    // The original repo-relative path this photo was migrated from
    // (e.g. "assets/images/completed-projects/loc-colombo/.../photo-001.png").
    // Only set by the migration script, and only so that re-running it
    // can tell "already uploaded" from "new file" instead of creating
    // duplicates.
    sourcePath: { type: String, default: null },

    // Lower numbers show first. Lets the admin reorder photos without
    // re-uploading them.
    order: { type: Number, default: 0 },

    uploadedAt: { type: Date, default: Date.now },
  },
  { _id: true }
);

const projectSchema = new mongoose.Schema(
  {
    // Human-readable id kept from the old JSON files (e.g.
    // "proj-belihuloya-project-01") so existing links keep working.
    slug: { type: String, required: true },
    name: { type: String, required: true, trim: true, maxlength: 120 },
    description: { type: String, default: '', maxlength: 2000 },
    photos: { type: [photoSchema], default: [] },
    order: { type: Number, default: 0 },
    createdAt: { type: Date, default: Date.now },
  },
  { _id: true }
);

const locationSchema = new mongoose.Schema(
  {
    slug: { type: String, required: true, unique: true, index: true },
    name: { type: String, required: true, trim: true, maxlength: 80 },
    projects: { type: [projectSchema], default: [] },
    order: { type: Number, default: 0 },

    // Lets the admin hide a location from the live site without
    // deleting it and losing the photos.
    published: { type: Boolean, default: true },
  },
  { timestamps: true }
);

/** Total photos across every project — used for the "N photos" card label. */
locationSchema.virtual('photoCount').get(function () {
  return this.projects.reduce((sum, p) => sum + p.photos.length, 0);
});

module.exports = mongoose.model('Location', locationSchema);
