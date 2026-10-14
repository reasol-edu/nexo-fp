// Pruebas de navegador (Playwright) del JavaScript que PHPUnit no cubre y auditoría de accesibilidad
// con axe-core. Se lanza con scripts/e2e/run.sh, que reutiliza el entorno aislado de las capturas
// (SQLite temporal + fixtures + servidor) y exporta BASE y PW_DIR.
//
//   node scripts/e2e/e2e.mjs [--only=<texto>]
//
// Cada prueba es una entrada de TESTS con su nombre y una función que recibe `page`. Una prueba falla si
// lanza una excepción. La auditoría de accesibilidad (grupo «a11y») falla con violaciones graves o críticas
// y solo avisa de las moderadas y leves. Se usa el usuario `admin` sobre «IES Ada Lovelace».
import { pathToFileURL } from 'node:url';
import { join } from 'node:path';
import { readFileSync } from 'node:fs';

const BASE = process.env.BASE || 'http://127.0.0.1:8124';
const PW_DIR = process.env.PW_DIR;
const PW = PW_DIR
  ? await import(pathToFileURL(join(PW_DIR, 'node_modules/playwright/index.mjs')).href)
  : await import('playwright');
const { chromium } = PW.default ?? PW;
const AXE = PW_DIR ? readFileSync(join(PW_DIR, 'node_modules/axe-core/axe.min.js'), 'utf8') : '';

const CENTRE = 'Ada Lovelace';
const DAW_STAY = 'FFEOE DAW 2026';
const only = (process.argv.find((a) => a.startsWith('--only=')) ?? '').slice(7);

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const visible = (page, selector) => page.locator(selector).first().isVisible();

/** Abre el detalle de la estancia cuyo nombre contiene `text`. */
async function openStay(page, text) {
  await page.goto(`${BASE}/estancias`, { waitUntil: 'networkidle' });
  const href = await page.evaluate((t) => {
    const a = [...document.querySelectorAll('a[href^="/estancias/0"]')]
      .find((x) => x.closest('article, li, div.rounded-2xl')?.textContent.includes(t));
    return a?.getAttribute('href');
  }, text);
  assert(href, `no hay estancia «${text}»`);
  await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
  return href;
}

// ── Pruebas ─────────────────────────────────────────────────────────────────
const TESTS = [
  {
    name: 'selección masiva: casillas, cabecera y barra de acciones',
    run: async (page) => {
      await openStay(page, DAW_STAY);
      const rows = page.locator('[data-bulk-select-target="row"]');
      const total = await rows.count();
      assert(total >= 2, `se esperaban varias casillas de puesto y hay ${total}`);
      assert(!(await visible(page, '[data-bulk-select-target="bar"]')), 'la barra debería estar oculta sin selección');

      await rows.nth(0).check();
      assert(await visible(page, '[data-bulk-select-target="bar"]'), 'la barra debería aparecer al marcar un puesto');
      assert((await page.locator('[data-bulk-select-target="count"]').first().innerText()).includes('1'), 'el contador debería indicar 1');

      await page.locator('[data-bulk-select-target="all"]').first().check();
      const checked = await page.locator('[data-bulk-select-target="row"]:checked').count();
      assert(checked === total, `la casilla de cabecera debería marcarlas todas (${checked}/${total})`);

      await page.locator('[data-action="bulk-select#clear"]').first().click();
      assert(!(await visible(page, '[data-bulk-select-target="bar"]')), 'la barra debería ocultarse al limpiar');
      assert((await page.locator('[data-bulk-select-target="row"]:checked').count()) === 0, 'no debería quedar nada marcado');
    },
  },
  {
    name: 'acción masiva en vivo y deshacer',
    run: async (page) => {
      await openStay(page, DAW_STAY);
      const row = page.locator('[data-bulk-select-target="row"]').first();
      const before = await page.locator('.js-student-row').count();
      await row.check();
      await page.locator('[data-action="bulk-select#run"][data-op="sign"]').first().click();
      // El aviso (toast) aparece tras el re-pintado del componente.
      await page.waitForSelector('text=/puestos? actualizados?|omitidos?/i', { timeout: 8000 });
      assert((await page.locator('.js-student-row').count()) === before, 'el listado debería seguir con las mismas filas');
      const undo = page.locator('[data-live-action-param="restoreBulk"]');
      if (await undo.count()) {
        await undo.first().click();
        await page.waitForSelector('text=/devueltos? a su situación anterior/i', { timeout: 8000 });
      }
    },
  },
  {
    name: 'filtros rápidos: marcar, filtrar y quitar',
    run: async (page) => {
      await openStay(page, DAW_STAY);
      const chips = page.locator('.js-filter-chip:not(.hidden)');
      assert((await chips.count()) > 0, 'debería haber algún filtro rápido con filas');
      const rowsBefore = await page.locator('tbody tr.js-student-row:visible').count();
      const chip = chips.first();
      await chip.click();
      assert((await chip.getAttribute('aria-pressed')) === 'true', 'el filtro debería quedar marcado (aria-pressed)');
      const rowsAfter = await page.locator('tbody tr.js-student-row:visible').count();
      assert(rowsAfter <= rowsBefore, `filtrar no debería mostrar más filas (${rowsBefore} → ${rowsAfter})`);
      await page.locator('.js-filter-clear:visible').first().click();
      assert((await page.locator('tbody tr.js-student-row:visible').count()) === rowsBefore, 'al quitar el filtro vuelven todas las filas');
    },
  },
  {
    name: 'el filtro de la URL (?filtro=) se aplica al entrar',
    run: async (page) => {
      const href = await openStay(page, DAW_STAY);
      await page.goto(`${BASE}${href}?filtro=sin-firmar`, { waitUntil: 'networkidle' });
      const pressed = await page.locator('.js-filter-chip[data-filter="sin-firmar"]').getAttribute('aria-pressed');
      assert(pressed === 'true', 'el filtro «sin-firmar» debería estar activo');
    },
  },
  {
    name: 'preferencias recordadas en el navegador (modo asignación rápida)',
    run: async (page) => {
      await openStay(page, DAW_STAY);
      await page.evaluate(() => window.localStorage.removeItem('nexo.stay.quickMode'));
      const toggle = page.locator('.js-quick-toggle').first();
      if (!(await toggle.count())) return; // sin alumnado asignable no hay modo rápido
      await toggle.click();
      const stored = await page.evaluate(() => window.localStorage.getItem('nexo.stay.quickMode'));
      assert(stored === '1', `la preferencia debería guardarse en localStorage (valor: ${stored})`);
      await page.reload({ waitUntil: 'networkidle' });
      assert((await page.locator('.js-quick-toggle').first().getAttribute('aria-pressed')) === 'true', 'el modo rápido debería recordarse al recargar');
      await page.locator('.js-quick-toggle').first().click();
    },
  },
  {
    name: 'duplicar un puesto pregunta cuántas copias',
    run: async (page) => {
      await openStay(page, DAW_STAY);
      const trigger = page.locator('form[data-confirm-copies] .js-confirm-trigger').first();
      if (!(await trigger.count())) return; // sin puestos libres no hay botón
      await trigger.click();
      const overlay = page.locator('.js-confirm-overlay');
      await overlay.waitFor({ timeout: 5000 });
      assert((await overlay.locator('input[type="number"]').count()) === 1, 'el diálogo debería pedir el número de copias');
      await page.keyboard.press('Escape');
      assert((await page.locator('.js-confirm-overlay').count()) === 0, 'Escape debería cerrar el diálogo');
    },
  },
  {
    name: 'paleta de búsqueda (Ctrl+K): acciones al abrir, búsqueda y Escape',
    run: async (page) => {
      await page.goto(`${BASE}/`, { waitUntil: 'networkidle' });
      await page.keyboard.press('Control+k');
      const input = page.locator('[data-command-palette-target="input"]');
      await input.waitFor({ state: 'visible', timeout: 5000 });
      await page.waitForSelector('[data-command-palette-target="results"] a[data-item]', { timeout: 5000 });
      assert((await page.locator('[data-command-palette-target="results"] a[data-item]').count()) > 0, 'sin escribir deberían verse acciones rápidas');
      await input.fill('Ada');
      await page.waitForTimeout(600);
      await page.keyboard.press('Escape');
      await page.waitForSelector('[data-command-palette-target="dialog"].hidden', { state: 'attached', timeout: 5000 });
    },
  },
];

// ── Accesibilidad (axe-core) ────────────────────────────────────────────────
const A11Y_PAGES = [
  ['inicio', '/'],
  ['estancias', '/estancias'],
  ['empresas', '/empresas'],
  ['mi-centro', '/mi-centro'],
  ['revision-datos', '/mi-centro/revision-datos'],
  ['calendario', '/calendario'],
  ['detalle-estancia', async (page) => openStay(page, DAW_STAY)],
  ['perfil', '/perfil'],
  ['ajustes', '/perfil/ajustes'],
  ['empresa', async (page) => {
    await page.goto(`${BASE}/empresas`, { waitUntil: 'networkidle' });
    await page.goto(`${BASE}${await page.locator('a[href^="/empresas/0"]').first().getAttribute('href')}`, { waitUntil: 'networkidle' });
  }],
  ['administracion', '/admin'],
  ['administracion-centros', '/admin/centros'],
  ['registro-actividad', '/admin/registro-actividad'],
  ['docentes-del-centro', async (page) => page.goto(`${BASE}/admin/centros/${await centreId(page)}/docentes-curso`, { waitUntil: 'networkidle' })],
  ['estudiantes', async (page) => page.goto(`${BASE}/admin/centros/${await centreId(page)}/estudiantes`, { waitUntil: 'networkidle' })],
  ['oferta-formativa', async (page) => page.goto(`${BASE}/admin/centros/${await centreId(page)}/familias`, { waitUntil: 'networkidle' })],
  ['editar-centro', async (page) => page.goto(`${BASE}/admin/centros/${await centreId(page)}`, { waitUntil: 'networkidle' })],
];

/** Identificador del centro de las fixtures, tomado del primer enlace de administración de /mi-centro. */
async function centreId(page) {
  await page.goto(`${BASE}/mi-centro`, { waitUntil: 'networkidle' });
  const href = await page.locator('a[href*="/admin/centros/"]').first().getAttribute('href');
  return href.match(/centros\/([0-9a-f-]{36})/)[1];
}

async function auditA11y(page, name, target, mobile = false) {
  if (typeof target === 'function') await target(page);
  else await page.goto(`${BASE}${target}`, { waitUntil: 'networkidle' });
  // Las animaciones de entrada (fundidos) mezclan los colores con el fondo y falsean el contraste.
  await page.waitForTimeout(1500);
  await page.addScriptTag({ content: AXE });
  // En móvil se añade WCAG 2.2 AA (tamaño mínimo de los objetivos táctiles, 24 px).
  const tags = mobile ? ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] : ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
  const result = await page.evaluate(async (tags) => {
    // La barra de depuración de Symfony (solo en desarrollo) no es parte de la aplicación.
    document.querySelectorAll('.sf-toolbar, .sf-toolbarreset, #sfMiniToolbar, [id^="sfwdt"]').forEach((e) => e.remove());
    const r = await window.axe.run(document, {
      runOnly: { type: 'tag', values: tags },
    });
    return r.violations.map((v) => {
      // Para el contraste se resumen los pares de colores distintos (texto sobre fondo) y cuántas veces salen.
      const pairs = {};
      if (v.id === 'color-contrast') {
        for (const n of v.nodes) {
          const d = n.any[0]?.data;
          if (d) {
            const k = `${d.fgColor} sobre ${d.bgColor} (${d.contrastRatio}, mín. ${d.expectedContrastRatio}) p. ej. ${n.html.slice(0, 110).replace(/\s+/g, ' ')}`;
            pairs[k] = (pairs[k] ?? 0) + 1;
          }
        }
      }
      return { id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.slice(0, 3).map((n) => n.target.join(' ')), count: v.nodes.length, pairs };
    });
  }, tags);
  const serious = result.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  for (const v of result) {
    const mark = serious.includes(v) ? '❌' : '⚠️ ';
    console.log(`     ${mark} [${v.impact}] ${v.id} (${v.count}): ${v.help} → ${v.nodes.join(' | ')}`);
    for (const [pair, n] of Object.entries(v.pairs).sort((a, b) => b[1] - a[1]).slice(0, 8)) console.log(`          ${n}× ${pair}`);
  }
  assert(serious.length === 0, `${serious.length} violación(es) grave(s) de accesibilidad en «${name}»`);
}

// ── Ejecución ───────────────────────────────────────────────────────────────
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const jsErrors = [];
page.on('pageerror', (e) => jsErrors.push(e.message));

await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
await page.fill('input[name="_username"]', 'admin');
await page.fill('input[name="_password"]', 'admin');
await page.click('button[type="submit"]');
await page.waitForLoadState('networkidle');
if (page.url().includes('/centro')) {
  await page.locator(`button:has-text("${CENTRE}")`).first().click();
  await page.waitForLoadState('networkidle');
}

const failures = [];
async function check(label, fn) {
  if (only && !label.includes(only)) return;
  try {
    await fn();
    console.log('✅', label);
  } catch (e) {
    failures.push(label);
    console.error('❌', label, '—', e.message.split('\n')[0]);
  }
}

for (const t of TESTS) await check(t.name, () => t.run(page));
for (const [name, target] of A11Y_PAGES) await check(`a11y: ${name}`, () => auditA11y(page, name, target));

// Móvil (iPhone 12/13): mismas pantallas, más desbordamiento horizontal y tamaño de los objetivos táctiles.
await page.setViewportSize({ width: 390, height: 844 });
for (const [name, target] of A11Y_PAGES) {
  await check(`a11y móvil: ${name}`, async () => {
    await auditA11y(page, name, target, true);
    const overflow = await page.evaluate(() => {
      const scrollers = [document.documentElement, ...document.querySelectorAll('main, body > div')];
      const width = Math.max(...scrollers.map((e) => e.scrollWidth - e.clientWidth));
      // Elementos visibles que sobresalen por la derecha (salvo dentro de un contenedor con scroll propio).
      const inScroller = (e) => { for (let p = e.parentElement; p; p = p.parentElement) { if (/(auto|scroll)/.test(getComputedStyle(p).overflowX) && p !== document.documentElement && p !== document.body) return true; } return false; };
      const culprits = [...document.querySelectorAll('main *')]
        .filter((e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.right > window.innerWidth + 1 && getComputedStyle(e).position !== 'fixed' && !inScroller(e); })
        .slice(0, 3).map((e) => `${e.tagName.toLowerCase()}.${String(e.className).split(' ').slice(0, 4).join('.')}`);
      return { width, culprits };
    });
    assert(overflow.width <= 1, `la página desborda horizontalmente ${overflow.width}px: ${overflow.culprits.join(' | ')}`);
  });
}

// Usabilidad móvil (solo informa, no falla): objetivos táctiles pequeños y campos que provocan zoom en iOS.
if (!only || 'ux móvil'.includes(only) || only === 'ux') {
  for (const [name, target] of A11Y_PAGES) {
    if (typeof target === 'function') await target(page); else await page.goto(`${BASE}${target}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const found = await page.evaluate(() => {
      const visible = (e) => { const r = e.getBoundingClientRect(); const s = getComputedStyle(e); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && !e.closest('.sr-only, [hidden], .hidden'); };
      const label = (e) => `${e.tagName.toLowerCase()} «${(e.getAttribute('aria-label') || e.textContent || e.getAttribute('name') || '').trim().replace(/\s+/g, ' ').slice(0, 30)}»`;
      const small = [...document.querySelectorAll('main a[href], main button, main [role="button"], main summary, main input[type="checkbox"], main input[type="radio"], aside a, header a, header button')]
        .filter(visible).filter((e) => { const r = e.getBoundingClientRect(); return r.height < 36 || r.width < 36; })
        .map((e) => { const r = e.getBoundingClientRect(); return `${label(e)} ${Math.round(r.width)}×${Math.round(r.height)}`; });
      const zoom = [...document.querySelectorAll('main input:not([type=checkbox]):not([type=radio]):not([type=hidden]), main select, main textarea')]
        .filter(visible).filter((e) => parseFloat(getComputedStyle(e).fontSize) < 16)
        .map((e) => label(e) + ' ' + getComputedStyle(e).fontSize);
      const tiny = [...document.querySelectorAll('main *')].filter((e) => e.children.length === 0 && e.textContent.trim() && visible(e) && parseFloat(getComputedStyle(e).fontSize) < 11)
        .map((e) => label(e) + ' ' + getComputedStyle(e).fontSize);
      return { small, zoom, tiny };
    });
    const parts = [];
    if (found.small.length) parts.push(`${found.small.length} objetivos <36px: ${[...new Set(found.small)].slice(0, 6).join(', ')}`);
    if (found.zoom.length) parts.push(`${found.zoom.length} campos <16px (zoom iOS): ${[...new Set(found.zoom)].slice(0, 4).join(', ')}`);
    if (found.tiny.length) parts.push(`${found.tiny.length} textos <11px: ${[...new Set(found.tiny)].slice(0, 4).join(', ')}`);
    console.log(parts.length ? `📱 ${name}: ${parts.join(' | ')}` : `📱 ${name}: sin hallazgos`);
  }
}
await page.setViewportSize({ width: 1440, height: 900 });
await check('sin errores de JavaScript en las páginas visitadas', async () => {
  assert(jsErrors.length === 0, `errores de JavaScript: ${jsErrors.slice(0, 3).join(' | ')}`);
});

await browser.close();
if (failures.length) { console.error(`\n${failures.length} comprobación(es) fallida(s).`); process.exit(1); }
console.log('\nTodo correcto.');
