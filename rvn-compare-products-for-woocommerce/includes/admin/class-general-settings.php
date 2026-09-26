<?php
/**
 * Вкладка «General»: сохранение, сброс и поиск товаров для исключений.
 *
 * Обработчики отвечают JSON, потому что форму рисует React-приложение
 * (assets/admin/settings.js). Единственный валидатор значений —
 * Settings::sanitize(): здесь только проверка прав и nonce, а данные
 * передаются в Settings::update() как есть.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Admin;

use RVN_Compare\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Сохраняет общие настройки сравнения и ищет товары для списка исключений.
 */
final class General_Settings {

	/**
	 * Ключи, которые редактирует вкладка «General».
	 *
	 * Используется и при сохранении (чтобы чужие ключи не перезаписывались),
	 * и при сбросе вкладки к значениям по умолчанию.
	 *
	 * @var string[]
	 */
	const SCOPE = array(
		'limit_total',
		'limit_per_category',
		'compare_rule',
		'ignored_categories',
		'other_label',
		'excluded_products',
		'excluded_categories',
		'accent_color',
		'scroll_offset_top',
		'scroll_offset_bottom',
		'scroll_offset_top_mobile',
		'scroll_offset_bottom_mobile',
	);

	/**
	 * Наибольшее число товаров в ответе поиска.
	 *
	 * @var int
	 */
	const SEARCH_LIMIT = 10;

	/**
	 * Наибольшее число категорий, передаваемых в интерфейс.
	 *
	 * @var int
	 */
	const CATEGORY_LIMIT = 500;

	/**
	 * Подключает обработчики форм и поиска.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_rvn_compare_save_general', array( self::class, 'save' ) );
		add_action( 'admin_post_rvn_compare_reset_general', array( self::class, 'reset' ) );
		add_action( 'wp_ajax_rvn_compare_search_products', array( self::class, 'search_products' ) );
	}

	/**
	 * Сохраняет значения вкладки «General» и отвечает сохранённым набором.
	 *
	 * @return void
	 */
	public static function save(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => self::capability_message() ), 403 );
		}

		// Проверка nonce пишется прямо в обработчике: иначе WPCS её не видит.
		if ( ! check_ajax_referer( 'rvn_compare_save_general', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => self::nonce_message() ), 403 );
		}

		// Суперглобальный массив читается здесь, после проверки nonce:
		// вспомогательные методы получают уже готовые данные.
		$post  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- очистка выполняется ниже по каждому ключу.
		$input = self::scope_input( $post );

		if ( isset( $post['ignored_categories'] ) ) {
			$input['ignored_categories'] = self::int_list( $post['ignored_categories'] );
		}

		if ( isset( $post['other_label'] ) ) {
			$input['other_label'] = sanitize_text_field( (string) $post['other_label'] );
		}

		if ( isset( $post['excluded_products'] ) ) {
			$input['excluded_products'] = self::exclusions( $post['excluded_products'] );
		}

		if ( isset( $post['excluded_categories'] ) ) {
			$input['excluded_categories'] = self::exclusions( $post['excluded_categories'] );
		}

		$saved = Settings::update( $input );

		wp_send_json_success(
			array(
				'settings' => self::export( $saved ),
				'message'  => __( 'General settings saved.', 'rvn-compare-products-for-woocommerce' ),
			)
		);
	}

	/**
	 * Возвращает ключам вкладки «General» значения по умолчанию.
	 *
	 * Другие вкладки не затрагиваются: сбрасываются только ключи из SCOPE.
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => self::capability_message() ), 403 );
		}

		// Проверка nonce пишется прямо в обработчике: иначе WPCS её не видит.
		if ( ! check_ajax_referer( 'rvn_compare_reset_general', '_wpnonce', false ) ) {
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
				'message'  => __( 'General settings restored to defaults.', 'rvn-compare-products-for-woocommerce' ),
			)
		);
	}

	/**
	 * Ищет товары по названию или артикулу для списка исключений.
	 *
	 * @return void
	 */
	public static function search_products(): void {
		if ( ! current_user_can( Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => self::capability_message() ), 403 );
		}

		// Проверка nonce пишется прямо в обработчике: иначе WPCS её не видит.
		if ( ! check_ajax_referer( 'rvn_compare_admin', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => self::nonce_message() ), 403 );
		}

		$get  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- значение очищается ниже.
		$term = isset( $get['term'] ) ? sanitize_text_field( (string) $get['term'] ) : '';

		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'products' => array() ) );
		}

		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => self::SEARCH_LIMIT,
				'no_found_rows'  => true,
				'fields'         => 'ids',
				// Поиск по названию и артикулу: LIKE безопасен, значение экранировано ниже.
				's'              => $term,
			)
		);

		$products = array();

		foreach ( $query->posts as $post_id ) {
			$id = (int) $post_id;

			$products[] = array(
				'id'    => $id,
				'title' => self::product_title( $id ),
			);
		}

		wp_send_json_success( array( 'products' => $products ) );
	}

	/**
	 * Собирает данные для интерфейса: настройки, категории и названия товаров.
	 *
	 * @return array<string, mixed>
	 */
	public static function bootstrap(): array {
		$settings = Settings::all();

		return array(
			'settings'     => self::export( $settings ),
			'categories'   => self::categories(),
			'compareRules' => array(
				array(
					'value' => 'assigned',
					/* translators: comparison rule: tabs are the categories assigned to a product. */
					'label' => __( 'Assigned categories', 'rvn-compare-products-for-woocommerce' ),
				),
				array(
					'value' => 'top_level',
					/* translators: comparison rule: only top-level categories become tabs. */
					'label' => __( 'Top-level categories', 'rvn-compare-products-for-woocommerce' ),
				),
				array(
					'value' => 'all',
					/* translators: comparison rule: one shared tab, no category split. */
					'label' => __( 'All products in one tab', 'rvn-compare-products-for-woocommerce' ),
				),
			),
			'urls'         => array(
				'save'   => self::handler_url( 'rvn_compare_save_general' ),
				'reset'  => self::handler_url( 'rvn_compare_reset_general' ),
				'search' => admin_url( 'admin-ajax.php' ),
			),
		);
	}

	/**
	 * Собирает адрес обработчика admin-post.php вместе с nonce.
	 *
	 * Ссылка собирается вручную: `wp_nonce_url()` и `add_query_arg()` возвращают
	 * `&amp;`, а в JSON браузер отправит такой nonce в параметре с искажённым
	 * именем, и проверка не пройдёт.
	 *
	 * @param string $action Действие и nonce.
	 * @return string
	 */
	private static function handler_url( string $action ): string {
		return admin_url( 'admin-post.php' ) . '?action=' . rawurlencode( $action ) . '&_wpnonce=' . rawurlencode( (string) wp_create_nonce( $action ) );
	}

	/**
	 * Отдаёт в интерфейс только ключи вкладки «General» в безопасном виде.
	 *
	 * @param array<string, mixed> $settings Настройки.
	 * @return array<string, mixed>
	 */
	private static function export( array $settings ): array {
		$excluded_products = array();

		foreach ( (array) ( $settings['excluded_products'] ?? array() ) as $id => $flags ) {
			$id = (int) $id;

			$excluded_products[] = array(
				'id'     => $id,
				'title'  => self::product_title( $id ),
				'card'   => ! empty( $flags['card'] ),
				'single' => ! empty( $flags['single'] ),
			);
		}

		$excluded_categories = array();

		foreach ( (array) ( $settings['excluded_categories'] ?? array() ) as $id => $flags ) {
			$id = (int) $id;

			$excluded_categories[] = array(
				'id'     => $id,
				'title'  => self::category_title( $id ),
				'card'   => ! empty( $flags['card'] ),
				'single' => ! empty( $flags['single'] ),
			);
		}

		return array(
			'limit_total'                 => (int) ( $settings['limit_total'] ?? 50 ),
			'limit_per_category'          => (int) ( $settings['limit_per_category'] ?? 12 ),
			'compare_rule'                => (string) ( $settings['compare_rule'] ?? 'assigned' ),
			'ignored_categories'          => array_map( 'intval', (array) ( $settings['ignored_categories'] ?? array() ) ),
			'other_label'                 => (string) ( $settings['other_label'] ?? '' ),
			'excluded_products'           => $excluded_products,
			'excluded_categories'         => $excluded_categories,
			'accent_color'                => (string) ( $settings['accent_color'] ?? '#2563eb' ),
			'scroll_offset_top'           => (string) ( $settings['scroll_offset_top'] ?? '0px' ),
			'scroll_offset_bottom'        => (string) ( $settings['scroll_offset_bottom'] ?? '0px' ),

			// Пустая строка на вкладке означает «как на компьютере».
			'scroll_offset_top_mobile'    => (string) ( $settings['scroll_offset_top_mobile'] ?? '' ),
			'scroll_offset_bottom_mobile' => (string) ( $settings['scroll_offset_bottom_mobile'] ?? '' ),
		);
	}

	/**
	 * Список категорий товаров для выпадающих списков.
	 *
	 * @return array<int, array{id: int, label: string}>
	 */
	private static function categories(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => self::CATEGORY_LIMIT,
			)
		);

		if ( ! is_array( $terms ) ) {
			return array();
		}

		$list = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$list[] = array(
				'id'    => (int) $term->term_id,
				'label' => (string) $term->name,
			);
		}

		return $list;
	}

	/**
	 * Название товара или запасная подпись, если товар удалён.
	 *
	 * @param int $id ID товара.
	 * @return string
	 */
	private static function product_title( int $id ): string {
		$title = get_the_title( $id );

		if ( ! is_string( $title ) || '' === $title ) {
			/* translators: %d: product ID that no longer exists. */
			return sprintf( __( 'Product #%d (deleted)', 'rvn-compare-products-for-woocommerce' ), $id );
		}

		return $title;
	}

	/**
	 * Название категории или запасная подпись, если категория удалена.
	 *
	 * @param int $id ID категории.
	 * @return string
	 */
	private static function category_title( int $id ): string {
		$term = get_term( $id, 'product_cat' );

		if ( ! $term instanceof \WP_Term ) {
			/* translators: %d: category ID that no longer exists. */
			return sprintf( __( 'Category #%d (deleted)', 'rvn-compare-products-for-woocommerce' ), $id );
		}

		return (string) $term->name;
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
			'limit_total',
			'limit_per_category',
			'compare_rule',
			'accent_color',
			'scroll_offset_top',
			'scroll_offset_bottom',
			'scroll_offset_top_mobile',
			'scroll_offset_bottom_mobile',
		);

		foreach ( $keys as $key ) {
			// Значение-массив привело бы к PHP Warning — такие поля отбрасываем.
			if ( isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ) {
				$input[ $key ] = sanitize_text_field( (string) $post[ $key ] );
			}
		}

		return $input;
	}

	/**
	 * Список целых чисел (игнорируемые категории).
	 *
	 * @param mixed $raw Сырое значение поля.
	 * @return int[]
	 */
	private static function int_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$list = array();

		foreach ( $raw as $value ) {
			$id = (int) $value;

			if ( $id > 0 && ! in_array( $id, $list, true ) ) {
				$list[] = $id;
			}
		}

		return $list;
	}

	/**
	 * Карта исключений: ID => флаги «карточка» и «страница товара».
	 *
	 * @param mixed $raw Сырое значение поля.
	 * @return array<int, array{card: bool, single: bool}>
	 */
	private static function exclusions( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$map = array();

		foreach ( $raw as $id => $flags ) {
			$id    = (int) $id;
			$flags = is_array( $flags ) ? $flags : array();

			if ( $id <= 0 ) {
				continue;
			}

			$map[ $id ] = array(
				'card'   => ! empty( $flags['card'] ),
				'single' => ! empty( $flags['single'] ),
			);
		}

		return $map;
	}
}
