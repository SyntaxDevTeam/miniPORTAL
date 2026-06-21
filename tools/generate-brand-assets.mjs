import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { deflateSync, inflateSync } from 'node:zlib';

const sourcePath = new URL('../theme/ico/SyntaxDevTeam_logo.png', import.meta.url);
const transparentSourcePath = new URL('../theme/ico/SyntaxDevTeam_logo.no_bg.png', import.meta.url);
const outputRoots = ['default', 'glassnight'].map(
  (theme) => new URL(`../templates/${theme}/assets/img/brand/`, import.meta.url),
);

const source = decodePng(readFileSync(sourcePath));
const bounds = alphaBounds(source);
const transparentSource = decodePng(readFileSync(transparentSourcePath));
const transparentMarkBounds = alphaBounds(transparentSource, {
  left: 350,
  top: 0,
  right: 930,
  bottom: 415,
});
const variants = new Map([
  ['favicon-16x16.png', renderSquare(transparentSource, transparentMarkBounds, 16, 0.9, null)],
  ['favicon-32x32.png', renderSquare(transparentSource, transparentMarkBounds, 32, 0.9, null)],
  ['favicon-48x48.png', renderSquare(transparentSource, transparentMarkBounds, 48, 0.9, null)],
  ['favicon-64x64.png', renderSquare(transparentSource, transparentMarkBounds, 64, 0.9, null)],
  ['favicon-96x96.png', renderSquare(transparentSource, transparentMarkBounds, 96, 0.9, null)],
  ['favicon-128x128.png', renderSquare(transparentSource, transparentMarkBounds, 128, 0.9, null)],
  ['favicon-256x256.png', renderSquare(transparentSource, transparentMarkBounds, 256, 0.9, null)],
  ['apple-touch-icon.png', renderSquare(transparentSource, transparentMarkBounds, 180, 0.82, [8, 12, 18, 255])],
  ['icon-192.png', renderSquare(transparentSource, transparentMarkBounds, 192, 0.82, [8, 12, 18, 255])],
  ['icon-512.png', renderSquare(transparentSource, transparentMarkBounds, 512, 0.82, [8, 12, 18, 255])],
  ['icon-maskable-512.png', renderSquare(transparentSource, transparentMarkBounds, 512, 0.66, [8, 12, 18, 255])],
  ['admin-logo.png', renderSquare(transparentSource, transparentMarkBounds, 256, 0.94, null)],
  ['syntaxdevteam-logo.png', renderSquare(source, bounds, 512, 0.9, null, 1)],
]);

const favicon = encodeIco([
  [16, variants.get('favicon-16x16.png')],
  [32, variants.get('favicon-32x32.png')],
  [48, variants.get('favicon-48x48.png')],
  [64, variants.get('favicon-64x64.png')],
  [128, variants.get('favicon-128x128.png')],
  [256, variants.get('favicon-256x256.png')],
]);

for (const outputRoot of outputRoots) {
  mkdirSync(outputRoot, { recursive: true });
  for (const [name, data] of variants) {
    writeFileSync(new URL(name, outputRoot), data);
  }
  writeFileSync(new URL('favicon.ico', outputRoot), favicon);
}

function decodePng(data) {
  const signature = data.subarray(0, 8).toString('hex');
  if (signature !== '89504e470d0a1a0a') throw new Error('Nieprawidlowy plik PNG.');

  let offset = 8;
  let width = 0;
  let height = 0;
  const idat = [];
  while (offset < data.length) {
    const length = data.readUInt32BE(offset);
    const type = data.subarray(offset + 4, offset + 8).toString('ascii');
    const chunk = data.subarray(offset + 8, offset + 8 + length);
    offset += length + 12;
    if (type === 'IHDR') {
      width = chunk.readUInt32BE(0);
      height = chunk.readUInt32BE(4);
      if (chunk[8] !== 8 || chunk[9] !== 6 || ![0, 1].includes(chunk[12])) {
        throw new Error('Generator obsluguje PNG RGBA 8-bit w standardowym wariancie PNG.');
      }
      var interlace = chunk[12];
    } else if (type === 'IDAT') {
      idat.push(chunk);
    } else if (type === 'IEND') {
      break;
    }
  }

  const packed = inflateSync(Buffer.concat(idat));
  const pixels = Buffer.alloc(width * height * 4);
  let inputOffset = 0;
  const passes = interlace === 0
    ? [[0, 0, 1, 1]]
    : [[0, 0, 8, 8], [4, 0, 8, 8], [0, 4, 4, 8], [2, 0, 4, 4], [0, 2, 2, 4], [1, 0, 2, 2], [0, 1, 1, 2]];
  for (const [startX, startY, stepX, stepY] of passes) {
    const passWidth = startX >= width ? 0 : Math.ceil((width - startX) / stepX);
    const passHeight = startY >= height ? 0 : Math.ceil((height - startY) / stepY);
    if (passWidth === 0 || passHeight === 0) continue;
    const stride = passWidth * 4;
    const previous = Buffer.alloc(stride);
    for (let passY = 0; passY < passHeight; passY += 1) {
      const filter = packed[inputOffset++];
      const row = Buffer.alloc(stride);
      for (let byte = 0; byte < stride; byte += 1) {
        const raw = packed[inputOffset++];
        const left = byte >= 4 ? row[byte - 4] : 0;
        const up = previous[byte];
        const upLeft = byte >= 4 ? previous[byte - 4] : 0;
        const value = filter === 0 ? raw
          : filter === 1 ? raw + left
            : filter === 2 ? raw + up
              : filter === 3 ? raw + Math.floor((left + up) / 2)
                : filter === 4 ? raw + paeth(left, up, upLeft)
                  : Number.NaN;
        if (Number.isNaN(value)) throw new Error(`Nieobslugiwany filtr PNG: ${filter}.`);
        row[byte] = value & 0xff;
      }
      const targetY = startY + passY * stepY;
      for (let passX = 0; passX < passWidth; passX += 1) {
        const targetX = startX + passX * stepX;
        row.copy(pixels, (targetY * width + targetX) * 4, passX * 4, passX * 4 + 4);
      }
      row.copy(previous);
    }
  }

  return { width, height, pixels };
}

function alphaBounds(image, region = {}) {
  const scanLeft = Math.max(0, region.left ?? 0);
  const scanTop = Math.max(0, region.top ?? 0);
  const scanRight = Math.min(image.width - 1, region.right ?? image.width - 1);
  const scanBottom = Math.min(image.height - 1, region.bottom ?? image.height - 1);
  let left = scanRight + 1;
  let top = scanBottom + 1;
  let right = -1;
  let bottom = -1;
  for (let y = scanTop; y <= scanBottom; y += 1) {
    for (let x = scanLeft; x <= scanRight; x += 1) {
      if (image.pixels[(y * image.width + x) * 4 + 3] === 0) continue;
      left = Math.min(left, x);
      top = Math.min(top, y);
      right = Math.max(right, x);
      bottom = Math.max(bottom, y);
    }
  }
  if (right < left || bottom < top) throw new Error('Logo jest calkowicie przezroczyste.');
  return { left, top, width: right - left + 1, height: bottom - top + 1 };
}

function renderSquare(image, bounds, size, fillRatio, background, forcedSamples = null) {
  const pixels = Buffer.alloc(size * size * 4);
  if (background) {
    for (let i = 0; i < size * size; i += 1) pixels.set(background, i * 4);
  }

  const scale = Math.min(size * fillRatio / bounds.width, size * fillRatio / bounds.height);
  const targetWidth = bounds.width * scale;
  const targetHeight = bounds.height * scale;
  const offsetX = (size - targetWidth) / 2;
  const offsetY = (size - targetHeight) / 2;
  const samples = forcedSamples ?? (scale < 0.25 ? 4 : scale < 0.75 ? 2 : 1);
  for (let y = Math.max(0, Math.floor(offsetY)); y < Math.min(size, Math.ceil(offsetY + targetHeight)); y += 1) {
    for (let x = Math.max(0, Math.floor(offsetX)); x < Math.min(size, Math.ceil(offsetX + targetWidth)); x += 1) {
      const color = forcedSamples === 1
        ? sampleBilinear(
          image,
          bounds.left + (x + 0.5 - offsetX) / scale - 0.5,
          bounds.top + (y + 0.5 - offsetY) / scale - 0.5,
        )
        : samplePixel(image, bounds, x, y, offsetX, offsetY, scale, samples);
      const destination = (y * size + x) * 4;
      if (!background) {
        pixels.set(color, destination);
        continue;
      }
      const alpha = color[3] / 255;
      for (let channel = 0; channel < 3; channel += 1) {
        pixels[destination + channel] = Math.round(color[channel] * alpha + background[channel] * (1 - alpha));
      }
      pixels[destination + 3] = 255;
    }
  }
  return encodePng(size, size, pixels);
}

function samplePixel(image, bounds, x, y, offsetX, offsetY, scale, samples) {
  let alphaSum = 0;
  const premultiplied = [0, 0, 0];
  for (let sampleY = 0; sampleY < samples; sampleY += 1) {
    for (let sampleX = 0; sampleX < samples; sampleX += 1) {
      const sourceX = bounds.left + (x + (sampleX + 0.5) / samples - offsetX) / scale - 0.5;
      const sourceY = bounds.top + (y + (sampleY + 0.5) / samples - offsetY) / scale - 0.5;
      const color = sampleBilinear(image, sourceX, sourceY);
      const alpha = color[3] / 255;
      alphaSum += alpha;
      for (let channel = 0; channel < 3; channel += 1) premultiplied[channel] += color[channel] * alpha;
    }
  }

  const sampleCount = samples * samples;
  const output = Buffer.alloc(4);
  if (alphaSum > 0) {
    for (let channel = 0; channel < 3; channel += 1) output[channel] = Math.round(premultiplied[channel] / alphaSum);
  }
  output[3] = Math.round(alphaSum / sampleCount * 255);
  return output;
}

function sampleBilinear(image, x, y) {
  const x0 = Math.max(0, Math.min(image.width - 1, Math.floor(x)));
  const y0 = Math.max(0, Math.min(image.height - 1, Math.floor(y)));
  const x1 = Math.min(image.width - 1, x0 + 1);
  const y1 = Math.min(image.height - 1, y0 + 1);
  const fx = Math.max(0, Math.min(1, x - x0));
  const fy = Math.max(0, Math.min(1, y - y0));
  const output = Buffer.alloc(4);
  for (let channel = 0; channel < 4; channel += 1) {
    const top = image.pixels[(y0 * image.width + x0) * 4 + channel] * (1 - fx)
      + image.pixels[(y0 * image.width + x1) * 4 + channel] * fx;
    const bottom = image.pixels[(y1 * image.width + x0) * 4 + channel] * (1 - fx)
      + image.pixels[(y1 * image.width + x1) * 4 + channel] * fx;
    output[channel] = Math.round(top * (1 - fy) + bottom * fy);
  }
  return output;
}

function encodePng(width, height, pixels) {
  const rows = Buffer.alloc((width * 4 + 1) * height);
  for (let y = 0; y < height; y += 1) {
    pixels.copy(rows, y * (width * 4 + 1) + 1, y * width * 4, (y + 1) * width * 4);
  }
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header.set([8, 6, 0, 0, 0], 8);
  return Buffer.concat([
    Buffer.from('89504e470d0a1a0a', 'hex'),
    pngChunk('IHDR', header),
    pngChunk('IDAT', deflateSync(rows, { level: 9 })),
    pngChunk('IEND', Buffer.alloc(0)),
  ]);
}

function pngChunk(type, data) {
  const name = Buffer.from(type, 'ascii');
  const chunk = Buffer.alloc(data.length + 12);
  chunk.writeUInt32BE(data.length, 0);
  name.copy(chunk, 4);
  data.copy(chunk, 8);
  chunk.writeUInt32BE(crc32(Buffer.concat([name, data])), data.length + 8);
  return chunk;
}

function encodeIco(images) {
  const header = Buffer.alloc(6 + images.length * 16);
  header.writeUInt16LE(0, 0);
  header.writeUInt16LE(1, 2);
  header.writeUInt16LE(images.length, 4);
  let offset = header.length;
  images.forEach(([size, data], index) => {
    const entry = 6 + index * 16;
    header[entry] = size === 256 ? 0 : size;
    header[entry + 1] = size === 256 ? 0 : size;
    header.writeUInt16LE(1, entry + 4);
    header.writeUInt16LE(32, entry + 6);
    header.writeUInt32LE(data.length, entry + 8);
    header.writeUInt32LE(offset, entry + 12);
    offset += data.length;
  });
  return Buffer.concat([header, ...images.map(([, data]) => data)]);
}

function crc32(data) {
  let crc = 0xffffffff;
  for (const byte of data) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit += 1) crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
  }
  return (crc ^ 0xffffffff) >>> 0;
}

function paeth(a, b, c) {
  const estimate = a + b - c;
  const distanceA = Math.abs(estimate - a);
  const distanceB = Math.abs(estimate - b);
  const distanceC = Math.abs(estimate - c);
  return distanceA <= distanceB && distanceA <= distanceC ? a : distanceB <= distanceC ? b : c;
}
