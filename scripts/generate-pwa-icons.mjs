/**
 * Rasterize the KOSPAL mark to PNG home-screen icons.
 * Requires: npm install --no-save @resvg/resvg-js
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Resvg } from '@resvg/resvg-js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const outDir = join(root, 'public', 'pwa');

mkdirSync(outDir, { recursive: true });

function render(svgPath, size) {
    const svg = readFileSync(svgPath);
    const resvg = new Resvg(svg, {
        fitTo: { mode: 'width', value: size },
        background: '#1F5C4A',
    });

    return resvg.render().asPng();
}

const iconSvg = join(root, 'resources', 'pwa', 'icon.svg');
const maskableSvg = join(root, 'resources', 'pwa', 'icon-maskable.svg');

writeFileSync(join(outDir, 'icon-192.png'), render(iconSvg, 192));
writeFileSync(join(outDir, 'icon-512.png'), render(iconSvg, 512));
writeFileSync(join(outDir, 'icon-maskable-192.png'), render(maskableSvg, 192));
writeFileSync(join(outDir, 'icon-maskable-512.png'), render(maskableSvg, 512));
writeFileSync(join(root, 'public', 'apple-touch-icon.png'), render(iconSvg, 180));

console.log('Wrote PWA icons to public/pwa and public/apple-touch-icon.png');
