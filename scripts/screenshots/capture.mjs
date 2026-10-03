// Motor de capturas de la documentación. Se lanza con scripts/screenshots/run.sh, que monta el
// entorno aislado (SQLite + fixtures + servidor) y exporta BASE y PW_DIR.
//
//   node scripts/screenshots/capture.mjs [--list] [manual|slides|<nombre>...]
//
// Cada captura es una entrada de SHOTS: nombre, grupo, fichero de salida, tamaño de ventana y los
// pasos para dejar la pantalla lista. Para añadir una captura nueva basta con añadir una entrada.
// Se usa siempre el usuario `admin` (o el indicado en `user`) sobre «IES Ada Lovelace».
//
// Las capturas del manual son de 1440x900 (factor 1) salvo que se indique; las de la presentación
// tienen los tamaños de las imágenes originales para encajar en las diapositivas.
import { pathToFileURL } from 'node:url';
import { join } from 'node:path';

const BASE = process.env.BASE || 'http://127.0.0.1:8124';
const listOnly = process.argv.includes('--list');
const PW = listOnly ? {} : process.env.PW_DIR
  ? await import(pathToFileURL(join(process.env.PW_DIR, 'node_modules/playwright/index.mjs')).href)
  : await import('playwright');
const { chromium } = PW.default ?? PW;

const CENTRE = 'Ada Lovelace';
const PASSWORDS = { admin: 'admin' };       // el resto de docentes de las fixtures usan «ejemplo»
const HIDE = '.sf-toolbar, .sf-minitoolbar, #sfMiniToolbar, .sf-toolreset, .sf-toolbarreset, .ts-dropdown { display:none !important; } html, body { overflow-x: hidden; }';
const SHARED_STAY = 'compartida';
const DAW_STAY = 'FFEOE DAW 2026';

const M = 'docs/manual/img', S = 'docs/slides/img';

// ── Pasos reutilizables ─────────────────────────────────────────────────────
const go = (path) => async ({ page }) => { await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle' }); };
const wait = (ms) => async ({ page }) => { await page.waitForTimeout(ms); };
const centreId = async (page) => {
  await page.goto(`${BASE}/mi-centro`, { waitUntil: 'networkidle' });
  const href = await page.locator('a[href*="/admin/centros/"]').first().getAttribute('href');
  return href.match(/centros\/([0-9a-f-]{36})/)[1];
};
/** Entra en la estancia cuyo nombre contiene `text` (en el listado de /estancias). */
const openStay = (text) => async ({ page }) => {
  await page.goto(`${BASE}/estancias`, { waitUntil: 'networkidle' });
  const href = await page.evaluate((t) => {
    const a = [...document.querySelectorAll('a[href^="/estancias/0"]')]
      .find((x) => x.closest('article, li, div.rounded-2xl')?.textContent.includes(t));
    return a?.getAttribute('href');
  }, text);
  if (!href) throw new Error(`no hay estancia «${text}»`);
  await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
  return href;
};
const firstCompany = async (page) => {
  await page.goto(`${BASE}/empresas`, { waitUntil: 'networkidle' });
  return page.locator('a[href^="/empresas/0"]').first().getAttribute('href');
};
/** Baja por el árbol de la oferta formativa (familia → enseñanza → nivel → grupo). */
const drill = (...actions) => async ({ page }) => {
  const id = await centreId(page);
  await page.goto(`${BASE}/admin/centros/${id}/familias`, { waitUntil: 'networkidle' });
  for (const action of actions) {
    await page.locator(`[data-live-action-param="${action}"]`).first().click();
    await page.waitForTimeout(1200);
  }
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1200); // los contadores del árbol se cargan con un pequeño retardo
};
const quillSample = async ({ page }) => {
  await page.waitForTimeout(800);
  await page.evaluate(() => {
    const html = '<p><strong>Persona de contacto:</strong> Carmen Ruiz · 954 123 456</p>'
      + '<p><strong>Email:</strong> carmen.ruiz@empresa-ejemplo.com</p><ul><li>Horario de atención: lunes a viernes 9:00–14:00</li></ul>';
    const q = document.querySelector('[data-controller="rich-editor"]')?.__richEditorController?.quill;
    if (q) q.clipboard.dangerouslyPasteHTML(html); else { const e = document.querySelector('.ql-editor'); if (e) e.innerHTML = html; }
  });
};

// ── Catálogo de capturas ────────────────────────────────────────────────────
// size: [ancho, alto, factor]; full: página completa; clip: función que devuelve un locator a capturar.
const SHOTS = [
  // ·········· Manual ··········
  { name: 'inicio', group: 'manual', out: `${M}/inicio.png`, size: [1440, 900, 1], steps: [go('/'), wait(1500)] },
  { name: 'inicio-familias', group: 'manual', out: `${M}/inicio-familias.png`, size: [1440, 900, 1], steps: [go('/'), wait(1500)],
    element: async (page) => {
      await page.evaluate(() => {
        const h = [...document.querySelectorAll('h2, h3, p')].find((e) => /Estudiantes por familia/.test(e.textContent));
        let n = h;
        while (n && !(n.textContent.includes('Puestos por familia') && n.getBoundingClientRect().height < 420)) n = n.parentElement;
        n?.setAttribute('data-shot', '1');
      });
      return page.locator('[data-shot="1"]');
    } },
  { name: 'estancias', group: 'manual', out: `${M}/estancias.png`, size: [1440, 900, 1], steps: [go('/estancias'), wait(1200)] },
  { name: 'estancias-pestanas', group: 'manual', out: `${M}/estancias-pestanas.png`, size: [1440, 900, 1], steps: [go('/estancias'), wait(1200)] },
  { name: 'firmas-pendientes', group: 'manual', out: `${M}/firmas-pendientes.png`, size: [1440, 900, 1],
    steps: [go('/estancias'), async ({ page }) => { await page.getByText('Firmas pendientes').first().click(); await page.waitForTimeout(1200); }] },
  { name: 'estancia-compartida', group: 'manual', out: `${M}/estancia-compartida.png`, size: [1440, 900, 1], user: 'diego.romero',
    steps: [openStay(SHARED_STAY), wait(1200)] },
  { name: 'puesto-preferencia', group: 'manual', out: `${M}/puesto-preferencia.png`, size: [1440, 900, 1], user: 'diego.romero',
    steps: [async (ctx) => { const href = await openStay(SHARED_STAY)(ctx); await ctx.page.goto(`${BASE}${href}/nuevo-puesto`, { waitUntil: 'networkidle' });
      await ctx.page.selectOption('#priority_programme_id', { index: 1 }); await ctx.page.fill('#priority_until', '2026-03-31'); }] },
  { name: 'calendario', group: 'manual', out: `${M}/calendario.png`, size: [1440, 900, 1], steps: [go('/calendario'), wait(1200)] },
  { name: 'empresas', group: 'manual', out: `${M}/empresas.png`, size: [1440, 900, 1], steps: [go('/empresas'), wait(800)] },
  { name: 'empresa-editar', group: 'manual', out: `${M}/empresa-editar.png`, size: [1440, 900, 1],
    steps: [async ({ page }) => { const h = await firstCompany(page); await page.goto(`${BASE}${h}`, { waitUntil: 'networkidle' }); }, quillSample, wait(300)] },
  { name: 'empresa-historial', group: 'manual', out: `${M}/empresa-historial.png`, size: [1440, 900, 1],
    steps: [async ({ page }) => { const h = await firstCompany(page); await page.goto(`${BASE}${h}/historial`, { waitUntil: 'networkidle' }); }, wait(500)] },
  { name: 'centro-educativo', group: 'manual', out: `${M}/centro-educativo.png`, size: [1440, 900, 1], steps: [go('/mi-centro'), wait(800)] },
  { name: 'oferta-formativa', group: 'manual', out: `${M}/oferta-formativa.png`, size: [1440, 900, 1],
    steps: [drill('selectFamily', 'selectProgramme', 'selectLevel', 'selectGroup'), wait(500)] },
  { name: 'administracion', group: 'manual', out: `${M}/administracion.png`, size: [1440, 900, 1], steps: [go('/admin'), wait(800)] },
  { name: 'login', group: 'manual', out: `${M}/login.png`, size: [1440, 900, 2], anonymous: true, steps: [go('/login'), wait(600)] },
  // (releases.png es una captura de la página de releases de GitHub: se hace a mano.)

  // ·········· Presentación ··········
  { name: 'slide-dashboard', group: 'slides', out: `${S}/01_dashboard.png`, size: [1440, 900, 1], steps: [go('/'), wait(1500)] },
  { name: 'slide-centro-selector', group: 'slides', out: `${S}/02_centro_selector.png`, size: [1440, 900, 1], steps: [go('/centro'), wait(600)] },
  { name: 'slide-admin', group: 'slides', out: `${S}/03_admin.png`, size: [1440, 900, 1], steps: [go('/admin'), wait(600)] },
  { name: 'slide-admin-centros', group: 'slides', out: `${S}/03b_admin_centros.png`, size: [1440, 900, 1], steps: [go('/admin/centros'), wait(600)] },
  { name: 'slide-admin-centro-detalle', group: 'slides', out: `${S}/03c_admin_centro_detalle.png`, size: [1184, 900, 1], full: true,
    steps: [async ({ page }) => { const id = await centreId(page); await page.goto(`${BASE}/admin/centros/${id}`, { waitUntil: 'networkidle' }); }] },
  { name: 'slide-admin-familias', group: 'slides', out: `${S}/04_admin_familias.png`, size: [1600, 820, 2], steps: [drill(), wait(600)] },
  { name: 'slide-admin-ensenanza', group: 'slides', out: `${S}/05_admin_ensenanza.png`, size: [1600, 820, 2], steps: [drill('selectFamily', 'selectProgramme'), wait(600)] },
  { name: 'slide-admin-estudiantes', group: 'slides', out: `${S}/06_admin_estudiantes.png`, size: [1079, 900, 1], full: true,
    steps: [async ({ page }) => { const id = await centreId(page); await page.goto(`${BASE}/admin/centros/${id}/estudiantes`, { waitUntil: 'networkidle' }); }, wait(500)] },
  { name: 'slide-admin-docentes', group: 'slides', out: `${S}/06b_admin_docentes.png`, size: [768, 900, 1], full: true, steps: [go('/admin/docentes'), wait(500)] },
  { name: 'slide-empresas', group: 'slides', out: `${S}/07_empresas.png`, size: [1440, 900, 1], full: true, steps: [go('/empresas'), wait(800)] },
  { name: 'slide-empresa-detalle', group: 'slides', out: `${S}/08_empresa_detalle.png`, size: [1184, 900, 1], full: true,
    steps: [async ({ page }) => { const h = await firstCompany(page); await page.goto(`${BASE}${h}`, { waitUntil: 'networkidle' }); }, wait(500)] },
  { name: 'slide-empresa-editar', group: 'slides', out: `${S}/08_empresa_editar.png`, size: [1184, 900, 1],
    steps: [async ({ page }) => { const h = await firstCompany(page); await page.goto(`${BASE}${h}`, { waitUntil: 'networkidle' }); }, quillSample, wait(300)] },
  { name: 'slide-empresa-historial', group: 'slides', out: `${S}/08_empresa_historial.png`, size: [1440, 900, 1],
    steps: [async ({ page }) => { const h = await firstCompany(page); await page.goto(`${BASE}${h}/historial`, { waitUntil: 'networkidle' }); }, wait(500)] },
  { name: 'slide-estancia-nueva', group: 'slides', out: `${S}/09_estancia_nueva.png`, size: [592, 450, 2], user: 'diego.romero',
    steps: [go('/estancias/nueva'), wait(800), async ({ page }) => {
      await page.evaluate(() => {
        const el = document.getElementById('programme_ids');
        el.tomselect.setValue([...el.options].filter((o) => /Aplicaciones Web|Microinform/.test(o.textContent)).map((o) => o.value));
        document.querySelector('input[name="name"]').value = 'FFEOE DAW + SMR 2026';
      });
    }] },
  { name: 'slide-estancias-lista', group: 'slides', out: `${S}/10_estancias_lista.png`, size: [1184, 900, 1], full: true, steps: [go('/estancias'), wait(1200)] },
  { name: 'slide-estancia-detalle', group: 'slides', out: `${S}/11_estancia_detalle.png`, size: [1184, 900, 1], full: true, steps: [openStay(DAW_STAY), wait(1200)] },
  { name: 'slide-puesto-editar', group: 'slides', out: `${S}/12_puesto_editar.png`, size: [1184, 900, 1], full: true,
    steps: [openStay(DAW_STAY), async ({ page }) => {
      const href = await page.locator('a[href*="/puesto/"][href$="/editar"]').first().getAttribute('href');
      await page.goto(`${BASE}${href}`, { waitUntil: 'networkidle' });
    }, wait(800)] },
  { name: 'slide-calendario', group: 'slides', out: `${S}/13_calendario.png`, size: [1184, 900, 1], full: true, steps: [go('/calendario'), wait(1200)] },
  { name: 'slide-palette', group: 'slides', out: `${S}/14_palette.png`, size: [1440, 900, 1],
    steps: [go('/'), wait(800), async ({ page }) => { await page.keyboard.press('Control+k'); await page.waitForTimeout(400); await page.keyboard.type('estancia', { delay: 40 }); await page.waitForTimeout(1200); }] },
  { name: 'slide-notificaciones', group: 'slides', out: `${S}/15_notificaciones.png`, size: [800, 900, 1],
    steps: [go('/'), wait(800), async ({ page }) => { await page.locator('[data-controller~="dropdown"] button, button[aria-label*="otific"]').last().click().catch(() => {}); await page.waitForTimeout(800); }] },
  { name: 'slide-perfil', group: 'slides', out: `${S}/16_perfil.png`, size: [1184, 900, 1], full: true, steps: [go('/perfil'), wait(600)] },
  { name: 'slide-ajustes', group: 'slides', out: `${S}/17_ajustes.png`, size: [1184, 900, 1], steps: [go('/perfil/ajustes'), wait(600)] },
  { name: 'slide-estancia-compartida', group: 'slides', out: `${S}/18_estancia_compartida.png`, size: [1184, 900, 1], user: 'diego.romero',
    steps: [openStay(SHARED_STAY), wait(1200)] },
];

// ── Ejecución ───────────────────────────────────────────────────────────────
const args = process.argv.slice(2);
if (args.includes('--list')) {
  for (const s of SHOTS) console.log(`${s.group.padEnd(7)} ${s.name.padEnd(28)} ${s.out}`);
  process.exit(0);
}
const wanted = args.filter((a) => !a.startsWith('--'));
const selected = SHOTS.filter((s) => !wanted.length || wanted.includes('all') || wanted.includes(s.group) || wanted.includes(s.name));
if (!selected.length) { console.error('Nada que capturar: ', wanted.join(', ')); process.exit(2); }

const browser = await chromium.launch();
const states = {};                                  // sesión iniciada por usuario, reutilizada entre capturas

async function login(user) {
  if (states[user]) return states[user];
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="_username"]', user);
  await page.fill('input[name="_password"]', PASSWORDS[user] ?? 'ejemplo');
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
  if (page.url().includes('/centro')) {
    await page.locator(`button:has-text("${CENTRE}")`).first().click();
    await page.waitForLoadState('networkidle');
  }
  states[user] = await ctx.storageState();
  await ctx.close();
  return states[user];
}

const failures = [];
for (const shot of selected) {
  const [width, height, scale] = shot.size;
  const user = shot.user ?? 'admin';
  const ctx = await browser.newContext({
    viewport: { width, height }, deviceScaleFactor: scale,
    ...(shot.anonymous ? {} : { storageState: await login(user) }),
  });
  const page = await ctx.newPage();
  try {
    for (const step of shot.steps) await step({ page, ctx });
    await page.addStyleTag({ content: HIDE });
    await page.waitForTimeout(500);
    await page.evaluate(() => { document.querySelectorAll('*').forEach((e) => { e.scrollLeft = 0; }); window.scrollTo(0, 0); document.activeElement?.blur?.(); });
    if (shot.full) {
      // El contenido hace scroll dentro del diseño (barra lateral fija), así que `fullPage` no basta:
      // se alarga la ventana hasta la altura real del contenido.
      const contentHeight = await page.evaluate(() => Math.ceil(Math.max(
        document.documentElement.scrollHeight,
        ...[...document.querySelectorAll('*')]
          .filter((e) => /(auto|scroll)/.test(getComputedStyle(e).overflowY) && e.scrollHeight > e.clientHeight)
          .map((e) => e.scrollHeight + e.getBoundingClientRect().top),
      )));
      await page.setViewportSize({ width, height: Math.max(height, Math.min(contentHeight, 6000)) });
      await page.waitForTimeout(500);
    }
    if (shot.element) await (await shot.element(page)).screenshot({ path: shot.out });
    else await page.screenshot({ path: shot.out });
    console.log('✅', shot.out);
  } catch (e) {
    failures.push(shot.name);
    console.error('❌', shot.name, '—', e.message.split('\n')[0]);
  } finally {
    await ctx.close();
  }
}
await browser.close();
if (failures.length) { console.error(`\n${failures.length} captura(s) fallidas: ${failures.join(', ')}`); process.exit(1); }
console.log(`\n${selected.length} captura(s) generadas.`);
