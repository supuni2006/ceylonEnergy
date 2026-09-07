#!/usr/bin/env node
/**
 * Checks that MongoDB Atlas and Cloudinary both accept your credentials.
 *
 *   npm run check-db
 *
 * Run this first when something is not working — it tells you which of
 * the two services is unhappy, instead of leaving you guessing.
 */
const { env, assertRequiredEnv } = require('../config/env');
const { connectDB, disconnectDB } = require('../config/db');
const { cloudinary } = require('../config/cloudinary');
const Location = require('../models/Location');

async function main() {
  assertRequiredEnv();
  console.log('\n  Checking your setup...\n');

  // --- MongoDB ---
  try {
    await connectDB();
    const count = await Location.countDocuments();
    console.log(`  MongoDB Atlas   OK — ${count} location(s) in "${env.dbName}"`);
  } catch (err) {
    console.log(`  MongoDB Atlas   FAILED — ${err.message}`);
    console.log('     Check: password correct? IP allowlisted in Atlas -> Network Access?');
  }

  // --- Cloudinary ---
  try {
    await cloudinary.api.ping();
    console.log(`  Cloudinary      OK — cloud "${env.cloudinary.cloudName}"`);
  } catch (err) {
    console.log(`  Cloudinary      FAILED — ${err.message}`);
    console.log('     Check: cloud name, API key and API secret in .env');
  }

  console.log('');
  await disconnectDB();
}

main().catch(async (err) => {
  console.error('  Check failed:', err.message);
  await disconnectDB();
  process.exit(1);
});
