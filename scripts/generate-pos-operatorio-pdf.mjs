/**
 * Gera PDF da documentação Hub Pós-Operatório.
 * Uso: npm run docs:pos-operatorio-pdf
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { mdToPdf } from 'md-to-pdf';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const input = path.join(root, 'docs', 'HUB_POS_OPERATORIO_INTEGRACAO.md');
const output = path.join(root, 'docs', 'HUB_POS_OPERATORIO_INTEGRACAO.pdf');
const css = path.join(root, 'docs', 'pdf', 'hub-pos-operatorio.css');

if (!fs.existsSync(input)) {
    console.error('Arquivo não encontrado:', input);
    process.exit(1);
}

const generated = new Date().toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
});

console.log('Gerando PDF…');
console.log('  Entrada:', path.relative(root, input));
console.log('  Saída:  ', path.relative(root, output));

const pdf = await mdToPdf(
    { path: input },
    {
        dest: output,
        css,
        pdf_options: {
            format: 'A4',
            printBackground: true,
            margin: { top: '18mm', right: '16mm', bottom: '20mm', left: '16mm' },
        },
        body_class: 'hub-pos-operatorio-doc',
        marked_options: {
            gfm: true,
            breaks: false,
        },
        launch_options: {
            args: ['--no-sandbox', '--disable-setuid-sandbox'],
        },
        stylesheet_encoding: 'utf-8',
        header_template: `
            <div style="font-size:8px;width:100%;padding:0 16mm;color:#64748b;font-family:Segoe UI,sans-serif;">
                <span>Unio · Hub Pós-Operatório — Integração</span>
            </div>`,
        footer_template: `
            <div style="font-size:8px;width:100%;padding:0 16mm;color:#64748b;font-family:Segoe UI,sans-serif;display:flex;justify-content:space-between;">
                <span>Gerado em ${generated}</span>
                <span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span>
            </div>`,
        displayHeaderFooter: true,
    }
);

if (!pdf) {
    console.error('Falha ao gerar PDF.');
    process.exit(1);
}

const sizeKb = Math.round(fs.statSync(output).size / 1024);
console.log(`PDF gerado (${sizeKb} KB): ${output}`);
