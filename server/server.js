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

async function start() {
  await connectDB();
  const server = app.listen(env.port, () => {
    console.log(`  API listening on http://localhost:${env.port}`);
    console.log(`   Gallery:  GET http://localhost:${env.port}/api/projects`);
    console.log(`   Health:   GET http://localhost:${env.port}/api/health`);
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
