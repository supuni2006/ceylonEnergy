#!/usr/bin/env node
/**
 * Writes the gallery from MongoDB out to assets/data/projects.json.
 *
 *   npm run export
 *
 * The site reads this file if the API is unreachable, so the gallery
 * keeps working during a backend restart or a hosting outage. The URLs
 * inside point at Cloudinary, so no image files are needed in the repo
 * either way.
 *
 * Re-run it after any change made through the admin panel.
 */
const fs = require('fs');
const path = require('path');

const { assertRequiredEnv } = require('../config/env');
const { connectDB, disconnectDB } = require('../config/db');
const { buildVariants } = require('../config/cloudinary');
const Location = require('../models/Location');

const OUTPUT = path.resolve(__dirname, '../../assets/data/projects.json');

async function main() {
  assertRequiredEnv();
  await connectDB();

  const locations = await Location.find({ published: true }).sort({ order: 1, name: 1 }).lean();

  const payload = locations.map((loc) => ({
    id: loc.slug,
    name: loc.name,
    projects: [...loc.projects]
      .sort((a, b) => a.order - b.order)
      .map((proj) => ({
        id: proj.slug,
        name: proj.name,
        photos: [...proj.photos]
          .sort((a, b) => a.order - b.order)
          .map((photo) => ({
            id: String(photo._id),
            caption: photo.caption || '',
            ...buildVariants(photo.publicId),
          })),
      })),
  }));

  // Written to a temporary file and renamed into place, so a reader
  // never catches the file half-written.
  fs.mkdirSync(path.dirname(OUTPUT), { recursive: true });
  const tmp = `${OUTPUT}.tmp`;
  fs.writeFileSync(tmp, JSON.stringify(payload, null, 2));
  fs.renameSync(tmp, OUTPUT);

  const photoTotal = payload.reduce(
    (sum, l) => sum + l.projects.reduce((s, p) => s + p.photos.length, 0),
    0
  );
  console.log(`  Wrote ${OUTPUT}`);
  console.log(`   ${payload.length} locations, ${photoTotal} photos\n`);

  await disconnectDB();
}

main().catch(async (err) => {
  console.error('  Export failed:', err.message);
  await disconnectDB();
  process.exit(1);
});
