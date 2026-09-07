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

  // The placeholder from .env.example is committed to the repository, so
  // leaving it in place means the admin token is public knowledge — a
  // stranger could delete the whole gallery. Refuse to start rather than
  // run in that state.
  if (env.adminApiToken === 'replace_with_a_long_random_string') {
    console.error('\n  ADMIN_API_TOKEN is still the example placeholder.\n');
    console.error('   That value is published in .env.example, so anyone who can');
    console.error('   read this repository could change or delete your gallery.\n');
    console.error('   Generate a real one and put it in .env:\n');
    console.error('     node -e "console.log(require(\'crypto\').randomBytes(32).toString(\'hex\'))"\n');
    process.exit(1);
  }

  // Not fatal — a short token is weak but still the owner's choice.
  if (env.adminApiToken.length < 24) {
    console.warn('\n  Warning: ADMIN_API_TOKEN is short. 32+ random characters is safer.\n');
  }
}

module.exports = { env, assertRequiredEnv };
