/**
 * Ceylon Energy Services — gallery API.
 *
 * Serves the project gallery from MongoDB Atlas, with the images
 * themselves stored in (and delivered by) Cloudinary. Start with:
 *
 *   npm start
 */
const express = require('express');
const cors = require('cors');

const { env, assertRequiredEnv } = require('./config/env');
const { connectDB, disconnectDB } = require('./config/db');
const projectRoutes = require('./routes/projects');

assertRequiredEnv();

const app = express();

app.use(express.json({ limit: '1mb' }));
app.use(express.urlencoded({ extended: true }));

app.use(
  cors({
    origin(origin, callback) {
      // No Origin header means a same-origin request, curl, or a health
      // check — none of which CORS is meant to block.
      if (!origin) return callback(null, true);
      if (env.allowedOrigins.includes('*')) return callback(null, true);
      if (env.allowedOrigins.includes(origin)) return callback(null, true);
      callback(new Error(`Origin ${origin} is not allowed by ALLOWED_ORIGINS.`));
    },
  })
);

// Lets you confirm the server is up and reaching Atlas without needing
// any data in the database yet.
app.get('/api/health', (req, res) => {
  const mongoose = require('mongoose');
  const states = ['disconnected', 'connected', 'connecting', 'disconnecting'];
  res.json({
    ok: true,
    service: 'ceylon-energy-api',
    mongo: states[mongoose.connection.readyState] || 'unknown',
    cloudinary: env.cloudinary.cloudName ? 'configured' : 'missing',
    time: new Date().toISOString(),
  });
});

app.use('/api/projects', projectRoutes);

app.use((req, res) => {
  res.status(404).json({ ok: false, error: `No route for ${req.method} ${req.originalUrl}` });
});

// Central error handler. Multer's size/type errors are the ones users
// actually hit, so they get a readable message instead of a 500.
app.use((err, req, res, next) => {
  if (err.code === 'LIMIT_FILE_SIZE') {
    return res.status(413).json({ ok: false, error: 'That image is larger than the 10MB limit.' });
  }
  if (err.code === 'LIMIT_FILE_COUNT') {
    return res.status(413).json({ ok: false, error: 'Too many images in one upload (20 max).' });
  }
  console.error('  Unhandled error:', err);
  res.status(err.status || 500).json({
    ok: false,
    error: env.nodeEnv === 'production' ? 'Something went wrong.' : err.message,
  });
});

/**
 * Confirm that requests to this port actually reach US.
 *
 * Binding a port and owning it are not the same thing. macOS's AirPlay
 * Receiver holds port 5000 in a way that still lets Node bind it, so the
 * server prints "listening" and looks perfectly healthy — while AirPlay
 * answers the real connections with "403 Forbidden". Every admin upload
 * then fails for a reason that appears nowhere in this log, because the
 * request never arrived.
 *
 * One request to ourselves at startup turns that into a clear message.
 */
async function verifyPortReachesUs(port) {
  // "localhost" resolves to either stack depending on the machine, and a
  // squatter may hold only one of them, so check both. Nothing answering
  // on an address is fine (it just means we are not bound there) — only
  // a reply from someone who is not us is worth reporting.
  for (const [label, host] of [['IPv4', '127.0.0.1'], ['IPv6', '[::1]']]) {
    let res;
    try {
      res = await fetch(`http://${host}:${port}/api/health`, {
        signal: AbortSignal.timeout(3000),
      });
    } catch {
      continue;
    }

    const body = await res.json().catch(() => null);
    if (res.ok && body && body.service === 'ceylon-energy-api') continue; // that is us

    const who = res.headers.get('server') || '';
    console.error(`\n  Warning: port ${port} does not reach this server over ${label}.`);
    console.error(
      `   http://${host}:${port}/api/health answered HTTP ${res.status}` +
        (who ? ` from "${who}".` : ' from another program.')
    );
    if (/airtunes|airplay/i.test(who)) {
      console.error("   That is macOS's AirPlay Receiver. It shares the port and rejects");
      console.error('   everything with 403, so the admin panel never reaches this server.');
    } else if (port === 5000) {
      console.error('   On macOS this is usually AirPlay Receiver, which holds port 5000');
      console.error('   and rejects everything with 403.');
    }
    console.error('   Fix: move the API to a free port. In .env set');
    console.error('        PORT=5050  and  GALLERY_API_BASE=http://localhost:5050');
    console.error('   then restart this server and reload the admin page.\n');
  }
}

async function start() {
  await connectDB();
  const server = app.listen(env.port, async () => {
    console.log(`  API listening on http://localhost:${env.port}`);
    console.log(`   Gallery:  GET http://localhost:${env.port}/api/projects`);
    console.log(`   Health:   GET http://localhost:${env.port}/api/health`);
    await verifyPortReachesUs(env.port);
  });

  // A port already taken is reported by Node as a bare "EADDRINUSE",
  // which does not say which port or what to do about it.
  server.on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
      console.error(`\n  Port ${env.port} is already in use by another program.`);
      console.error(`   See what is holding it:  lsof -i :${env.port}`);
      console.error('   Or pick a free one: set PORT and GALLERY_API_BASE in .env.\n');
      process.exit(1);
    }
    throw err;
  });

  // Close sockets cleanly so Atlas does not keep stale connections open
  // when the host restarts the process on deploy.
  const shutdown = async (signal) => {
    console.log(`\n  ${signal} received — shutting down.`);
    server.close(async () => {
      await disconnectDB();
      process.exit(0);
    });
  };
  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT', () => shutdown('SIGINT'));
}

if (require.main === module) {
  start().catch((err) => {
    console.error('  Failed to start:', err.message);
    process.exit(1);
  });
}

module.exports = { app, start };
// Exported for tests: checkable on its own without booting the whole server.
module.exports.verifyPortReachesUs = verifyPortReachesUs;
