/**
 * Браузерная проверка итерации 3: динамическая и статическая таблица.
 *
 * Запуск:
 *   NODE_PATH=/tmp/wp-tools/pw/node_modules node wp-dev/e2e/table-smoke.cjs <url> <стенд> <папка-скриншотов>
 *
 * Тест создаёт временную страницу со статическим shortcode, проверяет
 * страницу сравнения, таблицу, текстовые значения и удаляет временную запись.
 * Результат — JSON; код выхода 0 только при полном успехе.
 */
const { chromium, webkit, firefox } = require('playwright');
const ENGINES = { chromium, webkit, firefox };
const { execFileSync } = require('node:child_process');

const [baseUrl, stand, outDir] = process.argv.slice(2);
const slug = 'rvn-compare-products-for-woocommerce';
const checks = [];
const errors = [];

function wp(...args) {
	return execFileSync('wp', [`--path=/tmp/wp-stand-${stand}`, '--quiet', ...args], {
		encoding: 'utf8',
		stdio: ['ignore', 'pipe', 'pipe'],
		timeout: 60000,
	}).trim();
}

function check(name, ok, detail = '') {
	checks.push({ name, ok: Boolean(ok), detail: String(detail) });
}

(async () => {
	if (!baseUrl || !stand || !outDir) throw new Error('Нужны URL, имя стенда и папка скриншотов');

	const ids = wp('post', 'list', '--post_type=product', '--post_status=publish', '--field=ID')
		.split(/\s+/)
		.filter(Boolean)
		.slice(0, 2);
	if (ids.length < 2) throw new Error('На стенде нужны минимум два опубликованных товара');

	const pageId = wp(
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		'--post_title=RVN temporary table smoke',
		`--post_content=[rvn-compare-table products="${ids.join(',')}"]`,
		'--porcelain',
	);
	const staticUrl = wp('post', 'url', pageId);
	const comparisonId = wp('option', 'get', 'rvn_compare_page_id');
	const comparisonUrl = comparisonId ? wp('post', 'url', comparisonId) : `${baseUrl}/compare/`;
	const browser = await ENGINES[process.env.NODE_BROWSER || "chromium"].launch();
	const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
	page.setDefaultTimeout(15000);
	page.on('pageerror', (error) => errors.push(error.message));
	page.on('console', (message) => {
		if (message.type() === 'error') errors.push(message.text());
	});

	try {
		await page.goto(comparisonUrl, { waitUntil: 'load' });
		await page.waitForSelector('[data-rvn-table]', { timeout: 15000 });
		const dynamicHtml = await page.content();
		check('Динамическая страница содержит оболочку таблицы', dynamicHtml.includes('data-rvn-table'));
		check('Динамический HTML не содержит ID товаров', !ids.some((id) => dynamicHtml.includes(`data-products="${id}`)));

		await page.goto(staticUrl, { waitUntil: 'load' });
		await page.waitForSelector('[data-rvn-table]', { timeout: 15000 });
		await page.waitForSelector('.rvn-product-card', { timeout: 15000 });
		const columns = await page.locator('.rvn-product-card').count();
		const rows = await page.locator('[data-rvn-rows] .rvn-feature').count();
		check('Статический shortcode показывает две колонки', columns === 2, `${columns} колонок`);
		check('Таблица содержит строки характеристик', rows > 0, `${rows} строк`);
		check('Вкладки таблицы созданы', (await page.locator('[data-rvn-tabs] button').count()) > 0);
		check('В исходном HTML статического shortcode есть только заданные ID', (await page.locator('[data-rvn-table]').getAttribute('data-products')) === ids.join(','));
		check('В браузере нет ошибок плагина', errors.filter((message) => /rvn/i.test(message)).length === 0, errors.join(' | '));
		await page.screenshot({ path: `${outDir}/${stand}-table.png`, fullPage: true });
	} finally {
		await browser.close();
		wp('post', 'delete', pageId, '--force');
	}

	console.log(JSON.stringify({ stand, mode: 'table', checks, count: checks.length, passed: checks.filter((item) => item.ok).length }));
	process.exit(checks.every((item) => item.ok) ? 0 : 1);
})().catch((error) => {
	console.log(JSON.stringify({ stand, mode: 'table', checks: checks.concat([{ name: 'Сценарий завершился с ошибкой', ok: false, detail: String(error.message || error) }]) }));
	process.exit(2);
});
