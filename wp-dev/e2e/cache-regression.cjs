/**
 * Регрессия кэша страниц и восстановления REST nonce для RVN Compare.
 *
 * Использование (стенд main):
 *   NODE_PATH=/tmp/wp-tools/pw/node_modules node wp-dev/e2e/cache-regression.cjs http://127.0.0.1:8080 /tmp/wp-stand-main
 *
 * Проверки идут на настоящем WP Super Cache (файл кэша и HIT по заголовку),
 * а вход после обновления выполняется настоящим браузером через /wp-admin/.
 * WP-CLI здесь только готовит окружение/читает состояние, admin_init не вызывается вручную.
 * Скрипт выводит один JSON-объект и выходит с кодом 1 при провале хотя бы одного пункта.
 */
const { chromium, webkit, firefox } = require('playwright');
const ENGINES = { chromium, webkit, firefox };
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const [baseUrl, wpPath] = process.argv.slice(2);
const slug = 'rvn-compare-products-for-woocommerce';
const action = 'rvn_compare_refresh_nonce';
const tests = [];
const errors = [];
let stage = 'initialization';
const assert = (name, ok, detail = '') => tests.push({ name, ok: !!ok, detail: String(detail).slice(0, 1500) });

/** Запускает WP-CLI и возвращает стандартный вывод; предупреждения Woo остаются в stderr. */
function wp(...args) {
	return execFileSync('wp', [`--path=${wpPath}`, '--quiet', ...args], {
		encoding: 'utf8',
		stdio: ['ignore', 'pipe', 'pipe'],
		timeout: 60000
	}).trim();
}

/** Делает запрос гостя с Accept: text/html и, при необходимости, Android User-Agent. */
async function guestGet(url, mobile = false) {
	const headers = { Accept: 'text/html' };

	if (mobile) {
		headers['User-Agent'] = 'Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 Chrome/125.0.0.0 Mobile Safari/537.36';
	}

	const response = await fetch(url, { headers, cache: 'no-store' });
	return { headers: response.headers, body: await response.text(), status: response.status };
}

/** Определяет, отдался ли сохранённый файл WP Super Cache, а не новая страница PHP. */
function cacheHit(response) {
	return (response.headers.get('x-wp-super-cache') || '').includes('Served supercache file from PHP');
}

/** Проверяет, что на странице есть работоспособная разметка сравнения с новой версией ассетов. */
function currentMarkup(html, version) {
	return html.includes('data-rvn-compare-button')
		&& html.includes(`compare.js?ver=${version}`)
		&& html.includes(`compare.css?ver=${version}`);
}

/** Входит в реальную админку WordPress с помощью браузера. */
async function login(page) {
	await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
	await page.locator('#user_login').fill('admin');
	await page.locator('#user_pass').fill('admin');
	await Promise.all([
		page.waitForURL(/\/wp-admin\//, { timeout: 20000 }),
		page.locator('#wp-submit').click()
	]);
}

/** Наблюдает ответы двух REST-запросов и обновляющего ключ AJAX. */
function watchRecovery(page) {
	const observed = { invalid: [], restored: [], ajax: [], ajaxRequests: [] };

	page.on('response', async (response) => {
		const url = response.url();
		if (url.includes('admin-ajax.php') && url.includes(action)) {
			observed.ajaxRequests.push({ url, staleHeader: !!response.request().headers()['x-wp-nonce'] });
			let body = {};
			try { body = await response.json(); } catch { /* Ошибочный ответ останется пустым. */ }
			observed.ajax.push({ status: response.status(), cacheControl: response.headers()['cache-control'] || '', body });
		} else if (url.includes('/rvn-compare/v1/items')) {
			let body = {};
			try { body = await response.json(); } catch { /* Ошибочный ответ останется пустым. */ }
			(response.status() === 403 ? observed.invalid : observed.restored).push({ status: response.status(), code: body.code, result: body.status });
		}
	});

	return observed;
}

/** Ожидает состояние кнопки после двух HTTP-запросов при восстановлении ключа. */
async function waitForAdded(page) {
	await page.locator('[data-rvn-compare-button]').first().click();
	await page.waitForFunction(() => document.querySelector('[data-rvn-compare-button]')?.getAttribute('aria-pressed') === 'true', null, { timeout: 15000 });
}

(async () => {
	if (!baseUrl || !wpPath) throw new Error('Нужны URL и путь к установленному WordPress');

	const version = wp('plugin', 'get', slug, '--field=version');
	const previousVersion = '0.3.1';
	const shopUrl = new URL('/shop/', baseUrl).toString();
	const cacheFile = path.join(wpPath, 'wp-content/cache/supercache', new URL(baseUrl).hostname, 'shop', 'index.html');
	const ajaxUrl = new URL(`/wp-admin/admin-ajax.php?action=${action}`, baseUrl).toString();
	const browser = await ENGINES[process.env.NODE_BROWSER || "chromium"].launch();

	// Реальный WP Super Cache с PHP-выдачей файлов: установка и настройка без вызова admin_init вручную.
	try {
		wp('plugin', 'is-installed', 'wp-super-cache');
		wp('plugin', 'activate', 'wp-super-cache');
	} catch {
		wp('plugin', 'install', 'wp-super-cache', '--activate');
	}

	// PHP-режим кэша и слеши URL важны: иначе файл записывается, но каждый запрос
	// снова проходит через WordPress. Ниже это подтверждается заголовком HIT.
	wp('eval', 'if (!function_exists("wp_cache_setting")) { throw new Exception("WP Super Cache не загрузился"); } wp_cache_setting("cache_enabled", true); wp_cache_setting("super_cache_enabled", true); wp_cache_setting("wp_cache_slash_check", 1); wp_cache_setting("wpsc_served_header", 1);');

	// 1) HTML до активации не содержит кнопку. Активация должна удалить его физический файл.
	wp('plugin', 'deactivate', slug);
	wp('eval', 'wp_cache_clear_cache();');
	await guestGet(shopUrl);
	const oldPage = await guestGet(shopUrl);
	assert('До активации есть настоящий cache HIT без кнопки', cacheHit(oldPage) && !oldPage.body.includes('data-rvn-compare-button') && fs.existsSync(cacheFile));

	wp('plugin', 'activate', slug);
	assert('Активация удаляет старый HTML-файл кэша', !fs.existsSync(cacheFile));
	const afterActivation = await guestGet(shopUrl, true);
	assert('Первый мобильный запрос после активации получает кнопку и текущие CSS/JS', currentMarkup(afterActivation.body, version));

	// 2) После обновления файла плагина WordPress должен очистить старую версию HTML.
	wp('eval', 'wp_cache_clear_cache();');
	await guestGet(shopUrl);
	await guestGet(shopUrl);
	assert('Перед обновлением снова существует файл кэша', fs.existsSync(cacheFile));

	if (fs.existsSync(cacheFile)) {
		const staleHtml = fs.readFileSync(cacheFile, 'utf8')
			.replaceAll(`compare.js?ver=${version}`, `compare.js?ver=${previousVersion}`)
			.replaceAll(`compare.css?ver=${version}`, `compare.css?ver=${previousVersion}`)
			.concat('\n<!-- RVN-OLD-HTML-VERSION -->\n');
		fs.writeFileSync(cacheFile, staleHtml);
	}

	const beforeUpgrade = await guestGet(shopUrl);
	assert('До входа админа отдаётся именно старый кешированный HTML', cacheHit(beforeUpgrade) && beforeUpgrade.body.includes('RVN-OLD-HTML-VERSION') && beforeUpgrade.body.includes(`compare.js?ver=${previousVersion}`));
	wp('option', 'update', 'rvn_compare_version', previousVersion);

	const adminContext = await browser.newContext();
	const admin = await adminContext.newPage();
	stage = 'real-admin-login-after-upgrade';
	await login(admin); // Здесь реально проходит admin_init; имитации хуков WP-CLI нет.
	assert('Вход в админку повышает сохранённую версию', wp('option', 'get', 'rvn_compare_version') === version);
	assert('Обновление удаляет старый файл HTML-кэша', !fs.existsSync(cacheFile));

	const afterUpgrade = await guestGet(shopUrl, true);
	assert('Первый мобильный гостевой запрос после обновления содержит новые CSS/JS и кнопку', currentMarkup(afterUpgrade.body, version) && !afterUpgrade.body.includes('RVN-OLD-HTML-VERSION'));

	// Новый read-only обработчик доступен, не кэшируется и не требует старого ключа.
	const ajaxGuest = await fetch(ajaxUrl, { headers: { Accept: 'application/json' } });
	const ajaxGuestBody = await ajaxGuest.json();
	assert('AJAX гостя отдаёт новый nonce, не список товаров', ajaxGuest.status === 200 && ajaxGuestBody.success === true && ajaxGuestBody.data?.user === 'guest' && typeof ajaxGuestBody.data?.nonce === 'string' && !Object.hasOwn(ajaxGuestBody.data, 'ids'));
	assert('AJAX гостя не кэшируется', (ajaxGuest.headers.get('cache-control') || '').includes('no-store'));
	const ajaxPost = await fetch(ajaxUrl, { method: 'POST' });
	assert('AJAX обновления ключа только для чтения', ajaxPost.status === 405);
	const ajaxCrossSite = await fetch(ajaxUrl, { headers: { 'Sec-Fetch-Site': 'cross-site' } });
	assert('Явно кросс-сайтовый запрос отклоняется', ajaxCrossSite.status === 403);
	const oldSession = await fetch(new URL('/wp-json/rvn-compare/v1/session', baseUrl));
	assert('Старый REST /session удалён', oldSession.status === 404);

	// 3) Ключ внутри кешированного HTML намеренно испорчен: гость восстанавливает его по AJAX.
	stage = 'guest-stale-nonce';
	const guestContext = await browser.newContext();
	const guest = await guestContext.newPage();
	guest.on('pageerror', (error) => errors.push(`guest: ${error.message}`));
	const guestWatch = watchRecovery(guest);
	const cachedGuestResponse = await guest.goto(shopUrl, { waitUntil: 'load' });
	assert('Гость действительно открывает кешированный HTML', cacheHit({ headers: { get: (key) => cachedGuestResponse.headers()[key] || '' } }));
	await guest.waitForFunction(() => !!window.rvnCompareData, null, { timeout: 10000 });
	await guest.waitForTimeout(400);
	await guest.evaluate(() => { window.rvnCompareData.nonce = 'expired-rvn-compare-nonce'; });
	await waitForAdded(guest);
	assert('Гость: REST отклонил старый nonce и принял действие после обновления', guestWatch.invalid.some((r) => r.status === 403) && guestWatch.restored.some((r) => r.status === 200 && r.result === 'added'), JSON.stringify({ invalid: guestWatch.invalid, restored: guestWatch.restored }));
	assert('Гость: AJAX вызван один раз, без старого заголовка, ответ private/no-store', guestWatch.ajax.length === 1 && guestWatch.ajax[0].status === 200 && guestWatch.ajax[0].body.data?.user === 'guest' && guestWatch.ajax[0].cacheControl.includes('no-store') && guestWatch.ajaxRequests[0].staleHeader === false);
	assert('Гость: список после восстановления остался в браузере', await guest.evaluate(() => { const key = window.rvnCompareData.storageKey; return JSON.parse(localStorage.getItem(key) || '[]').length === 1; }));

	// 4) Вошедший пользователь: REST проверяет cookie+nonce до обработчика, AJAX его восстанавливает.
	stage = 'account-stale-nonce';
	wp('eval', 'delete_user_option(1, "rvn_compare_list");');
	const accountWatch = watchRecovery(admin);
	admin.on('pageerror', (error) => errors.push(`account: ${error.message}`));
	admin.on('requestfailed', (request) => errors.push(`account request: ${request.url()} ${request.failure()?.errorText || ''}`));
	const accountNavigation = await admin.goto(shopUrl, { waitUntil: 'load' });
	const accountHtml = await admin.content();
	const accountData = await admin.evaluate(() => Boolean(window.rvnCompareData));
	assert('Аккаунт открывает магазин с данными скрипта', accountNavigation.status() === 200 && accountHtml.includes('rvnCompareData') && accountData, `HTTP ${accountNavigation.status()}, URL ${admin.url()}, body=${accountHtml.length}, inline=${accountHtml.includes('rvnCompareData')}, script=${accountHtml.includes('compare.js')}, JS=${accountData}`);
	if (!accountData) throw new Error('После входа магазин не содержит данные скрипта');
	await admin.waitForFunction(() => !!window.rvnCompareData, null, { timeout: 10000 });
	await admin.waitForTimeout(400);
	await admin.evaluate(() => { window.rvnCompareData.nonce = 'expired-rvn-compare-nonce'; });
	try {
		await waitForAdded(admin);
	} catch (error) {
		assert('Аккаунт: нажатие после восстановления nonce', false, JSON.stringify({ invalid: accountWatch.invalid, ajax: accountWatch.ajax, restored: accountWatch.restored, js: errors }));
		throw error;
	}
	assert('Аккаунт: ошибку выдаёт сам WordPress REST до нашего маршрута', accountWatch.invalid.some((r) => r.code === 'rest_cookie_invalid_nonce'), JSON.stringify(accountWatch.invalid));
	assert('Аккаунт: AJAX вернул nonce именно вошедшего пользователя без старого заголовка', accountWatch.ajax.length === 1 && accountWatch.ajax[0].body.data?.user === 'registered' && accountWatch.ajaxRequests[0].staleHeader === false);
	assert('Аккаунт: действие повторено один раз и список сохранён в user_meta', accountWatch.restored.some((r) => r.status === 200 && r.result === 'added') && wp('eval', 'echo get_user_option("rvn_compare_list", 1) ?: "";').includes('"ids":['));

	// 5) Ошибка слияния: гостевой список нельзя удалять до серверного подтверждения.
	stage = 'merge-failure-preserves-guest-list';
	wp('eval', 'delete_user_option(1, "rvn_compare_list");');
	const productId = Number(await admin.locator('[data-rvn-compare-button]').first().getAttribute('data-product-id'));
	await admin.evaluate((id) => { localStorage.setItem(window.rvnCompareData.storageKey, JSON.stringify([id])); }, productId);
	await admin.route('**/rvn-compare/v1/merge*', (route) => route.fulfill({ status: 503, contentType: 'application/json', body: '{"code":"temporary_error"}' }));
	await admin.reload({ waitUntil: 'load' });
	await admin.waitForTimeout(800);
	assert('При неудачном слиянии гостевой список не удалён', await admin.evaluate(() => JSON.parse(localStorage.getItem(window.rvnCompareData.storageKey) || '[]').length === 1));
	await admin.unroute('**/rvn-compare/v1/merge*');
	await admin.reload({ waitUntil: 'load' });
	await admin.waitForTimeout(800);
	assert('После успешного повторного слияния гостевой список удалён', await admin.evaluate(() => JSON.parse(localStorage.getItem(window.rvnCompareData.storageKey) || '[]').length === 0));

	assert('В браузере нет ошибок JavaScript', errors.length === 0, errors.join(' | '));

	// Приводим стенд в рабочее состояние для других сценариев.
	wp('eval', 'delete_user_option(1, "rvn_compare_list");');
	await guestContext.close();
	await adminContext.close();
	await browser.close();

	console.log(JSON.stringify({ checks: tests, count: tests.length, passed: tests.filter((t) => t.ok).length }));
	process.exit(tests.every((t) => t.ok) ? 0 : 1);
})().catch((error) => {
	console.log(JSON.stringify({ checks: tests.concat([{ name: 'Сценарий завершился с ошибкой', ok: false, detail: `${stage}: ${String(error.message || error)}` }]) }));
	process.exit(2);
});
