/**
 * Copia dependências npm para public/vendor/ (sem CDN).
 * Uso: npm run vendor:sync
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const root = path.join(__dirname, '..');
const vendor = path.join(root, 'public', 'vendor');

const copies = [
  ['node_modules/bootstrap/dist/css/bootstrap.min.css', 'bootstrap/bootstrap.min.css'],
  ['node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'bootstrap/bootstrap.bundle.min.js'],
  ['node_modules/admin-lte/dist/css/adminlte.min.css', 'admin-lte/adminlte.min.css'],
  ['node_modules/admin-lte/dist/js/adminlte.min.js', 'admin-lte/adminlte.min.js'],
  ['node_modules/jquery/dist/jquery.min.js', 'jquery/jquery.min.js'],
  ['node_modules/@fortawesome/fontawesome-free/css/all.min.css', 'fontawesome/css/all.min.css'],
  ['node_modules/tom-select/dist/css/tom-select.bootstrap4.min.css', 'tom-select/tom-select.bootstrap4.min.css'],
  ['node_modules/tom-select/dist/js/tom-select.complete.min.js', 'tom-select/tom-select.complete.min.js'],
  ['node_modules/chart.js/dist/chart.umd.js', 'chart.js/chart.umd.min.js'],
];

function copyFile(from, to) {
  const src = path.join(root, from);
  const dest = path.join(vendor, to);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.copyFileSync(src, dest);
  console.log('  ' + to);
}

console.log('Sync public/vendor…');
copies.forEach(([from, to]) => copyFile(from, to));

const webfontsSrc = path.join(root, 'node_modules/@fortawesome/fontawesome-free/webfonts');
const webfontsDest = path.join(vendor, 'fontawesome/webfonts');
if (fs.existsSync(webfontsSrc)) {
  fs.mkdirSync(webfontsDest, { recursive: true });
  for (const file of fs.readdirSync(webfontsSrc)) {
    fs.copyFileSync(path.join(webfontsSrc, file), path.join(webfontsDest, file));
    console.log('  fontawesome/webfonts/' + file);
  }
}

console.log('Done.');
