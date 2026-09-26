/**
 * Браузерная проверка фронтенда: кнопка сравнения, счётчик и уведомления.
 *
 * Запуск:
 *   NODE_PATH=/tmp/wp-tools/pw/node_modules node wp-dev/e2e/frontend-smoke.cjs <url> <стенд> <папка-скриншотов>
 *
 * Проверяет путь покупателя: кнопка на карточке, добавление, счётчик,
 * уведомление, состояние кнопки после перезагрузки, повторное нажатие
 * (удаление), работу счётчика-шорткода и отсутствие ошибок JavaScript.
 *
 * Результат — JSON в stdout; код выхода 0, если все проверки пройдены.
 */
const { chromium, webkit, firefox } = require('playwright');
const ENGINES = { chromium, webkit, firefox };

const SHOP = '/?post_type=product&rvn_test=1';

(async () => {
	const [url, stand, outDir] = process.argv.slice(2);
	const browser = await ENGINES[process.env.NODE_BROWSER || "chromium"].launch();
	const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });

	// Общий таймаут: сценарий не должен висеть, если страница ведёт себя неожиданно.
	page.setDefaultTimeout(15000);
	page.setDefaultNavigationTimeout(20000);
	const result = { stand, mode: 'frontend', checks: [] };
	const jsErrors = [];
	const check = (name, ok, detail = '') => result.checks.push({ name, ok: Boolean(ok), detail: String(detail) });

	page.on('pageerror', (error) => jsErrors.push(error.message));
	page.on('console', (message) => {
		if (message.type() === 'error') {
			jsErrors.push(message.text());
		}
	});

	await page.goto(`${url}${SHOP}`, { waitUntil: 'load' });

	// Ждём, пока скрипт плагина получит свои данные: он подключается в подвале страницы.
	await page.waitForFunction(() => Boolean(window.rvnCompareData), null, { timeout: 15000 });
	await page.waitForSelector('[data-rvn-compare-button]', { timeout: 15000 });

	const buttons = await page.locator('[data-rvn-compare-button]').count();
	check('кнопки сравнения на карточках', buttons > 0, `${buttons} шт.`);

	// Счётчики и кнопка «Очистить» живут на странице с шорткодами: магазин их не выводит.
	const shorts = await page.evaluate(() => {
		const link = Array.from(document.querySelectorAll('a')).find((item) => item.href.indexOf('rvn-shortcodes') !== -1);

		return link ? link.getAttribute('href') : '';
	});

	const first = page.locator('[data-rvn-compare-button]').first();
	const label = (await first.getAttribute('data-label-compare')) || '';
	check('текст кнопки переводится', label.length > 0, label);

	await first.click();
	await page.waitForTimeout(900);

	check('кнопка стала активной', (await first.getAttribute('aria-pressed')) === 'true');
	check('уведомление показано', (await page.locator('.rvn-toast').count()) > 0);
	const toastText = (await page.locator('.rvn-toast').first().innerText().catch(() => '')).replace(/\s+/g, ' ').trim();
	check('в уведомлении есть текст', toastText.length > 3, toastText.slice(0, 90));
	check('полоска обратного отсчёта', (await page.locator('.rvn-toast-bar').count()) > 0);

	const stored = await page.evaluate(() => {
		const key = Object.keys(window.localStorage).find((item) => item.indexOf('rvn-compare:list:') === 0);

		return key ? window.localStorage.getItem(key) : '';
	});
	check('список сохранён в браузере', stored.indexOf('[') === 0, stored);

	// Проверяем живой счётчик на странице с шорткодами.
	const shortsUrl = shorts || await page.evaluate(() => {
		const link = Array.from(document.querySelectorAll('a')).find((item) => item.href.indexOf('rvn-shortcodes') !== -1);

		return link ? link.getAttribute('href') : '';
	});

	await page.screenshot({ path: `${outDir}/${stand}-frontend-added.png`, fullPage: false });

	const shortsPage = shortsUrl || `${url}/?page_id=0`;

	if (shortsUrl) {
		await page.goto(shortsUrl, { waitUntil: 'load' });
		await page.waitForFunction(() => Boolean(window.rvnCompareData), null, { timeout: 15000 });
		await page.waitForTimeout(700);

		const counter = page.locator('[data-rvn-counter]').first();
		const counterValue = (await counter.innerText().catch(() => '')).trim();
		check('счётчик присутствует на странице шорткодов', (await counter.count()) > 0);
		check('счётчик показывает число товаров', counterValue === '1', `значение: ${counterValue}`);
		check('кнопка-счётчик ведёт на страницу сравнения', (await page.locator('[data-rvn-counter-button]').count()) > 0);
		check('прогресс по лимиту показан', (await page.locator('[data-rvn-progress]').count()) > 0);
		check('кнопка «Очистить» есть', (await page.locator('[data-rvn-clear]').count()) > 0);
		await page.screenshot({ path: `${outDir}/${stand}-shortcodes.png`, fullPage: false });
	} else {
		check('страница с шорткодами найдена', false, shortsPage);
	}

	// Возвращаемся в магазин: состояние должно пережить переход между страницами.
	await page.goto(`${url}${SHOP}`, { waitUntil: 'load' });
	await page.waitForFunction(() => Boolean(window.rvnCompareData), null, { timeout: 15000 });

	// Перезагрузка: состояние должно восстановиться из localStorage.
	await page.reload({ waitUntil: 'load' });
	await page.waitForFunction(() => Boolean(window.rvnCompareData), null, { timeout: 15000 });
	await page.waitForSelector('[data-rvn-compare-button]', { timeout: 15000 });
	await page.waitForTimeout(900);
	const afterReload = await page.locator('[data-rvn-compare-button]').first().getAttribute('aria-pressed');
	check('после перезагрузки кнопка активна', afterReload === 'true');

	// Повторное нажатие убирает товар.
	await page.locator('[data-rvn-compare-button]').first().click();
	await page.waitForTimeout(900);
	const afterSecond = await page.locator('[data-rvn-compare-button]').first().getAttribute('aria-pressed');
	check('повторное нажатие убирает товар', afterSecond === 'false');
	check('появилось уведомление об удалении', (await page.locator('.rvn-toast').count()) > 0);

	// Кнопка не должна оказаться внутри ссылки на товар: клик открывал бы карточку.
	const nested = await page.evaluate(() => {
		const button = document.querySelector('[data-rvn-compare-button]');

		return button ? Boolean(button.closest('a')) : true;
	});
	check('кнопка не вложена в ссылку товара', nested === false);

	const ownErrors = jsErrors.filter((message) => /rvn/i.test(message));
	check('нет ошибок JavaScript от плагина', ownErrors.length === 0, ownErrors.join(' | '));

	await browser.close();
	console.log(JSON.stringify(result));
	process.exit(result.checks.every((item) => item.ok) ? 0 : 1);
})().catch((error) => {
	console.log(JSON.stringify({ checks: [{ name: 'Сценарий завершился с ошибкой', ok: false, detail: String(error.message || error) }] }));
	process.exit(2);
});
