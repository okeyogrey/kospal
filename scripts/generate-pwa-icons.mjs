/**
 * Regenerate PWA, favicon, and Tauri icons from resources/brand/logo.png.
 */
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const script = join(dirname(fileURLToPath(import.meta.url)), 'generate-brand-icons.py');
const result = spawnSync('python', [script], { stdio: 'inherit' });

process.exit(result.status ?? 1);
