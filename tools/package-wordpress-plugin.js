import fs from 'node:fs/promises';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const distDir = path.join(projectRoot, 'dist');
const pluginDir = path.join(projectRoot, 'wordpress', 'aac-member-portal');
const pluginAppDir = path.join(pluginDir, 'app');
const pluginZipPath = path.join(projectRoot, 'wordpress', 'aac-member-portal.zip');
const execFileAsync = promisify(execFile);

const JS_COMPATIBILITY_ALIASES = [
  'index-154a6d6e.js',
  'index-5387f896.js',
  'index-24a16865.js',
];

const CSS_COMPATIBILITY_ALIASES = [
  'index-03c3ab31.css',
  'index-595f891c.css',
];

const MEDIA_COMPATIBILITY_ALIASES = [
  {
    pattern: /^login-hero-left-image-[^.]+\.jpg$/,
    alias: 'login-hero-left-image.jpg',
  },
  {
    pattern: /^join-hero-homepage-image-[^.]+\.jpg$/,
    alias: 'join-hero-homepage-image.jpg',
  },
  {
    pattern: /^join-hero-static-image-[^.]+\.jpg$/,
    alias: 'join-hero-static-image.jpg',
  },
  {
    pattern: /^join-hero-uploaded-poster-[^.]+\.png$/,
    alias: 'join-hero-uploaded-poster.png',
  },
  {
    pattern: /^join-hero-uploaded-video-web-[^.]+\.mp4$/,
    alias: 'join-hero-uploaded-video-web.mp4',
  },
  {
    pattern: /^join-hero-uploaded-video-web-[^.]+\.webm$/,
    alias: 'join-hero-uploaded-video-web.webm',
  },
];

async function ensureDirectoryExists(targetPath) {
  try {
    await fs.access(targetPath);
  } catch {
    throw new Error(`Required path not found: ${targetPath}`);
  }
}

async function createCompatibilityAliases() {
  const assetsDir = path.join(pluginAppDir, 'assets');
  const assetEntries = await fs.readdir(assetsDir);
  const latestJs = assetEntries.find((entry) => /^index-[^.]+\.js$/.test(entry));
  const latestCss = assetEntries.find((entry) => /^index-[^.]+\.css$/.test(entry));

  if (latestJs) {
    await Promise.all(JS_COMPATIBILITY_ALIASES.map(async (alias) => {
      if (alias === latestJs) {
        return;
      }

      await fs.copyFile(path.join(assetsDir, latestJs), path.join(assetsDir, alias));
    }));
  }

  if (latestCss) {
    await Promise.all(CSS_COMPATIBILITY_ALIASES.map(async (alias) => {
      if (alias === latestCss) {
        return;
      }

      await fs.copyFile(path.join(assetsDir, latestCss), path.join(assetsDir, alias));
    }));
  }

  await Promise.all(MEDIA_COMPATIBILITY_ALIASES.map(async ({ pattern, alias }) => {
    const source = assetEntries.find((entry) => pattern.test(entry));
    if (!source || source === alias) {
      return;
    }

    await fs.copyFile(path.join(assetsDir, source), path.join(assetsDir, alias));
  }));
}

async function createPluginZip() {
  await fs.rm(pluginZipPath, { force: true });
  await execFileAsync('ditto', ['-c', '-k', '--sequesterRsrc', '--keepParent', pluginDir, pluginZipPath], {
    cwd: projectRoot,
  });
}

async function main() {
  await ensureDirectoryExists(distDir);
  await ensureDirectoryExists(pluginDir);

  await fs.rm(pluginAppDir, { recursive: true, force: true });
  await fs.mkdir(pluginAppDir, { recursive: true });
  await fs.cp(distDir, pluginAppDir, { recursive: true });
  await createCompatibilityAliases();
  await createPluginZip();

  console.log(`WordPress plugin assets copied to ${pluginAppDir}`);
  console.log(`Plugin ready at ${pluginDir}`);
  console.log(`Plugin zip updated at ${pluginZipPath}`);
}

main().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});
