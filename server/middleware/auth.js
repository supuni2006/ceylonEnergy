/**
 * Guards the write endpoints (upload, delete, edit).
 *
 * Reads are public — the whole point is that anyone visiting the site
 * can load the gallery. Writes require the shared token from
 * ADMIN_API_TOKEN, sent as:
 *
 *   Authorization: Bearer <token>      (or)      x-admin-token: <token>
 */
const crypto = require('crypto');
const { env } = require('../config/env');

/**
 * Compare two strings without leaking, through response timing, how
 * many leading characters matched. `timingSafeEqual` needs equal-length
 * buffers, so both sides are hashed to a fixed 32 bytes first.
 */
function safeEqual(a, b) {
  const ha = crypto.createHash('sha256').update(String(a)).digest();
  const hb = crypto.createHash('sha256').update(String(b)).digest();
  return crypto.timingSafeEqual(ha, hb);
}

function requireAdmin(req, res, next) {
  const header = req.get('authorization') || '';
  const bearer = header.startsWith('Bearer ') ? header.slice(7) : null;
  const token = bearer || req.get('x-admin-token');

  if (!token || !safeEqual(token, env.adminApiToken)) {
    return res.status(401).json({
      ok: false,
      error: 'Unauthorized — send a valid admin token.',
    });
  }
  next();
}

module.exports = { requireAdmin };
