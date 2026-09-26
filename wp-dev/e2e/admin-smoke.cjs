/**
 * Браузерная проверка консоли WordPress для плагина RVN Compare.
 *
 * Запуск:
 *   NODE_PATH=/tmp/wp-tools/pw/node_modules node wp-dev/e2e/admin-smoke.cjs <url> <стенд> <папка-скриншотов> [menu|requirements]
 *
 * menu          — меню «RVN» сразу после «Маркетинга», значок «R», подпункты без дубля,
 *                 клик открывает «Сравнение», страница «Поддержка», ссылка «Настройки».
 * requirements  — WooCommerce выключен: плагин показывает уведомление и не добавляет меню.
 *
 * Результат — JSON в stdout; код выхода 0, если все проверки пройдены.
 */
const { chromium, webkit, firefox } = require('playwright');
const ENGINES = { chromium, webkit, firefox };

const SLUG = 'rvn-compare-products-for-woocommerce';

(async () => {
	const [url, stand, outDir, mode = 'menu'] = process.argv.slice(2);
	const browser = await ENGINES[process.env.NODE_BROWSER || "chromium"].launch();
	const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
	const jsErrors = [];
	const result = { stand, mode, checks: [] };
	const check = (name, ok, detail = '') => result.checks.push({ name, ok: Boolean(ok), detail: String(detail) });

	page.on('pageerror', (error) => jsErrors.push(error.message));
	page.on('console', (message) => {
		if (message.type() === 'error') {
			jsErrors.push(message.text());
		}
	});

	await page.goto(`${url}/wp-login.php`);
	await page.fill('#user_login', 'admin');
	await page.fill('#user_pass', 'admin');
	await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

	if (mode === 'requirements') {
		await page.goto(`${url}/wp-admin/plugins.php`);
		const notice = page.locator('.rvn-compare-requirements-notice');
		const text = (await notice.count()) ? (await notice.innerText()).replace(/\s+/g, ' ').trim() : '';
		check('Уведомление о требованиях показано', text.includes('WooCommerce'), text.slice(0, 180));
		check('Ссылка для исправления есть', (await notice.locator('a').count()) > 0);
		check('Меню RVN не добавлено', (await page.locator('#toplevel_page_rvn').count()) === 0);
		await notice.screenshot({ path: `${outDir}/${stand}-requirements.png` }).catch(() => {});
	} else {
		await page.goto(`${url}/wp-admin/index.php`);
		const ids = await page.$$eval('#adminmenu > li', (items) => items.map((item) => item.id || item.className));
		const marketing = ids.indexOf('toplevel_page_woocommerce-marketing');
		const rvn = ids.indexOf('toplevel_page_rvn');
		check('Пункт «RVN» есть в меню', rvn >= 0);
		check('«RVN» сразу после «Маркетинга»', marketing >= 0 && rvn === marketing + 1, `Маркетинг: ${marketing}, RVN: ${rvn}`);
		check('За «RVN» идёт разделитель блока', rvn >= 0 && String(ids[rvn + 1] || '').includes('wp-menu-separator'), ids[rvn + 1] || '');

		const submenu = await page.$$eval('#toplevel_page_rvn .wp-submenu li:not(.wp-submenu-head) a', (links) => links.map((link) => link.textContent.trim()));
		check('Подпункты: страница плагина и «Поддержка», без дубля «RVN»', submenu.length === 2 && !submenu.includes('RVN'), submenu.join(', '));

		const icon = await page.$eval('#toplevel_page_rvn .wp-menu-image', (element) => getComputedStyle(element, '::before').content);
		check('Значок «R»', icon.includes('R'), icon);

		await page.hover('#toplevel_page_rvn > a');
		await page.waitForTimeout(300);
		await page.screenshot({ path: `${outDir}/${stand}-menu.png`, clip: { x: 0, y: 0, width: 520, height: 1000 } });

		await Promise.all([page.waitForNavigation(), page.click('#toplevel_page_rvn > a')]);
		check('Клик по «RVN» открывает «Сравнение»', page.url().includes('page=rvn-compare'), page.url());
		const heading = (await page.locator('.wrap h1').first().innerText()).trim();
		check('Страница «Сравнение» открывается', heading.length > 0, heading);
		// С 0.5.0 (раунд A) первая вкладка — General с React-приложением настроек:
		// General / Page / Comparison fields / System status. По умолчанию открывается
		// первая; таблица состояния живёт в четвёртой.
		const tabs = await page.locator('.nav-tab-wrapper .nav-tab').count();
		check('Вкладки страницы «Сравнение»', tabs === 5, `${tabs} вкладок`);

		await page.waitForSelector('[data-testid="rvn-admin-app"]', { timeout: 15000 });
		check('Вкладка «General»: React-приложение смонтировано', true);

		// Раунд B (0.5.0): вкладка сохраняет значения через admin-post.php и
		// умеет сбрасывать только свои ключи. Проверяем полный цикл: изменение,
		// сохранение, перечитывание страницы, сброс.
		const controls = await page.locator('[data-testid="rvn-limit-total"] input, [data-testid="rvn-compare-rule"] select, [data-testid="rvn-other-label"] input, [data-testid="rvn-accent-color"] input').count();
		check('Вкладка «General»: поля лимита, правила, названия и цвета', controls === 4, `${controls} поля`);

		await page.fill('[data-testid="rvn-limit-total"] input', '7');
		await page.fill('[data-testid="rvn-other-label"] input', 'Прочие товары');
		await page.selectOption('[data-testid="rvn-compare-rule"] select', 'top_level');
		await page.click('[data-testid="rvn-save-general"]');
		await page.waitForSelector('.components-notice.is-success', { timeout: 15000 });
		check('Вкладка «General»: сохранение показывает успешное уведомление', true);

		await page.reload();
		await page.waitForSelector('[data-testid="rvn-admin-app"]', { timeout: 15000 });
		const savedTotal = await page.inputValue('[data-testid="rvn-limit-total"] input');
		const savedLabel = await page.inputValue('[data-testid="rvn-other-label"] input');
		const savedRule = await page.inputValue('[data-testid="rvn-compare-rule"] select');
		check('Вкладка «General»: значения пережили перезагрузку', savedTotal === '7' && savedLabel === 'Прочие товары' && savedRule === 'top_level', `limit=${savedTotal}, label=${savedLabel}, rule=${savedRule}`);

		// Поиск товаров для исключений: серверный запрос admin-ajax.php, ответ
		// приходит списком; если товаров нет, проверка просто сообщает об этом.
		await page.fill('[data-testid="rvn-product-search"] input', 'a');
		await page.waitForTimeout(1500);
		const found = await page.locator('.rvn-compare-admin-results li').count();
		if (found > 0) {
			await page.locator('.rvn-compare-admin-results li button').first().click();
			await page.waitForTimeout(300);
			const rows = await page.locator('.rvn-compare-admin-exclusions tbody tr').count();
			check('Вкладка «General»: поиск товара добавляет его в исключения', rows >= 1, `${rows} строк`);
			await page.click('[data-testid="rvn-save-general"]');
			await page.waitForSelector('.components-notice.is-success', { timeout: 15000 });
			check('Вкладка «General»: исключения сохраняются', true);
		} else {
			check('Вкладка «General»: поиск товара добавляет его в исключения', true, 'в каталоге нет товаров на букву «a» — проверка пропущена');
		}
		await page.screenshot({ path: `${outDir}/${stand}-general.png`, fullPage: true });

		page.once('dialog', (dialog) => dialog.accept());
		await page.click('[data-testid="rvn-reset-general"]');
		await page.waitForSelector('.components-notice.is-success', { timeout: 15000 });
		const resetTotal = await page.inputValue('[data-testid="rvn-limit-total"] input');
		const resetLabel = await page.inputValue('[data-testid="rvn-other-label"] input');
		check('Вкладка «General»: сброс возвращает значения по умолчанию', resetTotal === '50' && resetLabel === '', `limit=${resetTotal}, label=${resetLabel}`);

		// Раунд C1 (0.5.0): вкладка «Кнопки и уведомления». Проверяем монтаж
		// React-панели, сохранение настройки повторного клика и сброс.
		await Promise.all([page.waitForNavigation(), page.click('.nav-tab-wrapper a[href*="tab=buttons"]')]);
		await page.waitForSelector('[data-rvn-panel="buttons"]', { timeout: 15000 });
		check('Вкладка «Кнопки и уведомления»: React-панель смонтирована', true);
		const fields = await page.locator('[data-testid="rvn-card-position"] select, [data-testid="rvn-second-click"] select, [data-testid="rvn-url-masks"] textarea').count();
		check('Вкладка «Кнопки»: поля позиции, повторного клика и масок', fields === 3, `${fields} поля`);
		await page.selectOption('[data-testid="rvn-second-click"] select', 'open_page');
		await page.click('[data-testid="rvn-save-buttons"]');
		await page.waitForSelector('.components-notice.is-success', { timeout: 15000 });
		check('Вкладка «Кнопки»: сохранение показывает успешное уведомление', true);
		await page.reload();
		await page.waitForSelector('[data-rvn-panel="buttons"]', { timeout: 15000 });
		const savedSecondClick = await page.inputValue('[data-testid="rvn-second-click"] select');
		check('Вкладка «Кнопки»: повторный клик пережил перезагрузку', savedSecondClick === 'open_page', savedSecondClick);
		page.once('dialog', (dialog) => dialog.accept());
		await page.click('[data-testid="rvn-reset-buttons"]');
		await page.waitForSelector('.components-notice.is-success', { timeout: 15000 });
		const resetSecondClick = await page.inputValue('[data-testid="rvn-second-click"] select');
		check('Вкладка «Кнопки»: сброс возвращает значение по умолчанию', resetSecondClick === 'remove', resetSecondClick);

		// Блок уведомлений (раунд C2): позиции, длительность, шаблоны текстов.
		const toastControls = await page.locator('[data-testid=rvn-toast-position] select, [data-testid=rvn-toast-position-mobile] select, [data-testid=rvn-toast-duration] input').count();
		check('Вкладка «Кнопки»: поля уведомлений (позиции, длительность)', toastControls === 3, `${toastControls} поля`);
		const tplControls = await page.locator('[data-testid^=rvn-toast-tpl-] input').count();
		check('Вкладка «Кнопки»: четыре шаблона текстов уведомлений', tplControls === 4, `${tplControls} поля`);

		await page.selectOption('[data-testid=rvn-toast-position-mobile] select', 'top_center');
		await page.fill('[data-testid=rvn-toast-duration] input', '4500');
		await page.fill('[data-testid=rvn-toast-tpl-added] input', 'Добавлен {product}');
		await page.click('[data-testid=rvn-save-buttons]');
		await page.waitForSelector('.components-notice.is-success', { timeout: 15000 });
		await page.reload();
		await page.waitForSelector('[data-rvn-panel=buttons]', { timeout: 15000 });
		const savedToastPos = await page.inputValue('[data-testid=rvn-toast-position-mobile] select');
		const savedToastDuration = await page.inputValue('[data-testid=rvn-toast-duration] input');
		const savedTemplate = await page.inputValue('[data-testid=rvn-toast-tpl-added] input');
		check('Вкладка «Кнопки»: уведомления пережили перезагрузку', savedToastPos === 'top_center' && savedToastDuration === '4500' && savedTemplate === 'Добавлен {product}', `pos=${savedToastPos}, ms=${savedToastDuration}, tpl=${savedTemplate}`);

		page.once('dialog', (dialog) => dialog.accept());
		await page.click('[data-testid=rvn-reset-buttons]');
		await page.waitForSelector('.components-notice.is-success', { timeout: 15000 });
		const resetTemplate = await page.inputValue('[data-testid=rvn-toast-tpl-added] input');
		check('Вкладка «Кнопки»: сброс очищает шаблон уведомления', resetTemplate === '', `[${resetTemplate}]`);

		await page.screenshot({ path: `${outDir}/${stand}-buttons.png`, fullPage: true });

		await Promise.all([page.waitForNavigation(), page.click('.nav-tab-wrapper a[href*="tab=system"]')]);
		const rows = await page.locator('.rvn-compare-status tbody tr').count();
		check('Таблица состояния системы', rows === 5, `${rows} строк`);
		await page.screenshot({ path: `${outDir}/${stand}-compare.png`, fullPage: true });

		await page.goto(`${url}/wp-admin/admin.php?page=rvn-support`);
		const support = (await page.locator('.wrap').first().innerText()).replace(/\s+/g, ' ').trim();
		check('Страница «Поддержка»', support.length > 10, support);

		await page.goto(`${url}/wp-admin/plugins.php`);
		const settingsLinks = await page.locator(`tr[data-slug="${SLUG}"] .row-actions a[href*="page=rvn-compare"]`).count();
		check('Ссылка «Настройки» на экране «Плагины»', settingsLinks === 1);
	}

	const ownErrors = jsErrors.filter((message) => /rvn/i.test(message));
	check('Нет ошибок JavaScript от плагина', ownErrors.length === 0, ownErrors.concat(jsErrors.length ? [`всего ошибок на странице: ${jsErrors.length}`] : []).join(' | '));

	await browser.close();
	console.log(JSON.stringify(result));
	process.exit(result.checks.every((item) => item.ok) ? 0 : 1);
})().catch((error) => {
	console.log(JSON.stringify({ checks: [{ name: 'Сценарий завершился с ошибкой', ok: false, detail: String(error.message || error) }] }));
	process.exit(2);
});
