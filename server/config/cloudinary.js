/**
 * Cloudinary setup plus the URL helpers the site uses.
 *
 * Images are stored once at full size. Every size the page needs is
 * produced by Cloudinary on the fly from the same stored file, so
 * uploading a 4MB photo does not mean shipping 4MB to a phone.
 */
const { v2: cloudinary } = require('cloudinary');
const { env } = require('./env');

cloudinary.config({
  cloud_name: env.cloudinary.cloudName,
  api_key: env.cloudinary.apiKey,
  api_secret: env.cloudinary.apiSecret,
  secure: true, // always return https:// URLs
});

/**
 * Build a delivery URL for a stored image.
 *
 * f_auto  -> serves WebP/AVIF to browsers that support it, JPG to those that don't
 * q_auto  -> Cloudinary picks the lowest quality that still looks clean
 * c_limit -> never upscales; only shrinks images larger than `width`
 */
function buildUrl(publicId, { width, height, crop = 'limit' } = {}) {
  if (!publicId) return null;
  return cloudinary.url(publicId, {
    secure: true,
    fetch_format: 'auto',
    quality: 'auto',
    crop,
    ...(width ? { width } : {}),
    ...(height ? { height } : {}),
  });
}

/**
 * The three sizes the gallery uses, generated from one stored original:
 * a small square-ish card thumbnail, a mid-size grid image, and a large
 * one for the lightbox.
 */
function buildVariants(publicId) {
  return {
    thumb: buildUrl(publicId, { width: 400, height: 300, crop: 'fill' }),
    medium: buildUrl(publicId, { width: 900 }),
    large: buildUrl(publicId, { width: 1600 }),
  };
}

/** Upload a file from disk. Returns the fields we store in MongoDB. */
async function uploadImage(filePath, { folder, publicId } = {}) {
  const result = await cloudinary.uploader.upload(filePath, {
    folder: folder || env.cloudinary.folder,
    ...(publicId ? { public_id: publicId } : {}),
    resource_type: 'image',
    overwrite: false,
    // Cloudinary appends a random suffix if the id is taken, so a
    // re-run cannot silently overwrite an existing photo.
    unique_filename: true,
  });

  return {
    publicId: result.public_id,
    url: result.secure_url,
    width: result.width,
    height: result.height,
    format: result.format,
    bytes: result.bytes,
  };
}

/** Upload from an in-memory buffer (what multer gives us on an HTTP upload). */
function uploadBuffer(buffer, { folder } = {}) {
  return new Promise((resolve, reject) => {
    const stream = cloudinary.uploader.upload_stream(
      {
        folder: folder || env.cloudinary.folder,
        resource_type: 'image',
      },
      (error, result) => {
        if (error) return reject(error);
        resolve({
          publicId: result.public_id,
          url: result.secure_url,
          width: result.width,
          height: result.height,
          format: result.format,
          bytes: result.bytes,
        });
      }
    );
    stream.end(buffer);
  });
}

/** Permanently remove an image from Cloudinary. */
async function deleteImage(publicId) {
  if (!publicId) return null;
  return cloudinary.uploader.destroy(publicId, { resource_type: 'image' });
}

module.exports = {
  cloudinary,
  buildUrl,
  buildVariants,
  uploadImage,
  uploadBuffer,
  deleteImage,
};
