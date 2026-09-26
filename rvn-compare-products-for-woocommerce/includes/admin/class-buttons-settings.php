<?php
/**
 * Вкладка «Кнопки и уведомления»: сохранение, сброс и данные для интерфейса.
 *
 * Как и вкладка «General», отвечает JSON на запросы admin-post.php: форму
 * рисует React-приложение (assets/admin/settings.js). Единственный валидатор
 * значений — Settings::sanitize().
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Admin;

use RVN_Compare\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Сохраняет настройки кнопок и передаёт интерфейсу варианты и текущие значения.
 */
final class Buttons_Settings {

	/**
	 * Ключи, которые редактирует вкладка «Кнопки и уведомления».
	 *
	 * Используется и при сохранении, и при сбросе вкладки к значениям по
	 * умолчанию. Настройки уведомлений добавятся в тот же SCOPE в раунде C2.
	 *
	 * @var string[]
	 */
	const SCOPE = array(
		'card_position',
		'single_position',
		'card_mode',
		'card_mode_mobile',
		'single_mode',
		'single_mode_mobile',
		'button_label',
		'button_in_label',
		'second_click_action',
		'button_icon',
		'button_icon_emoji',
		'show_contexts',
		'show_url_mode',
		'show_url_masks',
		'toast_position',
		'toast_position_mobile',
		'toast_duration',
		'toast_tpl_added',
		'toast_tpl_removed',
		'toast_tpl_limit',
		'toast_tpl_limit_tab',
	);

	/**
	 * Подключает обработчики сохранения и сброса.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_rvn_compare_save_buttons', array( self::class, 'save' ) );
		add_action( 'admin_post_rvn_compare_reset_buttons', array( self::class, 'reset' ) );
	}

	/**
	 * Сохраняет значения вкладки и отвечает сохранённым набором.
	 *
	 * @return void
	 */
	public static function save(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => self::capability_message() ), 403 );
		}

		// Проверка nonce пишется прямо в обработчике: иначе WPCS её не видит.
		if ( ! check_ajax_referer( 'rvn_compare_save_buttons', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => self::nonce_message() ), 403 );
		}

		// Суперглобальный массив читается здесь, после проверки nonce:
		// вспомогательные методы получают уже готовые данные.
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- очистка выполняется ниже по каждому ключу.

		$input = self::scope_input( $post );

		if ( isset( $post['show_contexts'] ) ) {
			$input['show_contexts'] = self::contexts( $post['show_contexts'] );
		}

		if ( isset( $post['show_url_masks'] ) ) {
			$input['show_url_masks'] = (string) $post['show_url_masks'];
		}

		$saved = Settings::update( $input );

		wp_send_json_success(
			array(
				'settings' => self::export( $saved ),
				'message'  => __( 'Button settings saved.', 'rvn-compare-products-for-woocommerce' ),
			)
		);
	}

	/**
	 * Возвращает ключам вкладки значения по умолчанию.
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => self::capability_message() ), 403 );
		}

		// Проверка nonce пишется прямо в обработчике: иначе WPCS её не видит.
		if ( ! check_ajax_referer( 'rvn_compare_reset_buttons', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => self::nonce_message() ), 403 );
		}

		$defaults = Settings::defaults();
		$input    = array();

		foreach ( self::SCOPE as $key ) {
			$input[ $key ] = $defaults[ $key ];
		}

		$saved = Settings::update( $input );

		wp_send_json_success(
			array(
				'settings' => self::export( $saved ),
				'message'  => __( 'Button settings restored to defaults.', 'rvn-compare-products-for-woocommerce' ),
			)
		);
	}

	/**
	 * Собирает данные для интерфейса: текущие значения и варианты выбора.
	 *
	 * @return array<string, mixed>
	 */
	public static function bootstrap(): array {
		$settings = Settings::all();

		return array(
			'settings'           => self::export( $settings ),
			'positions'          => self::labeled( Settings::POSITIONS, self::position_labels() ),
			'modes'              => self::labeled( Settings::BUTTON_MODES, self::mode_labels() ),
			'modeInherit'        => array(
				array(
					'value' => 'inherit',
					/* translators: phone button mode: same as desktop. */
					'label' => __( 'Same as computer', 'rvn-compare-products-for-woocommerce' ),
				),
			),
			'secondClickActions' => array(
				array(
					'value' => 'remove',
					'label' => __( 'Remove from comparison', 'rvn-compare-products-for-woocommerce' ),
				),
				array(
					'value' => 'open_page',
					'label' => __( 'Open the comparison page', 'rvn-compare-products-for-woocommerce' ),
				),
			),
			'iconModes'          => array(
				array(
					'value' => 'builtin',
					'label' => __( 'Built-in icon set', 'rvn-compare-products-for-woocommerce' ),
				),
				array(
					'value' => 'emoji',
					'label' => __( 'Custom emoji', 'rvn-compare-products-for-woocommerce' ),
				),
				array(
					'value' => 'none',
					'label' => __( 'No icon', 'rvn-compare-products-for-woocommerce' ),
				),
			),
			'contexts'           => self::contexts_data(),
			'urlModes'           => array(
				array(
					'value' => 'all_except',
					'label' => __( 'Hide on the listed pages', 'rvn-compare-products-for-woocommerce' ),
				),
				array(
					'value' => 'only',
					'label' => __( 'Show only on the listed pages', 'rvn-compare-products-for-woocommerce' ),
				),
			),
			'toastPositions'     => self::labeled(
				Settings::TOAST_POSITIONS,
				array(
					'top_left'      => __( 'Top left', 'rvn-compare-products-for-woocommerce' ),
					'top_center'    => __( 'Top center', 'rvn-compare-products-for-woocommerce' ),
					'top_right'     => __( 'Top right', 'rvn-compare-products-for-woocommerce' ),
					'bottom_left'   => __( 'Bottom left', 'rvn-compare-products-for-woocommerce' ),
					'bottom_center' => __( 'Bottom center', 'rvn-compare-products-for-woocommerce' ),
					'bottom_right'  => __( 'Bottom right', 'rvn-compare-products-for-woocommerce' ),
					'center'        => __( 'Center of the screen', 'rvn-compare-products-for-woocommerce' ),
				)
			),
			'toastPlaceholders'  => array( '{product}', '{category}', '{count}', '{limit}' ),

			'urls'               => array(
				'save'  => self::handler_url( 'rvn_compare_save_buttons' ),
				'reset' => self::handler_url( 'rvn_compare_reset_buttons' ),
			),
		);
	}

	/**
	 * Отдаёт в интерфейс только ключи вкладки в безопасном виде.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @return array<string, mixed>
	 */
	private static function export( array $settings ): array {
		return array(
			'card_position'         => (string) ( $settings['card_position'] ?? 'after_cart' ),
			'single_position'       => (string) ( $settings['single_position'] ?? 'after_cart' ),
			'card_mode'             => (string) ( $settings['card_mode'] ?? 'icon_text' ),
			'card_mode_mobile'      => (string) ( $settings['card_mode_mobile'] ?? 'icon' ),
			'single_mode'           => (string) ( $settings['single_mode'] ?? 'icon_text' ),
			'single_mode_mobile'    => (string) ( $settings['single_mode_mobile'] ?? 'inherit' ),
			'button_label'          => (string) ( $settings['button_label'] ?? '' ),
			'button_in_label'       => (string) ( $settings['button_in_label'] ?? '' ),
			'second_click_action'   => (string) ( $settings['second_click_action'] ?? 'remove' ),
			'button_icon'           => (string) ( $settings['button_icon'] ?? 'builtin' ),
			'button_icon_emoji'     => (string) ( $settings['button_icon_emoji'] ?? '' ),
			'show_contexts'         => array_values( (array) ( $settings['show_contexts'] ?? array() ) ),
			'show_url_mode'         => (string) ( $settings['show_url_mode'] ?? 'all_except' ),
			'show_url_masks'        => implode( "\n", (array) ( $settings['show_url_masks'] ?? array() ) ),

			// Уведомления: позиции, длительность показа и шаблоны текстов.
			'toast_position'        => (string) ( $settings['toast_position'] ?? 'bottom_right' ),
			'toast_position_mobile' => (string) ( $settings['toast_position_mobile'] ?? 'bottom_center' ),
			'toast_duration'        => (int) ( $settings['toast_duration'] ?? 3200 ),

			// Пустой шаблон означает встроенный перевод.
			'toast_tpl_added'       => (string) ( $settings['toast_tpl_added'] ?? '' ),
			'toast_tpl_removed'     => (string) ( $settings['toast_tpl_removed'] ?? '' ),
			'toast_tpl_limit'       => (string) ( $settings['toast_tpl_limit'] ?? '' ),
			'toast_tpl_limit_tab'   => (string) ( $settings['toast_tpl_limit_tab'] ?? '' ),
		);
	}

	/**
	 * Список контекстов для интерфейса: значение, название, подсказка.
	 *
	 * @return array<int, array{value: string, label: string, description: string}>
	 */
	private static function contexts_data(): array {
		$labels = array(
			'shop'    => __( 'Shop (all products catalog)', 'rvn-compare-products-for-woocommerce' ),
			'catalog' => __( 'Product categories and tags', 'rvn-compare-products-for-woocommerce' ),
			'search'  => __( 'Search results', 'rvn-compare-products-for-woocommerce' ),
			'home'    => __( 'Site front page', 'rvn-compare-products-for-woocommerce' ),
			'related' => __( 'Related products on the product page', 'rvn-compare-products-for-woocommerce' ),
			'cart'    => __( 'Cart (upsells and cross-sells)', 'rvn-compare-products-for-woocommerce' ),
		);

		$list = array();

		foreach ( Settings::SHOW_CONTEXTS as $context ) {
			$list[] = array(
				'value'       => $context,
				'label'       => $labels[ $context ],
				'description' => '',
			);
		}

		return $list;
	}

	/**
	 * Преобразует список ключей в пары «значение — переведённый вариант».
	 *
	 * @param string[] $values  Ключи.
	 * @param string[] $labels  Подписи по тем же ключам.
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function labeled( array $values, array $labels ): array {
		$list = array();

		foreach ( $values as $value ) {
			$list[] = array(
				'value' => $value,
				'label' => $labels[ $value ] ?? $value,
			);
		}

		return $list;
	}

	/**
	 * Переведённые названия позиций кнопки.
	 *
	 * @return array<string, string>
	 */
	private static function position_labels(): array {
		return array(
			'after_cart'  => __( 'After “Add to cart” button', 'rvn-compare-products-for-woocommerce' ),
			'before_cart' => __( 'Before “Add to cart” button', 'rvn-compare-products-for-woocommerce' ),
			'above_title' => __( 'Above the title', 'rvn-compare-products-for-woocommerce' ),
			'below_title' => __( 'Below the title', 'rvn-compare-products-for-woocommerce' ),
			'image_tl'    => __( 'On the image — top left', 'rvn-compare-products-for-woocommerce' ),
			'image_tr'    => __( 'On the image — top right', 'rvn-compare-products-for-woocommerce' ),
			'image_bl'    => __( 'On the image — bottom left', 'rvn-compare-products-for-woocommerce' ),
			'image_br'    => __( 'On the image — bottom right', 'rvn-compare-products-for-woocommerce' ),
			'off'         => __( 'Hidden (shortcode only)', 'rvn-compare-products-for-woocommerce' ),
		);
	}

	/**
	 * Переведённые названия режимов кнопки.
	 *
	 * @return array<string, string>
	 */
	private static function mode_labels(): array {
		return array(
			'icon_text'      => __( 'Icon and text', 'rvn-compare-products-for-woocommerce' ),
			'icon'           => __( 'Icon only', 'rvn-compare-products-for-woocommerce' ),
			'text'           => __( 'Text only', 'rvn-compare-products-for-woocommerce' ),
			'text_icon'      => __( 'Text and icon', 'rvn-compare-products-for-woocommerce' ),
			'text_over_icon' => __( 'Text above the icon', 'rvn-compare-products-for-woocommerce' ),
			'icon_over_text' => __( 'Icon above the text', 'rvn-compare-products-for-woocommerce' ),
		);
	}

	/**
	 * Собирает адрес обработчика admin-post.php вместе с nonce.
	 *
	 * Ссылка собирается вручную: `wp_nonce_url()` и `add_query_arg()`
	 * возвращают `&amp;`, а в JSON браузер отправит такой nonce в параметре
	 * с искажённым именем, и проверка не пройдёт.
	 *
	 * @param string $action Действие и nonce.
	 * @return string
	 */
	private static function handler_url( string $action ): string {
		return admin_url( 'admin-post.php' ) . '?action=' . rawurlencode( $action ) . '&_wpnonce=' . rawurlencode( (string) wp_create_nonce( $action ) );
	}

	/**
	 * Сообщение об отсутствии права.
	 *
	 * @return string
	 */
	private static function capability_message(): string {
		return __( 'You are not allowed to change comparison settings.', 'rvn-compare-products-for-woocommerce' );
	}

	/**
	 * Сообщение о просроченном токене безопасности.
	 *
	 * @return string
	 */
	private static function nonce_message(): string {
		return __( 'The security token has expired. Please reload the page.', 'rvn-compare-products-for-woocommerce' );
	}

	/**
	 * Скалярные ключи вкладки без дополнительной обработки.
	 *
	 * Очисткой занимается Settings::sanitize().
	 *
	 * @param array<string, mixed> $post Данные запроса.
	 * @return array<string, mixed>
	 */
	private static function scope_input( array $post ): array {
		$input = array();
		$keys  = array(
			'card_position',
			'single_position',
			'card_mode',
			'card_mode_mobile',
			'single_mode',
			'single_mode_mobile',
			'button_label',
			'button_in_label',
			'second_click_action',
			'button_icon',
			'button_icon_emoji',
			'show_url_mode',
			'toast_position',
			'toast_position_mobile',
			'toast_duration',
			'toast_tpl_added',
			'toast_tpl_removed',
			'toast_tpl_limit',
			'toast_tpl_limit_tab',
		);

		foreach ( $keys as $key ) {
			// Массив вместо строки (например, button_label[]=…) приводил бы к
			// PHP Warning «Array to string conversion» — такие значения отбрасываем.
			if ( isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ) {
				$input[ $key ] = sanitize_text_field( (string) $post[ $key ] );
			}
		}

		return $input;
	}

	/**
	 * Список разрешённых контекстов из запроса.
	 *
	 * @param mixed $raw Сырое значение поля.
	 * @return string[]
	 */
	private static function contexts( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$list = array();

		foreach ( $raw as $context ) {
			// Элемент может оказаться массивом — приведение к строке тогда
			// сыплет PHP Warning. Не-скалярные значения просто пропускаем.
			if ( ! is_scalar( $context ) ) {
				continue;
			}

			$context = sanitize_key( (string) $context );

			if ( in_array( $context, Settings::SHOW_CONTEXTS, true ) && ! in_array( $context, $list, true ) ) {
				$list[] = $context;
			}
		}

		return $list;
	}
}
