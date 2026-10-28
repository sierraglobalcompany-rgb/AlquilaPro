import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const baseUrl = (process.env.CAND03_G5_BASE_URL || 'http://127.0.0.1:8787').replace(/\/+$/, '');
const fixturePath = process.env.CAND03_G5_FIXTURE || path.join(process.env.RUNNER_TEMP || '', 'cand03-g5-fixture.json');
const artifactDir = path.join(path.dirname(fileURLToPath(import.meta.url)), 'artifacts');
const eventPath = process.env.GITHUB_EVENT_PATH || '';
const ciSha = process.env.GITHUB_SHA || 'unknown';
let eventData = {};
try { eventData = JSON.parse(await fs.readFile(eventPath, 'utf8')); } catch {}
const prHeadSha = eventData.pull_request?.head?.sha || ciSha;
const viewportCases = [
  { name: 'desktop-1448x1086', width: 1448, height: 1086, mobile: false },
  { name: 'desktop-1100x850', width: 1100, height: 850, mobile: false },
  { name: 'tablet-768x1024', width: 768, height: 1024, mobile: false },
  { name: 'mobile-375x812', width: 375, height: 812, mobile: true }
];
const regions = [
  ['summary', 'Resumen'],
  ['information', 'Estado de la información'],
  ['occupants', 'Ocupantes del hogar'],
  ['pets', 'Mascotas'],
  ['rules', 'Reglas y observaciones'],
  ['next-steps', 'Próximos pasos']
];
const manifest = {
  schema_version: 1,
  task: 'AP-R7-CAND03-G5-CI-BROWSER-REAL-20261026',
  synthetic_data_only: true,
  repository: 'sierraglobalcompany-rgb/AlquilaPro',
  pr: 55,
  pr_branch: eventData.pull_request?.head?.ref || 'unknown',
  pr_head_sha: prHeadSha,
  ci_sha: ciSha,
  ci_merge_sha: ciSha,
  browser: { engine: 'Chromium', playwright: '1.64.0' },
  approved_mockup: {
    name: '03_OCUPANTES_MASCOTAS.png',
    sha256: 'be9b8848afed75e31be6df6fd3e1d341b2b960b0d0ca71de68ad33efd3866ae0',
    viewport: '1448x1086',
    visual_fidelity_ruling: 'PENDING_INDEPENDENT_COMPARISON',
    artifact_included: false
  },
  viewports: viewportCases.map(({ name, width, height, mobile }) => ({ name, width, height, mobile })),
  started_at: new Date().toISOString(),
  status: 'RUNNING',
  checks: [],
  screenshots: [],
  metrics: [],
  documents: [],
  rulings: {
    QA_FUNCIONAL: 'RUNNING',
    UX_FIDELITY: 'PRELIMINARY_METRICS_ONLY',
    ACCESSIBILITY: 'BASIC_AUTOMATED_CHECKS_ONLY',
    LEY5_INDEPENDIENTE: 'PENDING',
    OWNER_UAT: 'PENDING',
    HOSTINGER: 'NOT_RUN'
  },
  diagnostics: {
    console_errors: 0,
    unexpected_console_errors: 0,
    page_errors: 0,
    failed_requests: 0,
    unexpected_failed_requests: 0
  }
};
const trace = [];
const consoleErrors = [];
const pageErrors = [];
const failedRequests = [];
const responseObservations = [];
const downloadObservations = [];
const verifiedDownloads = [];
let activeCheck = 'setup';
let browser = null;

function assert(condition, message) {
  if (!condition) throw new Error(message);
}
function sha256(bytes) {
  return crypto.createHash('sha256').update(bytes).digest('hex');
}
function safePathLabel(value) {
  const raw = String(value || '/');
  let pathname;
  try {
    pathname = new URL(raw, baseUrl).pathname;
  } catch (error) {
    const candidate = raw.split(/[?#]/, 1)[0];
    pathname = candidate.startsWith('/') ? candidate : '/[invalid-path]';
  }
  pathname = pathname.replace(/\/apply\/continue\/[^/]+/g, '/apply/continue/[redacted]');
  pathname = pathname.replace(/\/[^/]*@[^/]*(?=\/|$)/g, '/[redacted]');
  pathname = pathname.replace(/\/[a-f0-9]{64}(?=\/|$)/gi, '/[redacted]');
  return pathname.replace(/[^A-Za-z0-9_./:[\]-]/g, '_').slice(0, 180);
}
function safeError(error) {
  let value = error instanceof Error ? error.name + ': ' + error.message : String(error);
  value = value.replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[email-redacted]');
  value = value.replace(/([?&](?:token|password|csrf_token|identifier)=)[^&\s]+/gi, '$1[redacted]');
  value = value.replace(/\b[a-f0-9]{64}\b/gi, '[token-redacted]');
  return value.slice(0, 240);
}
function note(label, role, outcome, extra = {}) {
  trace.push({ at: new Date().toISOString(), label, role, outcome, ...extra });
}
async function runCheck(name, action) {
  const previousCheck = activeCheck;
  activeCheck = name;
  try {
    const details = await action();
    manifest.checks.push({ name, status: 'PASS', details: details ?? null });
    note(name, 'harness', 'PASS');
    return true;
  } catch (error) {
    manifest.checks.push({ name, status: 'FAIL', error: safeError(error) });
    note(name, 'harness', 'FAIL', { error_fingerprint: sha256(Buffer.from(safeError(error))) });
    return false;
  } finally {
    activeCheck = previousCheck;
  }
}
async function makeContext(browser, viewport) {
  return browser.newContext({
    viewport: { width: viewport.width, height: viewport.height },
    deviceScaleFactor: 1,
    isMobile: viewport.mobile,
    hasTouch: viewport.mobile,
    acceptDownloads: true
  });
}
function watchPage(page, role) {
  page.on('response', response => {
    const request = response.request();
    const status = response.status();
    if (status >= 400 || request.method() === 'POST') {
      responseObservations.push({
        phase: activeCheck,
        role,
        method: request.method(),
        path: safePathLabel(request.url()),
        status
      });
    }
  });
  page.on('console', message => {
    if (message.type() === 'error') {
      const value = message.text();
      const reportedStatus = value.match(/^Failed to load resource: the server responded with a status of (\d{3})\b/i);
      const status = reportedStatus ? Number(reportedStatus[1]) : null;
      const eventPath = safePathLabel(message.location().url);
      const matchingResponses = status === null ? [] : responseObservations.filter(item =>
        item.phase === activeCheck
        && item.role === role
        && item.path === eventPath
        && item.status === status
      );
      const response = matchingResponses.length === 1 ? matchingResponses[0] : null;
      const event = {
        phase: activeCheck,
        role,
        type: message.type(),
        path: eventPath,
        message_kind: reportedStatus ? 'browser_http_status_error' : 'unclassified_console_error',
        error_text: reportedStatus ? 'Browser reported an HTTP status error' : 'Unclassified console error; inspect the fingerprint',
        status: response?.status ?? status,
        response_method: response?.method ?? null,
        response_path: response?.path ?? null,
        response_status: response?.status ?? null,
        fingerprint: sha256(Buffer.from(value))
      };
      consoleErrors.push(event);
      note('browser-console', role, 'error', event);
    }
  });
  page.on('pageerror', error => {
    const value = safeError(error);
    const event = {
      phase: activeCheck,
      role,
      type: 'pageerror',
      path: safePathLabel(page.url()),
      error_text: error instanceof Error ? error.name : 'Unclassified page error',
      fingerprint: sha256(Buffer.from(value))
    };
    pageErrors.push(event);
    note('browser-pageerror', role, 'error', event);
  });
  page.on('requestfailed', request => {
    const route = safePathLabel(request.url());
    const failureText = request.failure()?.errorText || 'unknown';
    const event = {
      phase: activeCheck,
      role,
      method: request.method(),
      resource_type: request.resourceType(),
      path: route,
      status: null,
      error_text: /^net::[A-Z0-9_]+$/i.test(failureText) ? failureText : 'Unclassified request failure',
      fingerprint: sha256(Buffer.from(failureText))
    };
    failedRequests.push(event);
    note('browser-request', role, 'failed', event);
  });
  page.on('download', download => {
    const event = { phase: activeCheck, role, path: safePathLabel(download.url()) };
    downloadObservations.push(event);
    note('browser-download', role, 'started', event);
  });
}
function classifyExpectedConsoleErrors(events, responses, checks, mainCandidateId) {
  const expectedConsoleErrors = [];
  const unexpectedConsoleErrors = [];
  const candidateId = String(mainCandidateId);
  const candidatePath = /^[1-9]\d*$/.test(candidateId) ? '/admin/candidates/' + candidateId : null;
  const rules = candidatePath ? [
    { phase: 'invalid-login-real-form', role: 'guest', path: '/login', method: 'POST', status: 422 },
    { phase: 'guest-tenant-and-role-permissions', role: 'no_access', path: candidatePath, method: 'GET', status: 403 },
    { phase: 'guest-tenant-and-role-permissions', role: 'property_documents_only', path: candidatePath, method: 'GET', status: 403 },
    { phase: 'guest-tenant-and-role-permissions', role: 'tenant', path: candidatePath, method: 'GET', status: 403 },
    { phase: 'real-household-create-edit-delete-and-csrf', role: 'manager', path: candidatePath + '/household/occupant', method: 'POST', status: 422 },
    { phase: 'real-household-create-edit-delete-and-csrf', role: 'manager', path: candidatePath + '/household/pet', method: 'POST', status: 422 },
    { phase: 'household-photo-consent-validation', role: 'manager', path: candidatePath + '/household/occupant', method: 'POST', status: 422 },
    { phase: 'household-photo-file-validation', role: 'manager', path: candidatePath + '/household/pet', method: 'POST', status: 422 }
  ] : [];
  const usedResponseIndexes = new Set();

  for (const event of events) {
    const rule = rules.find(item =>
      item.phase === event.phase
      && item.role === event.role
      && item.path === event.path
      && item.status === event.status
    );
    const eventMetadataMatches = rule
      && event.type === 'error'
      && event.message_kind === 'browser_http_status_error'
      && (event.response_method === null || event.response_method === undefined || event.response_method === rule.method)
      && (event.response_path === null || event.response_path === undefined || event.response_path === rule.path)
      && (event.response_status === null || event.response_status === undefined || event.response_status === rule.status);
    const completedCheck = rule && checks.filter(item => item.name === rule.phase);
    const matchingResponseIndexes = rule ? responses.flatMap((response, index) =>
      response.phase === rule.phase
      && response.role === rule.role
      && response.method === rule.method
      && response.path === rule.path
      && response.status === rule.status
        ? [index]
        : []
    ) : [];
    const responseFieldsMayBeAbsent = rule?.phase === 'real-household-create-edit-delete-and-csrf'
      && rule.role === 'manager';
    const responseFieldsPresent = event.response_method !== null && event.response_method !== undefined
      && event.response_path !== null && event.response_path !== undefined
      && event.response_status !== null && event.response_status !== undefined;
    const responseFieldsValid = responseFieldsPresent || responseFieldsMayBeAbsent;
    const responseIndex = matchingResponseIndexes.length === 1 ? matchingResponseIndexes[0] : -1;
    const expected = Boolean(
      eventMetadataMatches
      && completedCheck.length === 1
      && completedCheck[0].status === 'PASS'
      && responseFieldsValid
      && responseIndex >= 0
      && !usedResponseIndexes.has(responseIndex)
    );

    if (expected) {
      usedResponseIndexes.add(responseIndex);
      expectedConsoleErrors.push(event);
    } else {
      unexpectedConsoleErrors.push(event);
    }
  }

  return { expectedConsoleErrors, unexpectedConsoleErrors };
}
function classifyExpectedBrowserEvents(mainCandidateId) {
  const consoleClassification = classifyExpectedConsoleErrors(
    consoleErrors,
    responseObservations,
    manifest.checks,
    mainCandidateId
  );

  const expectedFailedRequests = [];
  const unexpectedFailedRequests = [];
  const classifiedFailureIndexes = new Set();
  for (const download of verifiedDownloads) {
    if (!download.hash_match || !downloadObservations.some(item =>
      item.phase === download.phase && item.role === download.role && item.path === download.path
    )) continue;
    const index = failedRequests.findIndex((event, eventIndex) =>
      !classifiedFailureIndexes.has(eventIndex)
      && event.phase === download.phase
      && event.role === download.role
      && event.method === 'GET'
      && event.path === download.path
      && event.error_text === 'net::ERR_ABORTED'
    );
    if (index >= 0) {
      classifiedFailureIndexes.add(index);
      expectedFailedRequests.push(failedRequests[index]);
    }
  }
  failedRequests.forEach((event, index) => {
    if (!classifiedFailureIndexes.has(index)) unexpectedFailedRequests.push(event);
  });
  return {
    ...consoleClassification,
    expectedFailedRequests,
    unexpectedFailedRequests
  };
}
async function settle(page) {
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await page.evaluate(async () => {
    if (document.fonts?.ready) await Promise.race([document.fonts.ready, new Promise(resolve => setTimeout(resolve, 2500))]);
  }).catch(() => {});
}
async function login(page, credentials, role) {
  await page.goto(baseUrl + '/login', { waitUntil: 'domcontentloaded' });
  await settle(page);
  await page.locator('#auth_identifier').fill(credentials.email);
  await page.locator('#auth_password').fill(credentials.password);
  const responsePromise = page.waitForResponse(response =>
    response.request().method() === 'POST' && new URL(response.url()).pathname === '/login'
  );
  await page.getByRole('button', { name: 'Entrar', exact: true }).click();
  const response = await responsePromise;
  await settle(page);
  assert(response.status() === 303, 'El formulario real de inicio de sesión no devolvió 303.');
  const destination = new URL(page.url()).pathname;
  assert(destination === '/' || (role === 'tenant' && destination === '/tenant'), 'La sesión no llegó a su destino autenticado.');
  note('real-login', role, '303-session-established');
}
async function prepareImages(page) {
  await page.evaluate(async () => {
    const images = Array.from(document.images);
    for (const image of images) image.loading = 'eager';
    await Promise.all(images.map(image => image.decode().catch(() => {})));
  }).catch(() => {});
}
async function capture(page, label, role, viewport) {
  await settle(page);
  await prepareImages(page);
  const safeLabel = label.replace(/[^A-Za-z0-9_-]/g, '-');
  const safeRole = role.replace(/[^A-Za-z0-9_-]/g, '-');
  const prefix = safeRole + '__' + safeLabel + '__' + viewport.name;
  for (const mode of ['viewport', 'full-page']) {
    const bytes = await page.screenshot({
      fullPage: mode === 'full-page',
      animations: 'disabled',
      caret: 'hide',
      type: 'png',
      timeout: 30000
    });
    const file = prefix + '__' + mode + '.png';
    const absolute = path.join(artifactDir, 'screenshots', file);
    const pngWidth = bytes.readUInt32BE(16);
    const pngHeight = bytes.readUInt32BE(20);
    assert(pngWidth === viewport.width, 'La captura no conserva el ancho del viewport.');
    if (mode === 'viewport') assert(pngHeight === viewport.height, 'La captura viewport quedó recortada o alterada.');
    if (mode === 'full-page') assert(pngHeight >= viewport.height, 'La captura full-page no cubre el viewport completo.');
    await fs.mkdir(path.dirname(absolute), { recursive: true });
    await fs.writeFile(absolute, bytes);
    manifest.screenshots.push({
      file: 'screenshots/' + file,
      role,
      state: label,
      viewport: viewport.name,
      mode,
      width: pngWidth,
      height: pngHeight,
      bytes: bytes.length,
      sha256: sha256(bytes),
      route: safePathLabel(page.url())
    });
  }
}
async function inspectHousehold(page, label, role, viewport, expectedRegions = 6) {
  await prepareImages(page);
  const measured = await page.evaluate(expected => {
    const boxes = {};
    for (const [key] of expected) {
      const element = document.querySelector('[data-household-region="' + key + '"]');
      if (!element) {
        boxes[key] = null;
        continue;
      }
      const rect = element.getBoundingClientRect();
      boxes[key] = {
        x: Math.round(rect.x), y: Math.round(rect.y),
        width: Math.round(rect.width), height: Math.round(rect.height),
        text_length: (element.innerText || '').trim().length,
        heading_labelled: Boolean(element.getAttribute('aria-labelledby')
          && document.getElementById(element.getAttribute('aria-labelledby')))
      };
    }
    const html = document.documentElement;
    const body = document.body;
    return {
      regions: boxes,
      viewport_width: window.innerWidth,
      document_width: Math.max(html.scrollWidth, body.scrollWidth),
      document_height: Math.max(html.scrollHeight, body.scrollHeight),
      active_section: document.querySelector('[data-candidate-section-body]')?.getAttribute('data-candidate-section-body') || null,
      image_states: Array.from(document.images).map(image => ({
        loaded: image.complete && image.naturalWidth > 0,
        width: image.naturalWidth,
        height: image.naturalHeight
      })),
      labelled_controls: Array.from(document.querySelector('[data-candidate-section-body="household"]')
        ?.querySelectorAll('input:not([type="hidden"]),select,textarea') || [])
        .every(control => control.labels && control.labels.length > 0)
    };
  }, regions);
  assert(Object.values(measured.regions).filter(Boolean).length === expectedRegions, 'No están presentes todas las regiones esperadas.');
  for (const [key, name] of regions) {
    const region = measured.regions[key];
    assert(region && region.height > 0 && region.text_length > 0, 'Región vacía o no visible: ' + name);
    assert(region.heading_labelled, 'La región no tiene encabezado asociado: ' + name);
  }
  assert(measured.document_width <= measured.viewport_width, 'Hay desbordamiento horizontal en el estado capturado.');
  assert(measured.labelled_controls, 'Hay controles de formulario sin etiqueta asociada.');
  assert(measured.image_states.every(image => image.loaded), 'Hay imágenes de hogar sin cargar o decodificar.');
  const result = { label, role, viewport: viewport.name, ...measured };
  manifest.metrics.push(result);
  return result;
}
async function openHousehold(page, candidateId) {
  const response = await page.goto(baseUrl + '/admin/candidates/' + candidateId + '?section=household', { waitUntil: 'domcontentloaded' });
  await settle(page);
  assert(response && response.status() === 200, 'La ruta autenticada de hogar no devolvió HTTP 200.');
  assert(await page.locator('[data-candidate-section-body="household"]').count() === 1, 'No se renderizó la sección real de hogar.');
  return response;
}
async function submitAndWait(page, form, buttonName, routeSuffix) {
  const responsePromise = page.waitForResponse(response =>
    response.request().method() === 'POST'
    && new URL(response.url()).pathname.endsWith(routeSuffix),
    { timeout: 20000 }
  );
  await form.getByRole('button', { name: buttonName, exact: true }).click();
  const response = await responsePromise;
  await settle(page);
  assert(response.status() === 303, 'La mutación del formulario real no terminó con 303.');
  return response;
}
async function csrfToken(page) {
  return page.locator('input[name="csrf_token"]').first().inputValue();
}
async function formByRecord(page, kind, recordId) {
  const region = kind === 'occupant' ? 'occupants' : 'pets';
  const section = page.locator('section[data-household-region="' + region + '"]');
  return section.locator('form.candidate-household-edit:has(input[name="record_id"][value="' + recordId + '"])').first();
}
async function openCreateForm(page, kind, touch = false) {
  const detail = page.locator('details[data-household-action="create"][data-household-kind="' + kind + '"]');
  assert(await detail.count() === 1, 'No existe una acción directa de creación para ' + kind + '.');
  if (!(await detail.evaluate(element => element.open))) {
    const summary = detail.locator('summary');
    if (touch) {
      await summary.scrollIntoViewIfNeeded();
      await summary.tap();
    } else await summary.click();
  }
  assert(await detail.evaluate(element => element.open), 'No se abrió el formulario directo de creación para ' + kind + '.');
  assert(await page.locator('details[data-household-action="edit"][open][data-household-kind="' + kind + '"]').count() === 0, 'La creación dejó abierto un editor de ' + kind + '.');
  const form = detail.locator('form.candidate-household-edit[data-household-form-kind="' + kind + '"][data-household-form-id="new"]');
  assert(await form.count() === 1 && await form.isVisible(), 'No está visible el formulario de creación para ' + kind + '.');
  return form;
}
async function openRecordEditor(page, kind, recordId, touch = false) {
  const detail = page.locator('details[data-household-action="edit"][data-household-kind="' + kind + '"][data-household-record-id="' + recordId + '"]');
  assert(await detail.count() === 1, 'La ficha ' + recordId + ' no tiene un editor individual de ' + kind + '.');
  if (!(await detail.evaluate(element => element.open))) {
    const summary = detail.locator('summary');
    if (touch) {
      await summary.scrollIntoViewIfNeeded();
      await summary.tap();
    } else await summary.click();
  }
  assert(await detail.evaluate(element => element.open), 'No se abrió el editor individual ' + recordId + '.');
  const region = kind === 'occupant' ? 'occupants' : 'pets';
  assert(await page.locator('section[data-household-region="' + region + '"] details[data-household-action="edit"][open]').count() === 1, 'Hay más de una ficha abierta en la región ' + region + '.');
  assert(await page.locator('section[data-household-region="' + region + '"] details[data-household-action="create"][open]').count() === 0, 'El editor dejó abierta también la creación de ' + kind + '.');
  const form = await formByRecord(page, kind, recordId);
  assert(await form.count() === 1 && await form.isVisible(), 'El formulario de la ficha ' + recordId + ' no quedó visible.');
  return form;
}
async function submitInvalidEditAndCapture(page, kind, recordId, viewport) {
  const region = kind === 'occupant' ? 'occupants' : 'pets';
  const field = kind === 'occupant' ? 'full_name' : 'name';
  const form = await openRecordEditor(page, kind, recordId, viewport.mobile);
  const responsePromise = page.waitForResponse(response =>
    response.request().method() === 'POST'
    && new URL(response.url()).pathname.endsWith('/household/' + kind)
  );
  await form.evaluate((element, fieldName) => {
    const input = element.querySelector('input[name="' + fieldName + '"]');
    if (input) {
      input.value = '';
      input.removeAttribute('required');
    }
    element.requestSubmit();
  }, field);
  const response = await responsePromise;
  await settle(page);
  assert(response.status() === 422, 'La validación real de ' + kind + ' no devolvió HTTP 422.');
  assert((response.headers()['content-type'] || '').includes('text/html'), 'La validación de ' + kind + ' no volvió a renderizar la ficha completa.');
  const invalidForm = await formByRecord(page, kind, recordId);
  const invalidField = invalidForm.locator('input[name="' + field + '"]');
  assert(await invalidField.getAttribute('aria-invalid') === 'true', 'El campo inválido de ' + kind + ' no quedó marcado.');
  assert(await invalidField.evaluate(element => element === document.activeElement), 'El foco no volvió al campo inválido de ' + kind + '.');
  assert(await invalidForm.locator('[role="alert"]').count() === 1, 'El error accesible de ' + kind + ' no se renderizó exactamente una vez.');
  const expectedMessage = kind === 'occupant' ? 'El nombre del ocupante es obligatorio.' : 'El nombre y la especie de la mascota son obligatorios.';
  assert((await invalidForm.locator('[role="alert"]').innerText()).includes(expectedMessage), 'El error de ' + kind + ' no identifica el campo obligatorio.');
  assert(await page.locator('section[data-household-region="' + region + '"] details[data-household-action="edit"][open][data-household-record-id="' + recordId + '"]').count() === 1, 'El error no abrió la ficha inválida de ' + kind + '.');
  assert(await page.locator('section[data-household-region="' + region + '"] details[open]').count() === 1, 'La validación dejó más de un panel abierto de ' + kind + '.');
  const otherRegion = kind === 'occupant' ? 'pets' : 'occupants';
  assert(await page.locator('section[data-household-region="' + otherRegion + '"] details[open]').count() === 0, 'La validación abrió un panel de la región no afectada.');
  await capture(page, kind + '-validation-error-422', 'manager', viewport);
  return response.status();
}
async function createManagerContext(browser, credentials, viewport = viewportCases[0]) {
  const context = await makeContext(browser, viewport);
  const page = await context.newPage();
  watchPage(page, 'manager');
  await login(page, credentials, 'manager');
  return { context, page };
}
async function main() {
  assert(process.env.GITHUB_ACTIONS === 'true' && process.env.CI === 'true', 'Browser QA solo puede ejecutarse en el runner de GitHub Actions.');
  assert(process.env.GITHUB_EVENT_NAME === 'pull_request' && eventData.pull_request?.number === 55, 'Browser QA solo acepta el evento pull_request #55.');
  assert(eventData.pull_request?.head?.ref === 'impl/cand-03-household-human-first-20261023', 'El PR no usa la rama autorizada para CAND-03.');
  assert(eventData.pull_request?.head?.sha === prHeadSha, 'No se pudo atribuir la ejecución al HEAD del PR.');
  assert(new URL(baseUrl).origin === 'http://127.0.0.1:8787', 'El servidor Browser QA debe estar enlazado al loopback autorizado.');
  await fs.rm(artifactDir, { recursive: true, force: true });
  await fs.mkdir(artifactDir, { recursive: true });
  const fixture = JSON.parse(await fs.readFile(fixturePath, 'utf8'));
  browser = await chromium.launch({ headless: true });
  const mainCandidate = fixture.candidates.main;
  const longCandidate = fixture.candidates.long;
  const emptyCandidate = fixture.candidates.empty;
  const credentials = fixture.credentials;
  let managerDesktop = null;

  for (const viewport of viewportCases) {
    await runCheck('authenticated-household-' + viewport.name, async () => {
      const context = await makeContext(browser, viewport);
      const page = await context.newPage();
      watchPage(page, 'manager');
      await page.goto(baseUrl + '/login', { waitUntil: 'domcontentloaded' });
      await settle(page);
      assert(new URL(page.url()).pathname === '/login', 'La página real de inicio de sesión no cargó.');
      await capture(page, 'login-empty', 'guest', viewport);
      await login(page, credentials.manager, 'manager');
      const response = await openHousehold(page, mainCandidate.id);
      const metrics = await inspectHousehold(page, 'main', 'manager', viewport);
      if (viewport.mobile) {
        const titleGeometry = await page.locator('#candidate-household-title').evaluate(title => {
          const summary = title.closest('[data-household-region="summary"]');
          if (!summary) return null;
          const titleRect = title.getBoundingClientRect();
          const summaryRect = summary.getBoundingClientRect();
          const style = window.getComputedStyle(title);
          const fontSize = Number.parseFloat(style.fontSize) || 0;
          const lineHeight = Number.parseFloat(style.lineHeight) || fontSize * 1.2;
          return {
            titleWidth: titleRect.width,
            summaryWidth: summaryRect.width,
            titleHeight: titleRect.height,
            maxTitleHeight: lineHeight * 3,
          };
        });
        assert(titleGeometry && titleGeometry.titleWidth >= titleGeometry.summaryWidth * 0.6, 'El título del resumen no tiene ancho suficiente en móvil.');
        assert(titleGeometry.titleHeight <= titleGeometry.maxTitleHeight, 'El título del resumen se fragmenta en más de tres líneas en móvil.');
      }
      assert(await page.getByText('Camilo Demo G5', { exact: true }).count() > 0, 'No aparece la ficha sintética principal.');
      assert(await page.getByText('Ver versión en Documentos', { exact: true }).count() === 1, 'No aparece el manual JSON autorizado.');
      assert(await page.locator('details[data-household-action="create"]').count() === 2, 'El perfil manager no ve una acción de creación por región.');
      for (const [kind, label] of [['occupant', 'Agregar ocupante'], ['pet', 'Agregar mascota']]) {
        const action = page.locator('details[data-household-action="create"][data-household-kind="' + kind + '"] > summary');
        assert(await action.isVisible(), 'La acción de gestión no está visible: ' + label);
        assert((await action.innerText()).trim() === label, 'La acción directa no tiene el nombre visible esperado: ' + label);
        const actionBox = await action.boundingBox();
        assert(actionBox && actionBox.height >= 44, 'La acción de gestión no tiene un área táctil de 44 px: ' + label);
        if (viewport.mobile) {
          const region = kind === 'occupant' ? 'occupants' : 'pets';
          const regionBox = await page.locator('section[data-household-region="' + region + '"]').boundingBox();
          assert(regionBox && actionBox.width >= regionBox.width - 36, 'La acción móvil no ocupa el ancho disponible: ' + label);
        }
      }
      assert(await page.locator('details[data-household-action="edit"]').count() >= 2, 'Las fichas reales no muestran su editor individual.');
      const desktopTabs = page.locator('nav[aria-label="Secciones del candidato"]');
      const responsiveMenu = page.locator('details.candidate-section-menu');
      assert(await desktopTabs.isVisible() || await responsiveMenu.isVisible(), 'No hay navegación de sección disponible en este viewport.');
      await capture(page, 'household-main', 'manager', viewport);
      let actionForm = await openCreateForm(page, 'occupant', viewport.mobile);
      await capture(page, 'occupant-create-open', 'manager', viewport);
      await actionForm.locator('xpath=ancestor::details[1]/summary').click();
      actionForm = await openCreateForm(page, 'pet', viewport.mobile);
      await capture(page, 'pet-create-open', 'manager', viewport);
      await actionForm.locator('xpath=ancestor::details[1]/summary').click();
      actionForm = await openRecordEditor(page, 'occupant', mainCandidate.occupant_id, viewport.mobile);
      await capture(page, 'occupant-edit-open', 'manager', viewport);
      await actionForm.locator('xpath=ancestor::details[1]/summary').click();
      actionForm = await openRecordEditor(page, 'pet', mainCandidate.pet_id, viewport.mobile);
      await capture(page, 'pet-edit-open', 'manager', viewport);
      await actionForm.locator('xpath=ancestor::details[1]/summary').click();
      if (viewport.mobile) {
        const menu = page.locator('details.candidate-section-menu');
        assert(await menu.isVisible(), 'La navegación móvil no está visible.');
        const menuSummary = await menu.locator('summary').boundingBox();
        assert(menuSummary, 'El disparador de navegación móvil no tiene caja visible.');
        await page.touchscreen.tap(menuSummary.x + menuSummary.width / 2, menuSummary.y + menuSummary.height / 2);
        assert(await menu.locator('nav a').count() >= 6, 'El menú móvil no muestra las secciones disponibles.');
        await capture(page, 'mobile-navigation-open', 'manager', viewport);
        await menu.getByRole('link', { name: /Información laboral/ }).click();
        assert(await page.locator('[data-candidate-section-body="work"]').count() === 1, 'La navegación móvil no abrió Información laboral.');
        await page.goBack();
        await settle(page);
        assert(await page.locator('[data-candidate-section-body="household"]').count() === 1, 'El botón Atrás no restauró la sección de hogar.');
      }
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      const bottomState = await page.evaluate(() => {
        const region = document.querySelector('[data-household-region="next-steps"]');
        if (!region) return null;
        const rect = region.getBoundingClientRect();
        const sample = document.elementFromPoint(
          Math.min(window.innerWidth - 1, Math.max(0, rect.left + Math.min(20, rect.width / 2))),
          Math.min(window.innerHeight - 1, Math.max(0, rect.top + Math.min(20, rect.height / 2)))
        );
        return {
          bottom_visible: rect.bottom <= window.innerHeight + 1,
          unobscured: Boolean(sample && (sample === region || region.contains(sample))),
          scroll_y: window.scrollY
        };
      });
      assert(bottomState && bottomState.bottom_visible && bottomState.unobscured, 'La última región queda oculta al final del scroll.');
      await capture(page, 'scroll-final', 'manager', viewport);
      let keyboardFocus = null;
      await page.evaluate(() => document.activeElement?.blur());
      await page.keyboard.press('Tab');
      keyboardFocus = await page.evaluate(() => {
        const element = document.activeElement;
        const style = element ? getComputedStyle(element) : null;
        const rect = element?.getBoundingClientRect();
        return {
          tag: element?.tagName || null,
          visible: Boolean(rect && rect.width > 0 && rect.height > 0),
          outline: style?.outlineStyle || null,
          outline_width: style?.outlineWidth || null
        };
      });
      assert(keyboardFocus.visible && keyboardFocus.outline !== 'none' && keyboardFocus.outline_width !== '0px', 'El primer control de teclado no muestra un foco visible.');
      await capture(page, 'keyboard-focus-visible', 'manager', viewport);
      if (viewport.name === 'desktop-1448x1086') {
        managerDesktop = { context, page };
      } else {
        await context.close();
      }
      return { http_status: response.status(), metrics, bottom_state: bottomState, keyboard_focus: keyboardFocus, authenticated_session: true };
    });
  }

  await runCheck('invalid-login-real-form', async () => {
    const context = await makeContext(browser, viewportCases[0]);
    const page = await context.newPage();
    watchPage(page, 'guest');
    await page.goto(baseUrl + '/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#auth_identifier').fill('invalid-g5@example.test');
    await page.locator('#auth_password').fill('synthetic-invalid-password');
    const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/login');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    const response = await responsePromise;
    await settle(page);
    assert(response.status() === 422, 'El acceso inválido no devolvió el estado esperado.');
    assert(await page.getByText('No fue posible iniciar sesión con esas credenciales.', { exact: true }).count() === 1, 'No aparece el error genérico de acceso.');
    await capture(page, 'login-error', 'guest', viewportCases[0]);
    await context.close();
    return { http_status: response.status(), generic_error_only: true };
  });

  await runCheck('guest-tenant-and-role-permissions', async () => {
    const roleOutcomes = [];
    const guest = await browser.newContext({ viewport: { width: 1448, height: 1086 } });
    const guestResponse = await guest.request.get(baseUrl + '/admin/candidates/' + mainCandidate.id + '?section=household', { maxRedirects: 0 });
    assert(guestResponse.status() === 302, 'La ruta privada no redirigió al invitado.');
    assert((guestResponse.headers().location || '').endsWith('/login'), 'El invitado no fue dirigido al inicio de sesión.');
    const tokenPath = '/apply/continue/' + 'a'.repeat(64);
    const invalidTokenResponse = await guest.request.get(baseUrl + tokenPath, { maxRedirects: 0 });
    assert(invalidTokenResponse.status() === 404, 'El token de continuación inválido no falló en cerrado.');
    await guest.close();

    for (const role of ['viewer', 'viewer_property', 'viewer_documents', 'viewer_all', 'no_access', 'property_documents_only', 'tenant']) {
      const context = await makeContext(browser, viewportCases[0]);
      const page = await context.newPage();
      watchPage(page, role);
      await login(page, credentials[role], role);
      const response = await page.goto(baseUrl + '/admin/candidates/' + mainCandidate.id + '?section=household', { waitUntil: 'domcontentloaded' });
      await settle(page);
      await prepareImages(page);
      if (role === 'no_access' || role === 'property_documents_only' || role === 'tenant') {
        assert(response && response.status() === 403, 'El perfil sin permiso de candidatos no obtuvo 403: ' + role);
      } else {
        assert(response && response.status() === 200, 'El perfil con candidates.view no obtuvo 200: ' + role);
        assert(await page.locator('details.candidate-household-manager').count() === 0, 'Un perfil de solo lectura ve controles de gestión.');
        assert(await page.locator('section[data-household-region="occupants"] form, section[data-household-region="pets"] form').count() === 0, 'El perfil de solo lectura ve formularios de mutación.');
        const propertyAddress = await page.getByText('Calle Sintética 100, Bogotá', { exact: true }).count();
        assert(propertyAddress === (role === 'viewer_property' || role === 'viewer_all' ? 1 : 0), 'La dirección de propiedad no coincide con el permiso del perfil: ' + role);
        const manualAllowed = role === 'viewer_all';
        if (manualAllowed) {
          assert(await page.getByText('Ver versión en Documentos', { exact: true }).count() === 1, 'El perfil autorizado no ve el manual.');
          await capture(page, 'role-full-viewer-manual-json', role, viewportCases[0]);
        } else {
          assert(await page.getByText('La información del manual no está disponible para este perfil.', { exact: true }).count() === 1, 'La ACL del manual no oculta la información: ' + role);
          assert(await page.getByRole('link', { name: /Ver versión en Documentos/ }).count() === 0, 'La ACL expone el enlace al manual: ' + role);
        }
        if (role === 'viewer') await capture(page, 'household-viewer-readonly', role, viewportCases[0]);
      }
      if (role === 'viewer') {
        const accountResponse = await page.goto(baseUrl + '/account', { waitUntil: 'domcontentloaded' });
        await settle(page);
        assert(accountResponse && accountResponse.status() === 200, 'El viewer no pudo abrir su formulario real de cuenta para obtener CSRF.');
        const logoutForm = page.locator('form[action="/logout"]');
        assert(await logoutForm.count() === 1, 'El viewer no tiene el formulario real de cierre de sesión.');
        const token = await logoutForm.locator('input[name="csrf_token"]').inputValue();
        assert(token.length === 64, 'El formulario real de cuenta no entregó el token CSRF de la sesión viewer.');
        const mutation = await context.request.post(baseUrl + '/admin/candidates/' + mainCandidate.id + '/household/occupant', {
          maxRedirects: 0,
          form: { csrf_token: token, full_name: 'No debe guardarse', relationship: 'QA', action: 'save' }
        });
        assert(mutation.status() === 403, 'El viewer pudo modificar una ficha.');
      }
      if (['viewer', 'viewer_documents', 'viewer_all', 'property_documents_only'].includes(role)) {
        const docsResponse = await context.request.get(baseUrl + '/admin/documents?scope=property&id=' + mainCandidate.property_id, { maxRedirects: 0 });
        assert(docsResponse.status() === (role === 'viewer' ? 403 : 200), 'La autorización del módulo Documentos no coincide con el rol.');
      }
      roleOutcomes.push({ role, candidate_status: response?.status() ?? null, manual_visible: role === 'viewer_all' });
      note('role-acl-' + role, role, response?.status() === 200 ? 'read-only-allowed' : 'denied', { manual_visible: role === 'viewer_all' });
      await context.close();
    }
    for (const viewport of viewportCases.slice(1)) {
      const context = await makeContext(browser, viewport);
      const page = await context.newPage();
      watchPage(page, 'viewer');
      await login(page, credentials.viewer, 'viewer');
      const response = await page.goto(baseUrl + '/admin/candidates/' + mainCandidate.id + '?section=household', { waitUntil: 'domcontentloaded' });
      await settle(page);
      assert(response && response.status() === 200, 'El perfil viewer no obtuvo acceso de lectura en ' + viewport.name + '.');
      assert(await page.locator('details.candidate-household-manager, section[data-household-region="occupants"] form, section[data-household-region="pets"] form').count() === 0, 'El perfil viewer ve controles de mutación en ' + viewport.name + '.');
      await capture(page, 'household-viewer-readonly', 'viewer', viewport);
      await context.close();
    }
    const guestContext = await makeContext(browser, viewportCases[0]);
    const guestPage = await guestContext.newPage();
    watchPage(guestPage, 'guest');
    await login(guestPage, credentials.manager, 'manager');
    await guestPage.goto(baseUrl + '/account', { waitUntil: 'domcontentloaded' });
    const logoutForm = guestPage.locator('form[action="/logout"]');
    assert(await logoutForm.count() === 1, 'No se encontró el formulario real de cierre de sesión.');
    const logoutPromise = guestPage.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/logout');
    await logoutForm.locator('button[type="submit"]').click();
    const logoutResponse = await logoutPromise;
    await settle(guestPage);
    assert(logoutResponse.status() === 303 && new URL(guestPage.url()).pathname === '/login', 'El cierre real de sesión no invalidó la sesión.');
    const afterLogout = await guestContext.request.get(baseUrl + '/admin/candidates/' + mainCandidate.id, { maxRedirects: 0 });
    assert(afterLogout.status() === 302, 'La sesión cerrada conserva acceso privado.');
    await guestContext.close();
    return { guest_redirect: 302, invalid_continuation: 404, role_matrix_checked: roleOutcomes, logout: 303 };
  });

  await runCheck('candidate-counts-long-empty-and-escaped-content', async () => {
    assert(managerDesktop, 'La sesión manager desktop no quedó disponible.');
    const { page, context } = managerDesktop;
    for (const [key, expected] of [['long', '3 registradas'], ['empty', '0 registradas']]) {
      const candidate = fixture.candidates[key];
      let metrics = null;
      for (const viewport of viewportCases) {
        let targetPage = page;
        let extraContext = null;
        if (viewport !== viewportCases[0]) {
          extraContext = await makeContext(browser, viewport);
          targetPage = await extraContext.newPage();
          watchPage(targetPage, 'manager');
          await login(targetPage, credentials.manager, 'manager');
        }
        await openHousehold(targetPage, candidate.id);
        const currentMetrics = await inspectHousehold(targetPage, key, 'manager', viewport);
        metrics ||= currentMetrics;
        if (key === 'long') {
          assert(await targetPage.getByText('Ocupante Demo G5 3', { exact: true }).count() === 1, 'No se renderizaron los N ocupantes.');
          assert(await targetPage.getByText('Mascota Demo G5 3', { exact: true }).count() === 1, 'No se renderizaron las N mascotas.');
          assert(await targetPage.locator('script').filter({ hasText: '__g5_injected' }).count() === 0, 'Las observaciones sintéticas escaparon HTML.');
          assert(await targetPage.evaluate(() => window.__g5_injected === undefined), 'El texto sintético se ejecutó como script.');
        } else {
          assert(await targetPage.getByText('No hay fichas de otras personas registradas.', { exact: true }).count() === 1, 'No se representa el estado de 0 ocupantes.');
          assert(await targetPage.getByText('No hay fichas de mascotas registradas.', { exact: true }).count() === 1, 'No se representa el estado de 0 mascotas.');
          assert(await targetPage.getByText('Manual de convivencia no registrado.', { exact: true }).count() === 1, 'No se representa el manual ausente.');
          assert(await targetPage.getByText('No confirmado', { exact: true }).count() === 1, 'No se representa la respuesta histórica ambigua.');
        }
        await capture(targetPage, 'household-' + key, 'manager', viewport);
        if (extraContext) await extraContext.close();
      }
      note('fixture-state-' + key, 'manager', 'verified', { layout_height: metrics.document_height });
    }
    await openHousehold(page, mainCandidate.id);
    return { zero_one_many: true, long_text_escaped: true, legacy_ambiguous: true };
  });

  await runCheck('photo-private-headers-replacement-absence-and-deletion', async () => {
    assert(managerDesktop, 'La sesión manager desktop no quedó disponible.');
    const { context, page } = managerDesktop;
    const mainPetId = fixture.candidates.main.pet_id;
    const photoUrl = baseUrl + '/admin/candidates/' + mainCandidate.id + '/household/pet/' + mainPetId + '/photo';
    const original = await context.request.get(photoUrl);
    assert(original.status() === 200, 'La foto privada sintética no se sirve autenticada.');
    assert(original.headers()['cache-control'] === 'private, no-store', 'La foto no tiene Cache-Control privado.');
    assert(original.headers()['x-content-type-options'] === 'nosniff', 'La foto no tiene nosniff.');
    const originalBytes = await original.body();
    assert(originalBytes.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])), 'La foto no es un PNG válido.');
    await openHousehold(page, mainCandidate.id);
    assert(await page.locator('img[alt="Fotografía de Max Demo G5"]').count() === 1, 'La imagen del registro no aparece en la página real.');
    await capture(page, 'photo-private-original', 'manager', viewportCases[0]);

    let editForm = await openRecordEditor(page, 'pet', mainPetId);
    await editForm.locator('input[name="name"]').fill('Max Demo G5 Reemplazado');
    await editForm.locator('input[name="photo"]').setInputFiles(fixture.uploads.replacement_photo);
    await editForm.locator('input[name="photo_consent"]').check();
    await submitAndWait(page, editForm, 'Guardar cambios', '/household/pet');
    assert(await page.getByText('Max Demo G5 Reemplazado', { exact: true }).count() === 1, 'El reemplazo del nombre no persistió.');
    const replacement = await context.request.get(photoUrl);
    assert(replacement.status() === 200, 'La foto reemplazada no se sirve.');
    const replacementBytes = await replacement.body();
    assert(sha256(originalBytes) !== sha256(replacementBytes), 'La sustitución de foto no cambió el archivo persistido.');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await settle(page);
    assert(await page.getByText('Max Demo G5 Reemplazado', { exact: true }).count() === 1, 'El cambio de mascota no sobrevivió a la recarga.');
    await capture(page, 'photo-replaced', 'manager', viewportCases[0]);

    editForm = await openRecordEditor(page, 'pet', mainPetId);
    await editForm.locator('input[name="remove_photo"]').check();
    await submitAndWait(page, editForm, 'Guardar cambios', '/household/pet');
    assert(await page.locator('img[alt="Fotografía de Max Demo G5 Reemplazado"]').count() === 0, 'La foto retirada continúa visible.');
    const removed = await context.request.get(photoUrl);
    assert(removed.status() === 404, 'La ruta de la foto retirada no devuelve 404.');
    await capture(page, 'photo-removed', 'manager', viewportCases[0]);

    const foreignPhoto = await context.request.get(
      baseUrl + '/admin/candidates/' + fixture.candidates.foreign.id + '/household/pet/' + mainPetId + '/photo',
      { maxRedirects: 0 }
    );
    assert(foreignPhoto.status() === 404, 'La ruta cruzada de foto revela o sirve otro candidato.');
    const unauthPhotoContext = await browser.newContext();
    const unauthPhoto = await unauthPhotoContext.request.get(photoUrl, { maxRedirects: 0 });
    assert(unauthPhoto.status() === 302, 'La foto privada se entrega sin autenticar.');
    await unauthPhotoContext.close();
    return {
      private_photo_http: original.status(),
      replacement_differs_by_sha256: true,
      removed_photo_http: removed.status(),
      cross_candidate_http: foreignPhoto.status(),
      unauthenticated_http: unauthPhoto.status()
    };
  });

  await runCheck('real-household-create-edit-delete-and-csrf', async () => {
    assert(managerDesktop, 'La sesión manager desktop no quedó disponible.');
    const { context, page } = managerDesktop;
    await openHousehold(page, mainCandidate.id);
    const createOccupant = await openCreateForm(page, 'occupant');
    const occupantsRegion = page.locator('section[data-household-region="occupants"]');
    const createOccupantButton = occupantsRegion.getByRole('button', { name: 'Crear ficha', exact: true });
    assert(await createOccupantButton.count() === 1, 'No se encontró el botón real para crear un ocupante en su región.');
    assert(await createOccupant.evaluate(form => form.classList.contains('candidate-household-edit')), 'El formulario de creación de ocupante no pertenece al owner esperado.');
    assert((await createOccupant.getAttribute('action') || '').endsWith('/household/occupant'), 'El formulario de creación no apunta a la ruta de ocupantes.');
    await createOccupant.locator('input[name="full_name"]').fill('Ocupante G5 Creado');
    await createOccupant.locator('input[name="relationship"]').fill('Familiar');
    await createOccupant.locator('input[name="age_years"]').fill('29');
    await createOccupant.locator('input[name="occupation_or_activity"]').fill('Actividad sintética');
    await createOccupant.locator('textarea[name="notes"]').fill('Registro ficticio creado desde el navegador.');
    await submitAndWait(page, createOccupant, 'Crear ficha', '/household/occupant');
    assert(await page.getByText('Ocupante G5 Creado', { exact: true }).count() === 1, 'No se creó el ocupante desde el formulario real.');
    await capture(page, 'occupant-created', 'manager', viewportCases[0]);
    const createdOccupantForm = page.locator('section[data-household-region="occupants"] form.candidate-household-edit:has(input[name="full_name"][value="Ocupante G5 Creado"])').first();
    const occupantId = await createdOccupantForm.locator('input[name="record_id"]').inputValue();
    assert(/^\d+$/.test(occupantId), 'No se encontró el ID persistido del ocupante sintético.');
    await openRecordEditor(page, 'occupant', mainCandidate.occupant_id);
    const occupantForm = await openRecordEditor(page, 'occupant', occupantId);
    await occupantForm.locator('input[name="full_name"]').fill('Ocupante G5 Editado');
    await submitAndWait(page, occupantForm, 'Guardar cambios', '/household/occupant');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await settle(page);
    assert(await page.getByText('Ocupante G5 Editado', { exact: true }).count() === 1, 'La edición del ocupante no persistió tras recargar.');
    await openRecordEditor(page, 'occupant', occupantId);
    const deleteOccupant = page.locator('section[data-household-region="occupants"] form:not(.candidate-household-edit):has(input[name="record_id"][value="' + occupantId + '"])');
    page.once('dialog', dialog => dialog.accept());
    const deleteOccupantPromise = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/household/occupant'));
    await deleteOccupant.getByRole('button', { name: 'Retirar ficha', exact: true }).click();
    const deleteOccupantResponse = await deleteOccupantPromise;
    await settle(page);
    assert(deleteOccupantResponse.status() === 303, 'No se eliminó el ocupante.');
    assert(await page.getByText('Ocupante G5 Editado', { exact: true }).count() === 0, 'El ocupante eliminado continúa visible.');

    await openHousehold(page, mainCandidate.id);
    const createPet = await openCreateForm(page, 'pet');
    const petsRegion = page.locator('section[data-household-region="pets"]');
    const createPetButton = petsRegion.getByRole('button', { name: 'Crear ficha', exact: true });
    assert(await createPetButton.count() === 1, 'No se encontró el botón real para crear una mascota en su región.');
    assert(await createPet.evaluate(form => form.classList.contains('candidate-household-edit')), 'El formulario de creación de mascota no pertenece al owner esperado.');
    assert((await createPet.getAttribute('action') || '').endsWith('/household/pet'), 'El formulario de creación no apunta a la ruta de mascotas.');
    await createPet.locator('input[name="name"]').fill('Mascota G5 Creada');
    await createPet.locator('input[name="species"]').fill('Gato');
    await createPet.locator('input[name="breed"]').fill('Sintética');
    await createPet.locator('input[name="size_category"]').fill('small');
    await createPet.locator('input[name="age_years"]').fill('2');
    await createPet.locator('textarea[name="notes"]').fill('Mascota de prueba ficticia.');
    await submitAndWait(page, createPet, 'Crear ficha', '/household/pet');
    assert(await page.getByText('Mascota G5 Creada', { exact: true }).count() === 1, 'No se creó la mascota desde el formulario real.');
    const createdPetForm = page.locator('section[data-household-region="pets"] form.candidate-household-edit:has(input[name="name"][value="Mascota G5 Creada"])').first();
    const petId = await createdPetForm.locator('input[name="record_id"]').inputValue();
    assert(/^\d+$/.test(petId), 'No se encontró el ID persistido de la mascota sintética.');
    const openCreatedPetForm = await openRecordEditor(page, 'pet', petId);
    await openCreatedPetForm.locator('input[name="name"]').fill('Mascota G5 Editada');
    await openCreatedPetForm.locator('input[name="photo"]').setInputFiles(fixture.uploads.added_photo);
    await openCreatedPetForm.locator('input[name="photo_consent"]').check();
    await submitAndWait(page, openCreatedPetForm, 'Guardar cambios', '/household/pet');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await settle(page);
    assert(await page.getByText('Mascota G5 Editada', { exact: true }).count() === 1, 'La edición de mascota no persistió tras recargar.');
    assert(await page.locator('img[alt="Fotografía de Mascota G5 Editada"]').count() === 1, 'La carga de la foto nueva no se mostró.');
    await capture(page, 'pet-created-edited-with-photo', 'manager', viewportCases[0]);
    await openRecordEditor(page, 'pet', petId);
    const deletePet = page.locator('section[data-household-region="pets"] form:not(.candidate-household-edit):has(input[name="record_id"][value="' + petId + '"])');
    page.once('dialog', dialog => dialog.accept());
    const deletePetPromise = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/household/pet'));
    await deletePet.getByRole('button', { name: 'Retirar ficha', exact: true }).click();
    const deletePetResponse = await deletePetPromise;
    await settle(page);
    assert(deletePetResponse.status() === 303, 'No se eliminó la mascota.');
    assert(await page.getByText('Mascota G5 Editada', { exact: true }).count() === 0, 'La mascota eliminada continúa visible.');

    await openHousehold(page, mainCandidate.id);
    const token = await csrfToken(page);
    const invalidCsrf = await context.request.post(baseUrl + '/admin/candidates/' + mainCandidate.id + '/household/occupant', {
      maxRedirects: 0,
      form: { csrf_token: 'invalid-csrf-token', action: 'save', full_name: 'No debe persistir', relationship: 'QA' }
    });
    assert(invalidCsrf.status() === 403, 'La mutación sin CSRF válido no devolvió 403.');
    assert(token.length === 64, 'El formulario real no entregó el token CSRF de la sesión.');
    const invalidOccupant = await submitInvalidEditAndCapture(page, 'occupant', fixture.candidates.main.occupant_id, viewportCases[0]);
    const invalidPet = await submitInvalidEditAndCapture(page, 'pet', fixture.candidates.main.pet_id, viewportCases[0]);
    for (const viewport of viewportCases.slice(1)) {
      const validationContext = await makeContext(browser, viewport);
      const validationPage = await validationContext.newPage();
      watchPage(validationPage, 'manager');
      await login(validationPage, credentials.manager, 'manager');
      await openHousehold(validationPage, mainCandidate.id);
      await submitInvalidEditAndCapture(validationPage, 'occupant', fixture.candidates.main.occupant_id, viewport);
      await submitInvalidEditAndCapture(validationPage, 'pet', fixture.candidates.main.pet_id, viewport);
      await validationContext.close();
    }
    return { occupant: 'create-edit-reload-delete', pet: 'create-edit-reload-delete-photo', invalid_csrf: invalidCsrf.status(), invalid_occupant: invalidOccupant, invalid_pet: invalidPet, validation_viewports: viewportCases.map(viewport => viewport.name) };
  });

  await runCheck('household-photo-consent-validation', async () => {
    assert(managerDesktop, 'La sesión manager desktop no quedó disponible.');
    const { page } = managerDesktop;
    await openHousehold(page, mainCandidate.id);
    const form = await openCreateForm(page, 'occupant');
    const region = page.locator('section[data-household-region="occupants"]');
    await form.locator('input[name="full_name"]').fill('Ocupante sin consentimiento QA');
    await form.locator('input[name="relationship"]').fill('Prueba');
    await form.locator('input[name="photo"]').setInputFiles(fixture.uploads.added_photo);
    assert(!(await form.locator('input[name="photo_consent"]').isChecked()), 'La prueba debe enviar la foto sin consentimiento.');
    const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/household/occupant'));
    await form.getByRole('button', { name: 'Crear ficha', exact: true }).click();
    const response = await responsePromise;
    await settle(page);
    assert(response.status() === 422, 'La foto sin consentimiento no devolvió HTTP 422.');
    assert((response.headers()['content-type'] || '').includes('text/html'), 'La falta de consentimiento no volvió a mostrar la ficha completa.');
    const invalidForm = page.locator('form[data-household-form-kind="occupant"][data-household-form-id="new"]');
    const consent = invalidForm.locator('input[name="photo_consent"]');
    assert(await consent.getAttribute('aria-invalid') === 'true', 'El consentimiento faltante no quedó marcado.');
    assert(await consent.evaluate(element => element === document.activeElement), 'El foco no volvió al consentimiento faltante.');
    assert(!(await consent.isChecked()), 'La pantalla no debe marcar consentimiento sin confirmación del usuario.');
    assert(await invalidForm.locator('[role="alert"]').count() === 1, 'El aviso de consentimiento no se renderizó exactamente una vez.');
    await capture(page, 'photo-consent-validation-422', 'manager', viewportCases[0]);
    return { status: response.status(), field: 'photo_consent', upload_retained: false };
  });

  await runCheck('household-photo-file-validation', async () => {
    assert(managerDesktop, 'La sesión manager desktop no quedó disponible.');
    const { page } = managerDesktop;
    await openHousehold(page, mainCandidate.id);
    const form = await openCreateForm(page, 'pet');
    const region = page.locator('section[data-household-region="pets"]');
    await form.locator('input[name="name"]').fill('Mascota archivo inválido QA');
    await form.locator('input[name="species"]').fill('Gato');
    await form.locator('input[name="photo"]').setInputFiles({
      name: 'DO_NOT_RENDER_INVALID.png',
      mimeType: 'image/png',
      buffer: Buffer.from('not an image')
    });
    await form.locator('input[name="photo_consent"]').check();
    const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname.endsWith('/household/pet'));
    await form.getByRole('button', { name: 'Crear ficha', exact: true }).click();
    const response = await responsePromise;
    await settle(page);
    assert(response.status() === 422, 'La foto inválida no devolvió HTTP 422.');
    assert((response.headers()['content-type'] || '').includes('text/html'), 'El error de foto no volvió a mostrar la ficha completa.');
    const invalidForm = page.locator('form[data-household-form-kind="pet"][data-household-form-id="new"]');
    const photo = invalidForm.locator('input[name="photo"]');
    const consent = invalidForm.locator('input[name="photo_consent"]');
    assert(await photo.getAttribute('aria-invalid') === 'true', 'El campo de foto inválida no quedó marcado.');
    assert(await photo.evaluate(element => element === document.activeElement), 'El foco no volvió al campo de foto inválida.');
    assert(await consent.isChecked(), 'La confirmación seleccionada se perdió al responder con error.');
    assert(await invalidForm.locator('[role="alert"]').count() === 1, 'El aviso de foto inválida no se renderizó exactamente una vez.');
    assert(!(await page.locator('body').innerText()).includes('DO_NOT_RENDER_INVALID.png'), 'La respuesta filtró el nombre del archivo.');
    await capture(page, 'invalid-photo-validation-422', 'manager', viewportCases[0]);
    return { status: response.status(), field: 'photo', upload_retained: false, consent_retained: true };
  });

  await runCheck('manual-json-pdf-and-private-document-acl', async () => {
    assert(managerDesktop, 'La sesión manager desktop no quedó disponible.');
    const { context, page } = managerDesktop;
    await openHousehold(page, mainCandidate.id);
    const jsonLink = page.getByRole('link', { name: 'Ver versión en Documentos', exact: true });
    const jsonHref = await jsonLink.getAttribute('href');
    assert(jsonHref && jsonHref.startsWith('/admin/documents?scope=property&id='), 'El enlace del manual JSON no apunta al módulo privado.');
    await jsonLink.click();
    await settle(page);
    assert(await page.getByText('Manual sintético G5 JSON', { exact: true }).count() === 1, 'El manual JSON no se muestra en Documentos.');
    assert(await page.locator('pre').evaluate(element => element.innerText.includes('Contenido de convivencia sintético.')), 'El contenido JSON vigente no aparece.');
    await capture(page, 'manual-json-documents', 'manager', viewportCases[0]);

    await openHousehold(page, longCandidate.id);
    assert(await page.getByText('Manual sintético G5 PDF', { exact: true }).count() === 1, 'El manual PDF no está asociado al candidato sintético.');
    await capture(page, 'manual-pdf-household', 'manager', viewportCases[0]);
    const pdfLink = page.getByRole('link', { name: 'Ver versión en Documentos', exact: true });
    await pdfLink.click();
    await settle(page);
    assert(await page.getByText('Manual sintético G5 PDF', { exact: true }).count() === 1, 'El manual PDF no abre su vista real de Documentos.');
    const downloadLink = page.getByRole('link', { name: 'Descargar archivo', exact: true });
    assert(await downloadLink.count() === 1, 'No aparece la descarga autenticada del PDF.');
    const fileHref = await downloadLink.getAttribute('href');
    assert(fileHref, 'La ruta del PDF no está presente.');
    const response = await context.request.get(new URL(fileHref, baseUrl).toString());
    assert(response.status() === 200, 'La descarga autenticada del PDF no devuelve HTTP 200.');
    assert(response.headers()['content-type']?.includes('application/pdf'), 'La respuesta privada no declara application/pdf.');
    assert(response.headers()['cache-control'] === 'private, no-store', 'El PDF carece de control privado de caché.');
    assert(response.headers()['x-content-type-options'] === 'nosniff', 'El PDF carece de nosniff.');
    const pdfBytes = await response.body();
    assert(pdfBytes.subarray(0, 5).toString('ascii') === '%PDF-', 'La respuesta no contiene un PDF válido.');
    const documentDir = path.join(artifactDir, 'documents');
    await fs.mkdir(documentDir, { recursive: true });
    const pdfName = 'manual-synthetic-g5.pdf';
    await fs.writeFile(path.join(documentDir, pdfName), pdfBytes);
    manifest.documents.push({ file: 'documents/' + pdfName, bytes: pdfBytes.length, sha256: sha256(pdfBytes), content_type: response.headers()['content-type'] });
    const downloadPromise = page.waitForEvent('download');
    await downloadLink.click();
    const download = await downloadPromise;
    const downloadedPath = await download.path();
    assert(downloadedPath !== null, 'Playwright no produjo el archivo de descarga del navegador.');
    const downloadedBytes = await fs.readFile(downloadedPath);
    const downloadedSha256 = sha256(downloadedBytes);
    const expectedPdfSha256 = sha256(pdfBytes);
    assert(downloadedSha256 === expectedPdfSha256, 'La descarga del navegador difiere de la respuesta PDF verificada.');
    const downloadPathLabel = safePathLabel(download.url());
    assert(downloadObservations.some(item => item.phase === activeCheck && item.role === 'manager' && item.path === downloadPathLabel), 'El evento real de descarga no quedó registrado en los diagnósticos.');
    verifiedDownloads.push({
      phase: activeCheck,
      role: 'manager',
      path: downloadPathLabel,
      bytes: downloadedBytes.length,
      sha256: downloadedSha256,
      expected_sha256: expectedPdfSha256,
      hash_match: true
    });
    await capture(page, 'manual-pdf-document-download', 'manager', viewportCases[0]);

    await openHousehold(page, emptyCandidate.id);
    assert(await page.getByText('Manual de convivencia no registrado.', { exact: true }).count() === 1, 'El estado de manual ausente no se muestra.');
    await capture(page, 'manual-absent', 'manager', viewportCases[0]);
    const missingAccess = await context.request.get(baseUrl + '/admin/documents?scope=property&id=' + emptyCandidate.property_id, { maxRedirects: 0 });
    assert(missingAccess.status() === 200, 'El manager no puede abrir Documentos para el estado ausente.');
    return {
      json_manual: 'verified in real Documents module',
      pdf_manual: 'HTTP 200 private PDF; browser download hash matched',
      absent_manual: 'explicit empty state',
      pdf_sha256: sha256(pdfBytes)
    };
  });

  if (managerDesktop) {
    await managerDesktop.context.close().catch(() => {});
    managerDesktop = null;
  }
  const browserEventClassification = classifyExpectedBrowserEvents(mainCandidate.id);
  manifest.diagnostics = {
    console_errors: consoleErrors.length,
    expected_console_errors: browserEventClassification.expectedConsoleErrors.length,
    unexpected_console_errors: browserEventClassification.unexpectedConsoleErrors.length,
    console_events: consoleErrors,
    page_errors: pageErrors.length,
    page_error_events: pageErrors,
    failed_requests: failedRequests.length,
    expected_failed_requests: browserEventClassification.expectedFailedRequests.length,
    unexpected_failed_requests: browserEventClassification.unexpectedFailedRequests.length,
    failed_request_events: failedRequests,
    response_events: responseObservations,
    browser_downloads: downloadObservations,
    verified_downloads: verifiedDownloads
  };
  assert(manifest.diagnostics.unexpected_console_errors === 0, 'Chromium reportó errores inesperados en consola.');
  assert(manifest.diagnostics.page_errors === 0, 'Chromium reportó errores JavaScript.');
  assert(manifest.diagnostics.unexpected_failed_requests === 0, 'Chromium registró solicitudes fallidas inesperadas.');
  await browser.close();
}

async function writeArtifacts() {
  manifest.finished_at = new Date().toISOString();
  manifest.status = manifest.checks.some(item => item.status === 'FAIL')
    || manifest.diagnostics.unexpected_console_errors > 0
    || manifest.diagnostics.page_errors > 0
    || manifest.diagnostics.unexpected_failed_requests > 0
    ? 'FAIL'
    : 'PASS';
  manifest.rulings.QA_FUNCIONAL = manifest.status === 'PASS' ? 'PASS_AUTOMATED' : 'FAIL_AUTOMATED';
  await fs.mkdir(artifactDir, { recursive: true });
  const manifestBytes = Buffer.from(JSON.stringify(manifest, null, 2) + '\n');
  await fs.writeFile(path.join(artifactDir, 'manifest.json'), manifestBytes);
  await fs.writeFile(path.join(artifactDir, 'manifest.sha256'), sha256(manifestBytes) + '  manifest.json\n');
  await fs.writeFile(path.join(artifactDir, 'sanitized-trace.json'), JSON.stringify({
    synthetic_data_only: true,
    events: trace
  }, null, 2) + '\n');
  const screenshotRows = manifest.screenshots.map(item =>
    '<li><a href="' + item.file + '"><img loading="lazy" src="' + item.file + '" alt="' + item.role + ' ' + item.state + ' ' + item.viewport + ' ' + item.mode + '"><span>'
    + item.role + ' · ' + item.state + ' · ' + item.viewport + ' · ' + item.mode + ' (' + item.width + '×' + item.height + ')</span></a></li>'
  ).join('\n');
  await fs.writeFile(path.join(artifactDir, 'index.html'),
    '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>AlquilaPro CAND-03 G5 Browser QA</title>'
    + '<style>body{font:16px system-ui,sans-serif;margin:2rem;color:#24332e}h1{font-size:1.5rem}ul{list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem}li{border:1px solid #d6dfd9;border-radius:12px;overflow:hidden}img{display:block;width:100%;height:auto;background:#f3f5f4}span{display:block;padding:.7rem}a{color:inherit;text-decoration:none}</style>'
    + '<h1>AlquilaPro · CAND-03 · G5 Browser QA</h1><p>Estado: ' + manifest.status + ' · SHA PR: ' + prHeadSha + ' · SHA CI: ' + ciSha + '</p><p>Datos sintéticos; fidelidad visual y aceptación humana pendientes de revisión independiente.</p><ul>'
    + screenshotRows + '</ul><p><a href="manifest.json">Manifest JSON</a> · <a href="manifest.sha256">SHA-256</a> · <a href="sanitized-trace.json">Traza sanitizada</a></p></html>');
  async function collectFiles(directory, relative = '') {
    const entries = await fs.readdir(directory, { withFileTypes: true });
    const files = [];
    for (const entry of entries) {
      const childRelative = path.posix.join(relative, entry.name);
      const absolute = path.join(directory, entry.name);
      if (entry.isDirectory()) files.push(...await collectFiles(absolute, childRelative));
      else if (entry.isFile() && entry.name !== 'SHA256SUMS') files.push(childRelative);
    }
    return files;
  }
  const files = (await collectFiles(artifactDir)).sort();
  const sums = [];
  for (const file of files) {
    sums.push(sha256(await fs.readFile(path.join(artifactDir, file))) + '  ' + file);
  }
  await fs.writeFile(path.join(artifactDir, 'SHA256SUMS'), sums.join('\n') + '\n');
  process.stdout.write('CAND03 G5 Browser QA ' + manifest.status + '; checks=' + manifest.checks.filter(item => item.status === 'PASS').length + '/' + manifest.checks.length + '; screenshots=' + manifest.screenshots.length + '; PR_HEAD=' + prHeadSha + '; CI_SHA=' + ciSha + '\n');
}

try {
  await main();
} catch (error) {
  manifest.checks.push({ name: 'browser-harness-fatal', status: 'FAIL', error: safeError(error) });
  note('browser-harness-fatal', 'harness', 'FAIL', { error_fingerprint: sha256(Buffer.from(safeError(error))) });
  try { await browser?.close(); } catch {}
}
await writeArtifacts();
if (manifest.status !== 'PASS') process.exitCode = 1;
