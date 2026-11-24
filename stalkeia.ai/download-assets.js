const fs = require("fs");
const path = require("path");

const BASE_URL = "https://ghostspy.website";
const ROOT_DIR = __dirname;
const FORCE = process.argv.includes("--force");

const ASSET_PATH_REGEX = /["'(`](\/(?:assets|images|img|fonts|media|audio|video|videos|icons|sounds|static)\/[^"'`)\s<>\\]+|\/favicon\.(?:png|ico|svg)|\/site\.webmanifest|\/manifest\.json)(?:\?[^"'`)\s<>\\]*)?/g;
const CSS_URL_REGEX = /url\(\s*["']?([^"')]+)["']?\s*\)/g;
const JS_IMPORT_REGEX = /(?:from|import)\s*\(?\s*["'](\/[^"']+\.(?:js|mjs|css))(?:\?[^"']*)?["']/g;

function normalizeLocalPath(rawUrl) {
  let clean = rawUrl.trim();
  if (clean.startsWith(BASE_URL)) {
    clean = clean.slice(BASE_URL.length);
  }
  const qIdx = clean.indexOf("?");
  const withoutQuery = qIdx !== -1 ? clean.slice(0, qIdx) : clean;
  const hIdx = withoutQuery.indexOf("#");
  const localRel = hIdx !== -1 ? withoutQuery.slice(0, hIdx) : withoutQuery;
  return {
    requestPath: clean,
    localRel: localRel.replace(/^\/+/, "")
  };
}

function isExpectedContentType(ext, contentType) {
  if (!contentType) return true;
  const ct = contentType.toLowerCase();
  if (ext !== ".html" && ext !== ".htm" && ct.includes("text/html")) {
    return false;
  }
  return true;
}

function extractPathsFromContent(content, fileRelPath) {
  const found = new Set();

  for (const match of content.matchAll(ASSET_PATH_REGEX)) {
    const full = match[0].slice(1);
    if (!full.includes("${") && !full.includes("`")) {
      found.add(full);
    }
  }

  for (const match of content.matchAll(JS_IMPORT_REGEX)) {
    const p = match[1];
    if (p && !p.includes("${")) {
      found.add(p);
    }
  }

  if (fileRelPath.endsWith(".css")) {
    const dir = path.posix.dirname("/" + fileRelPath.replace(/\\/g, "/"));
    for (const match of content.matchAll(CSS_URL_REGEX)) {
      const ref = match[1].trim();
      if (!ref || ref.startsWith("data:") || ref.startsWith("http://") || ref.startsWith("https://") || ref.startsWith("#")) {
        continue;
      }
      const resolved = ref.startsWith("/") ? ref : path.posix.resolve(dir, ref);
      found.add(resolved);
    }
  }

  return Array.from(found);
}

function walkExistingFiles(dir, results = []) {
  if (!fs.existsSync(dir)) return results;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      walkExistingFiles(fullPath, results);
    } else if (
      entry.name !== "download-assets.js" &&
      /\.(html|htm|js|mjs|css|json|webmanifest)$/i.test(entry.name)
    ) {
      results.push(path.relative(ROOT_DIR, fullPath).replace(/\\/g, "/"));
    }
  }
  return results;
}

async function downloadFile(requestPath, localRel) {
  const destPath = path.join(ROOT_DIR, localRel);
  const ext = path.extname(localRel).toLowerCase();
  const urlsToTry = [BASE_URL + requestPath];
  if (requestPath.includes("?")) {
    urlsToTry.push(BASE_URL + "/" + localRel);
  }

  for (const url of urlsToTry) {
    try {
      const res = await fetch(url, {
        headers: {
          "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
        }
      });
      if (!res.ok) continue;
      const contentType = res.headers.get("content-type") || "";
      if (!isExpectedContentType(ext, contentType)) {
        return { ok: false, reason: `HTML fallback (${contentType}) at ${url}` };
      }
      const buffer = Buffer.from(await res.arrayBuffer());
      if (buffer.length === 0) {
        return { ok: false, reason: `Empty response at ${url}` };
      }
      fs.mkdirSync(path.dirname(destPath), { recursive: true });
      fs.writeFileSync(destPath, buffer);
      return { ok: true, size: buffer.length, url };
    } catch (err) {
      if (url === urlsToTry[urlsToTry.length - 1]) {
        return { ok: false, reason: err.message };
      }
    }
  }
  return { ok: false, reason: `HTTP 404/error for ${urlsToTry[0]}` };
}

async function main() {
  const scannedFiles = new Set();
  const seenAssets = new Set();
  const queue = [];

  for (const rel of walkExistingFiles(ROOT_DIR)) {
    queue.push({ requestPath: "/" + rel, localRel: rel, isSeed: true });
  }

  let downloadedCount = 0;
  let skippedCount = 0;
  const failed = [];

  while (queue.length > 0) {
    const item = queue.shift();
    const { requestPath, localRel, isSeed } = item;

    if (!localRel || localRel.includes("..") || localRel.endsWith("/")) continue;

    const fullPath = path.join(ROOT_DIR, localRel);
    const exists = fs.existsSync(fullPath) && fs.statSync(fullPath).size > 0;

    if (!isSeed && (!exists || FORCE)) {
      const result = await downloadFile(requestPath, localRel);
      if (result.ok) {
        downloadedCount++;
        console.log(`[DOWNLOADED] ${localRel} (${result.size} bytes)`);
      } else {
        failed.push({ localRel, reason: result.reason });
        console.log(`[FAILED] ${localRel} -> ${result.reason}`);
        continue;
      }
    } else if (!isSeed && exists) {
      skippedCount++;
    }

    if (!scannedFiles.has(localRel) && fs.existsSync(fullPath)) {
      scannedFiles.add(localRel);
      if (/\.(html|htm|js|mjs|css|json|webmanifest)$/i.test(localRel)) {
        const content = fs.readFileSync(fullPath, "utf8");
        const refs = extractPathsFromContent(content, localRel);
        for (const ref of refs) {
          const norm = normalizeLocalPath(ref);
          if (!norm.localRel || seenAssets.has(norm.localRel)) continue;
          seenAssets.add(norm.localRel);
          queue.push({ requestPath: norm.requestPath, localRel: norm.localRel, isSeed: false });
        }
      }
    }
  }

  console.log("\nSummary:");
  console.log(`- Scanned text/code files: ${scannedFiles.size}`);
  console.log(`- Downloaded missing assets: ${downloadedCount}`);
  console.log(`- Already present assets: ${skippedCount}`);
  console.log(`- Unavailable on origin: ${failed.length}`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
