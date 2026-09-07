#!/usr/bin/env node
/**
 * One-time migration: local image files -> Cloudinary -> MongoDB Atlas.
 *
 * Reads assets/docs/gallery.json (the catalogue whose paths actually
 * match what is on disk), uploads each photo to Cloudinary, and saves
 * the resulting ids into MongoDB.
 *
 *   npm run migrate            upload for real
 *   npm run migrate -- --dry   list what would happen, upload nothing
 *
 * Safe to re-run: a photo whose file has already been uploaded is
 * skipped rather than duplicated, so a run interrupted halfway can just
 * be started again.
 */
const fs = require('fs');
const path = require('path');

const { env, assertRequiredEnv } = require('../config/env');
const { connectDB, disconnectDB } = require('../config/db');
const { uploadImage } = require('../config/cloudinary');
const Location = require('../models/Location');

const SITE_ROOT = path.resolve(__dirname, '../..');
const CATALOGUE = path.join(SITE_ROOT, 'assets/docs/gallery.json');
const DRY_RUN = process.argv.includes('--dry');

function slugify(str) {
  return (
    String(str)
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'item'
  );
}

async function main() {
  // A dry run only reads local files, so it deliberately skips the
  // credential check and the database — you can preview the plan before
  // you have filled in .env at all.
  if (!DRY_RUN) assertRequiredEnv();

  if (!fs.existsSync(CATALOGUE)) {
    console.error(`  Catalogue not found: ${CATALOGUE}`);
    process.exit(1);
  }

  const catalogue = JSON.parse(fs.readFileSync(CATALOGUE, 'utf8'));
  console.log(`\n  Ceylon Energy — image migration${DRY_RUN ? '  (DRY RUN)' : ''}`);
  console.log(`   Catalogue: ${catalogue.length} locations\n`);

  if (!DRY_RUN) await connectDB();

  const stats = { uploaded: 0, skipped: 0, missing: 0, locations: 0, projects: 0 };

  for (const [locIndex, rawLoc] of catalogue.entries()) {
    const locSlug = rawLoc.id || slugify(rawLoc.name);

    let loc = DRY_RUN ? null : await Location.findOne({ slug: locSlug });
    if (!loc) {
      loc = new Location({
        slug: locSlug,
        name: rawLoc.name,
        order: locIndex,
        projects: [],
      });
      stats.locations++;
    }
    console.log(`  ${rawLoc.name}  (${locSlug})`);

    for (const [projIndex, rawProj] of (rawLoc.projects || []).entries()) {
      const projSlug = rawProj.id || slugify(rawProj.name);

      let proj = loc.projects.find((p) => p.slug === projSlug);
      if (!proj) {
        loc.projects.push({
          slug: projSlug,
          name: rawProj.name,
          order: projIndex,
          photos: [],
        });
        proj = loc.projects[loc.projects.length - 1];
        stats.projects++;
      }
      console.log(`   └─ ${rawProj.name}  (${(rawProj.photos || []).length} photos)`);

      for (const [photoIndex, relPath] of (rawProj.photos || []).entries()) {
        const absPath = path.join(SITE_ROOT, relPath);
        const fileName = path.basename(relPath);

        if (!fs.existsSync(absPath)) {
          console.log(`       ${fileName} — file missing on disk, skipped`);
          stats.missing++;
          continue;
        }

        // Re-running should not create duplicates. The original relative
        // path is stored on each photo as sourcePath, so a second run can
        // recognise a file it has already handled.
        if (proj.photos.some((p) => p.sourcePath === relPath)) {
          console.log(`       ${fileName} — already uploaded, skipped`);
          stats.skipped++;
          continue;
        }

        if (DRY_RUN) {
          console.log(`       ${fileName} — would upload`);
          stats.uploaded++;
          continue;
        }

        try {
          const folder = `${env.cloudinary.folder}/${locSlug}/${projSlug}`;
          const uploaded = await uploadImage(absPath, { folder });
          proj.photos.push({
            ...uploaded,
            sourcePath: relPath,
            order: photoIndex,
            caption: `${rawLoc.name} — ${rawProj.name}`,
          });
          const kb = Math.round(uploaded.bytes / 1024);
          console.log(`       ${fileName} — uploaded (${kb}KB, ${uploaded.width}x${uploaded.height})`);
          stats.uploaded++;
        } catch (err) {
          console.error(`       ${fileName} — FAILED: ${err.message}`);
        }
      }
    }

    if (!DRY_RUN) await loc.save();
  }

  console.log('\n  Summary');
  console.log(`   Locations created: ${stats.locations}`);
  console.log(`   Projects created:  ${stats.projects}`);
  console.log(`   Photos uploaded:   ${stats.uploaded}`);
  console.log(`   Already there:     ${stats.skipped}`);
  console.log(`   Missing on disk:   ${stats.missing}`);

  if (DRY_RUN) {
    console.log('\n   Dry run — nothing was uploaded or saved.');
    console.log('   Run "npm run migrate" (without --dry) to do it for real.\n');
  } else {
    console.log('\n   Done. Next: run "npm run export" to refresh the static fallback file.\n');
  }

  if (!DRY_RUN) await disconnectDB();
}

main().catch(async (err) => {
  console.error('\n  Migration failed:', err.message);
  if (!DRY_RUN) await disconnectDB();
  process.exit(1);
});
