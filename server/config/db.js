/**
 * MongoDB Atlas connection.
 *
 * Opened once and reused for the whole process — Mongoose keeps an
 * internal connection pool, so calling connectDB() again is a no-op
 * rather than a second set of sockets to Atlas.
 */
const mongoose = require('mongoose');
const { env } = require('./env');

let connectionPromise = null;

async function connectDB() {
  if (connectionPromise) return connectionPromise;

  mongoose.set('strictQuery', true);

  connectionPromise = mongoose
    .connect(env.mongoUri, {
      dbName: env.dbName,
      // Fail fast with a clear error instead of hanging for 30s when
      // Atlas is unreachable — almost always a missing IP allowlist
      // entry or a wrong password.
      serverSelectionTimeoutMS: 10000,
    })
    .then((conn) => {
      console.log(`  MongoDB connected — database "${conn.connection.name}"`);
      return conn;
    })
    .catch((err) => {
      // Reset so a later retry can attempt a fresh connection.
      connectionPromise = null;
      console.error('  MongoDB connection failed:', err.message);
      if (/IP|whitelist|allowlist/i.test(err.message)) {
        console.error(
          '   Hint: add your server IP under Atlas -> Network Access.'
        );
      }
      throw err;
    });

  return connectionPromise;
}

async function disconnectDB() {
  if (!connectionPromise) return;
  await mongoose.disconnect();
  connectionPromise = null;
}

module.exports = { connectDB, disconnectDB };
