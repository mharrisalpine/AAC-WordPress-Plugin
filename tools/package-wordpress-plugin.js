import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const distDir = path.join(projectRoot, 'dist');
const pluginDir = path.join(projectRoot, 'wordpress', 'aac-member-portal');
const pluginAppDir = path.join(pluginDir, 'app');

async function ensureDirectoryExists(targetPath) {
  try {
    await fs.access(targetPath);
  } catch {
    throw new Error(`Required path not found: ${targetPath}`);
  }
}

async function main() {
  await ensureDirectoryExists(distDir);
  await ensureDirectoryExists(pluginDir);

  await fs.rm(pluginAppDir, { recursive: true, force: true });
  await fs.mkdir(pluginAppDir, { recursive: true });
  await fs.cp(distDir, pluginAppDir, { recursive: true });

  console.log(`WordPress plugin assets copied to ${pluginAppDir}`);
  console.log(`Plugin ready at ${pluginDir}`);
}

main().catch((error) => {
  console.error(error.message);
  process.exitCode = 1;
});
