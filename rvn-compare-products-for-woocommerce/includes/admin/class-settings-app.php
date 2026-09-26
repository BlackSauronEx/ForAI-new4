<?php
/**
 * Каркас интерфейса настроек 0.5.0: React-приложение на компонентах WordPress.
 *
 * PHP регистрирует страницу, проверяет права, передаёт nonce и текущие
 * настройки, а динамический интерфейс рисует собранный бандл
 * assets/admin/settings.js. Сохранение в раунде A отсутствует: приложение
 * только показывает данные (контракт санитайзера вступит с первой
 * сохраняющей вкладкой — единственным валидатором останется
 * Settings::sanitize()).
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Подключает скрипты и выводит корневой контейнер приложения настроек.
 */
final class Settings_App {

	/**
	 * Хэндл скрипта настроек.
	 *
	 * @var string
	 */
	const HANDLE = 'rvn-compare-admin-settings';

	/**
	 * Подключает загрузку скриптов на странице настроек.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Подключает собранный бандл на React-вкладках страницы «Compare».
	 *
	 * Сейчас это вкладки «General» и «Кнопки и уведомления» (buttons).
	 * Если бандл не собран (разработчик не запускал build.sh), скрипты тихо
	 * не подключаются, и на экране остаётся PHP-заглушка с объяснением.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || false === strpos( $screen->id, Admin::PAGE_SLUG ) ) {
			return;
		}

		// Это чтение вкладки ничего не меняет. Изменения проходят отдельный POST с nonce.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Бандл нужен только на React-вкладках: General (раунд A/B) и
		// «Кнопки и уведомления» (раунд C). Остальные вкладки — чистый PHP.
		if ( ! in_array( $tab, array( 'general', 'buttons' ), true ) ) {
			return;
		}

		$asset_path = RVN_COMPARE_PATH . 'assets/admin/settings.asset.php';
		$js_path    = RVN_COMPARE_PATH . 'assets/admin/settings.js';

		if ( ! is_readable( $asset_path ) || ! is_readable( $js_path ) ) {
			return;
		}

		$asset = include $asset_path;

		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) || ! is_array( $asset['dependencies'] ) ) {
			return;
		}

		$dependencies = array();

		foreach ( $asset['dependencies'] as $dependency ) {
			if ( is_string( $dependency ) ) {
				$dependencies[] = $dependency;
			}
		}

		$version = is_string( $asset['version'] ) ? $asset['version'] : RVN_COMPARE_VERSION;

		wp_register_script( self::HANDLE, RVN_COMPARE_URL . 'assets/admin/settings.js', $dependencies, $version, true );

		$data = wp_json_encode( self::bootstrap_data() );

		if ( ! is_string( $data ) ) {
			return;
		}

		wp_add_inline_script( self::HANDLE, 'window.rvnCompareAdminData = ' . $data . ';', 'before' );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Выводит корневой контейнер приложения и заглушку на случай сбоя JS.
	 *
	 * React при успешном запуске заменяет содержимое контейнера; если бандл
	 * не загрузился, администратор видит понятное объяснение, а не пустоту.
	 *
	 * @return void
	 */
	public static function render(): void {
		?>
		<div id="rvn-compare-admin-root" class="rvn-compare-admin">
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'The settings interface could not be loaded. Please reload the page; if the problem persists, a plugin or browser extension may be blocking the script.', 'rvn-compare-products-for-woocommerce' ); ?></p>
			</div>
			<noscript><p><?php esc_html_e( 'Enable JavaScript to use the comparison settings interface.', 'rvn-compare-products-for-woocommerce' ); ?></p></noscript>
		</div>
		<?php
	}

	/**
	 * Собирает данные для приложения: версию, nonce, настройки и строки.
	 *
	 * Строки интерфейса переводятся здесь, в PHP, поэтому бандл не содержит
	 * вызовов wp.i18n — так же устроены скрипты витрины.
	 *
	 * @return array<string, mixed>
	 */
	private static function bootstrap_data(): array {
		$general   = General_Settings::bootstrap();
		$buttons   = Buttons_Settings::bootstrap();
		$react_tab = isset( $_GET['tab'] ) && 'buttons' === sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) ? 'buttons' : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- вкладка ничего не меняет.

		return array(
			'version'    => RVN_COMPARE_VERSION,
			'nonce'      => wp_create_nonce( 'rvn_compare_admin' ),
			'activeTab'  => $react_tab,
			'settings'   => $general['settings'],
			'categories' => $general['categories'],
			'rules'      => $general['compareRules'],
			'urls'       => $general['urls'],
			'buttons'    => $buttons,
			'i18n'       => array(
				'generalTab'            => __( 'General settings', 'rvn-compare-products-for-woocommerce' ),
				'limitsHeading'         => __( 'Limits', 'rvn-compare-products-for-woocommerce' ),
				'limitTotal'            => __( 'Total limit', 'rvn-compare-products-for-woocommerce' ),
				'limitTotalHelp'        => __( 'How many products a visitor can compare at once (1–50).', 'rvn-compare-products-for-woocommerce' ),
				'limitCategory'         => __( 'Per-tab limit', 'rvn-compare-products-for-woocommerce' ),
				'limitCategoryHelp'     => __( 'How many products fit into one category tab. It never exceeds the total limit.', 'rvn-compare-products-for-woocommerce' ),
				'rulesHeading'          => __( 'Comparison rule', 'rvn-compare-products-for-woocommerce' ),
				'compareRule'           => __( 'How products are split into tabs', 'rvn-compare-products-for-woocommerce' ),
				'compareRuleHelp'       => __( 'Tabs group products by category so that only comparable items sit side by side.', 'rvn-compare-products-for-woocommerce' ),
				'ignoredCategories'     => __( 'Ignored categories', 'rvn-compare-products-for-woocommerce' ),
				'ignoredHelp'           => __( 'These categories never become tabs. Hold Ctrl (Cmd on macOS) to select several.', 'rvn-compare-products-for-woocommerce' ),
				'otherLabel'            => __( 'Name of the “Other” tab', 'rvn-compare-products-for-woocommerce' ),
				'otherLabelHelp'        => __( 'Products without a usable category land here. Leave blank for the built-in translation.', 'rvn-compare-products-for-woocommerce' ),
				'exclusionsHeading'     => __( 'Exclusions for automatic buttons', 'rvn-compare-products-for-woocommerce' ),
				'exclusionsHelp'        => __( 'Hide the automatic “Compare” button for selected products or categories. Shortcodes still work there.', 'rvn-compare-products-for-woocommerce' ),
				'excludedProducts'      => __( 'Excluded products', 'rvn-compare-products-for-woocommerce' ),
				'excludedCategories'    => __( 'Excluded categories', 'rvn-compare-products-for-woocommerce' ),
				'searchPlaceholder'     => __( 'Search products by name or SKU…', 'rvn-compare-products-for-woocommerce' ),
				'searchEmpty'           => __( 'Nothing found. Type at least two characters.', 'rvn-compare-products-for-woocommerce' ),
				'searchBusy'            => __( 'Searching…', 'rvn-compare-products-for-woocommerce' ),
				'add'                   => __( 'Add', 'rvn-compare-products-for-woocommerce' ),
				'remove'                => __( 'Remove', 'rvn-compare-products-for-woocommerce' ),
				'onCard'                => __( 'Product cards', 'rvn-compare-products-for-woocommerce' ),
				'onSingle'              => __( 'Product page', 'rvn-compare-products-for-woocommerce' ),
				'listEmpty'             => __( 'The list is empty.', 'rvn-compare-products-for-woocommerce' ),
				'lookHeading'           => __( 'Look and offsets', 'rvn-compare-products-for-woocommerce' ),
				'accentColor'           => __( 'Accent color', 'rvn-compare-products-for-woocommerce' ),
				'accentColorHelp'       => __( 'Buttons, counter and highlights are derived from this color.', 'rvn-compare-products-for-woocommerce' ),
				'offsetTop'             => __( 'Top offset', 'rvn-compare-products-for-woocommerce' ),
				'offsetBottom'          => __( 'Bottom offset', 'rvn-compare-products-for-woocommerce' ),
				'offsetHelp'            => __( 'Reserved space for a fixed header or bar, for example 80px or 4rem.', 'rvn-compare-products-for-woocommerce' ),
				'offsetTopMobile'       => __( 'Top offset on the phone', 'rvn-compare-products-for-woocommerce' ),
				'offsetBottomMobile'    => __( 'Bottom offset on the phone', 'rvn-compare-products-for-woocommerce' ),
				'offsetMobileHelp'      => __( 'Leave blank to use the computer value.', 'rvn-compare-products-for-woocommerce' ),
				'save'                  => __( 'Save changes', 'rvn-compare-products-for-woocommerce' ),
				'saving'                => __( 'Saving…', 'rvn-compare-products-for-woocommerce' ),
				'resetTab'              => __( 'Reset this tab', 'rvn-compare-products-for-woocommerce' ),
				'resetConfirm'          => __( 'Restore the General tab to default values? Other tabs are not affected.', 'rvn-compare-products-for-woocommerce' ),
				'errorGeneric'          => __( 'The request failed. Please reload the page and try again.', 'rvn-compare-products-for-woocommerce' ),

				// Вкладка «Кнопки и уведомления» (раунд C).
				'buttonsTab'            => __( 'Buttons and notifications', 'rvn-compare-products-for-woocommerce' ),
				'positionsHeading'      => __( 'Button position', 'rvn-compare-products-for-woocommerce' ),
				'cardPosition'          => __( 'On product cards', 'rvn-compare-products-for-woocommerce' ),
				'singlePosition'        => __( 'On the product page', 'rvn-compare-products-for-woocommerce' ),
				'positionsHelp'         => __( '“Hidden (shortcode only)” turns off the automatic button; place it manually with the [rvn-compare-button] shortcode.', 'rvn-compare-products-for-woocommerce' ),
				'modesHeading'          => __( 'Button mode', 'rvn-compare-products-for-woocommerce' ),
				'cardMode'              => __( 'On product cards — computer', 'rvn-compare-products-for-woocommerce' ),
				'cardModeMobile'        => __( 'On product cards — phone', 'rvn-compare-products-for-woocommerce' ),
				'singleMode'            => __( 'On the product page — computer', 'rvn-compare-products-for-woocommerce' ),
				'singleModeMobile'      => __( 'On the product page — phone', 'rvn-compare-products-for-woocommerce' ),
				'modesHelp'             => __( '“Same as computer” copies the desktop mode to phones.', 'rvn-compare-products-for-woocommerce' ),
				'textsHeading'          => __( 'Button texts', 'rvn-compare-products-for-woocommerce' ),
				'buttonLabel'           => __( '“Compare” state', 'rvn-compare-products-for-woocommerce' ),
				'buttonInLabel'         => __( '“In comparison” state', 'rvn-compare-products-for-woocommerce' ),
				'emptyMeansTranslation' => __( 'Leave empty to use the built-in translation.', 'rvn-compare-products-for-woocommerce' ),
				'behaviorHeading'       => __( 'Behavior', 'rvn-compare-products-for-woocommerce' ),
				'secondClick'           => __( 'When a visitor clicks an active button', 'rvn-compare-products-for-woocommerce' ),
				'secondClickHelp'       => __( 'The button is active when the product is already in the comparison list.', 'rvn-compare-products-for-woocommerce' ),
				'iconHeading'           => __( 'Button icon', 'rvn-compare-products-for-woocommerce' ),
				'buttonIcon'            => __( 'Icon', 'rvn-compare-products-for-woocommerce' ),
				'iconHelp'              => __( 'Built-in set, one custom emoji, or no icon at all.', 'rvn-compare-products-for-woocommerce' ),
				'iconEmojiValue'        => __( 'Your emoji (one symbol)', 'rvn-compare-products-for-woocommerce' ),
				'iconEmojiHelp'         => __( 'An empty value falls back to the built-in icon set.', 'rvn-compare-products-for-woocommerce' ),
				'whereHeading'          => __( 'Where to show the automatic button', 'rvn-compare-products-for-woocommerce' ),
				'whereHelp'             => __( 'Applies to product cards. The product page is governed by its position setting; shortcodes with an explicit ID are never limited.', 'rvn-compare-products-for-woocommerce' ),
				'showContexts'          => __( 'WooCommerce contexts', 'rvn-compare-products-for-woocommerce' ),
				'urlRestriction'        => __( 'Page restrictions by URL', 'rvn-compare-products-for-woocommerce' ),
				'urlMode'               => __( 'Mode', 'rvn-compare-products-for-woocommerce' ),
				'urlMasks'              => __( 'Page masks, one per line', 'rvn-compare-products-for-woocommerce' ),
				'urlMasksHelp'          => __( 'Use * as a wildcard, for example: sale/* or /about-us. An empty list means no restrictions.', 'rvn-compare-products-for-woocommerce' ),

				// Уведомления (раунд C2).
				'toastsHeading'         => __( 'Notifications (toasts)', 'rvn-compare-products-for-woocommerce' ),
				'toastsHelp'            => __( 'A notification appears after a visitor adds or removes a product. These settings apply to the whole site.', 'rvn-compare-products-for-woocommerce' ),
				'toastPosition'         => __( 'Position on the computer', 'rvn-compare-products-for-woocommerce' ),
				'toastPositionMobile'   => __( 'Position on the phone', 'rvn-compare-products-for-woocommerce' ),
				'toastDuration'         => __( 'How long a notification stays, ms', 'rvn-compare-products-for-woocommerce' ),
				'toastDurationHelp'     => __( 'From 0 to 15000. 0 keeps the notification until the visitor closes it.', 'rvn-compare-products-for-woocommerce' ),
				'toastTemplatesHeading' => __( 'Notification texts', 'rvn-compare-products-for-woocommerce' ),
				'toastTemplatesHelp'    => __( 'Available substitutions: {product}, {category}, {count}, {limit}. An empty field uses the built-in translation.', 'rvn-compare-products-for-woocommerce' ),
				'toastTplAdded'         => __( 'When a product is added', 'rvn-compare-products-for-woocommerce' ),
				'toastTplRemoved'       => __( 'When a product is removed', 'rvn-compare-products-for-woocommerce' ),
				'toastTplLimit'         => __( 'When the list is full', 'rvn-compare-products-for-woocommerce' ),
				'toastTplLimitTab'      => __( 'When the category tab is full', 'rvn-compare-products-for-woocommerce' ),
			),
		);
	}
}
