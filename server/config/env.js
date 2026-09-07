/**
 * Loads .env and checks that the settings we cannot run without are
 * actually present.
 *
 * Why check up front: a missing MONGODB_URI otherwise shows up much
 * later as a confusing driver error in the middle of a request. Failing
 * here instead means you get one clear message naming the variable you
 * forgot, before the server ever accepts traffic.
 */
require('dotenv').config();

/** Read a variable, or fall back to a default. Blank counts as missing. */
function read(name, fallback = undefined) {
  const value = process.env[name];
  if (value === undefined || value.trim() === '') return fallback;
  return value.trim();
}

const env = {
  nodeEnv: read('NODE_ENV', 'development'),
  port: parseInt(read('PORT', '5000'), 10),

  // MongoDB Atlas
  mongoUri: read('MONGODB_URI'),
  dbName: read('MONGODB_DB_NAME', 'ceylonenergy'),

  // Cloudinary
  cloudinary: {
    cloudName: read('CLOUDINARY_CLOUD_NAME'),
    apiKey: read('CLOUDINARY_API_KEY'),
    apiSecret: read('CLOUDINARY_API_SECRET'),
    folder: read('CLOUDINARY_FOLDER', 'ceylon-energy/completed-projects'),
  },

  // Shared secret the admin panel sends on write requests.
  adminApiToken: read('ADMIN_API_TOKEN'),

  // Browsers block cross-origin fetches unless the API says the site is
  // allowed. Comma-separated list; "*" allows everything (fine for local
  // development, too loose for production).
  allowedOrigins: read('ALLOWED_ORIGINS', '*')
    .split(',')
    .map((o) => o.trim())
    .filter(Boolean),
};

/**
 * Stop the process if anything required is missing, listing every
 * missing variable at once rather than one per restart.
 */
function assertRequiredEnv() {
  const missing = [];
  if (!env.mongoUri) missing.push('MONGODB_URI');
  if (!env.cloudinary.cloudName) missing.push('CLOUDINARY_CLOUD_NAME');
  if (!env.cloudinary.apiKey) missing.push('CLOUDINARY_API_KEY');
  if (!env.cloudinary.apiSecret) missing.push('CLOUDINARY_API_SECRET');
  if (!env.adminApiToken) missing.push('ADMIN_API_TOKEN');

  if (missing.length) {
    console.error('\n  Missing required environment variables:\n');
    missing.forEach((name) => console.error(`   - ${name}`));
    console.error('\n  Copy .env.example to .env and fill in the values.\n');
    process.exit(1);
  }
}

module.exports = { env, assertRequiredEnv };
