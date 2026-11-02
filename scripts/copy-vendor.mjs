import { copyFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';

const copies = [
  ['node_modules/@alpinejs/csp/dist/cdn.min.js', 'public/assets/vendor/alpine.min.js'],
  ['node_modules/chart.js/dist/chart.umd.js', 'public/assets/vendor/chart.umd.js'],
  ...[400, 500, 600, 700].map((weight) => [
    `node_modules/@fontsource/inter/files/inter-latin-${weight}-normal.woff2`,
    `public/assets/fonts/inter-latin-${weight}.woff2`,
  ]),
];

for (const [source, target] of copies) {
  const destination = resolve(target);
  await mkdir(dirname(destination), { recursive: true });
  await copyFile(resolve(source), destination);
}
