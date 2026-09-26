<?php
/**
 * Исключения товаров и категорий для автоматических кнопок.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare;

defined( 'ABSPATH' ) || exit;

/**
 * Решает, скрыта ли кнопка сравнения у товара.
 *
 * Исключение имеет приоритет над настройками позиций: если товар прямо
 * отмечен в исключённой категории или сам исключён для этого места показа,
 * авто-кнопка не выводится. Шорткод с явным ID работает независимо от
 * исключений — это ручное решение владельца магазина.
 */
final class Visibility {

	/**
	 * Контекст показа: карточка товара.
	 *
	 * @var string
	 */
	const CONTEXT_CARD = 'card';

	/**
	 * Контекст показа: страница товара.
	 *
	 * @var string
	 */
		const CONTEXT_SINGLE = 'single';

	/**
	 * Запомненный путь запроса (одна проверка за запрос).
	 *
	 * @var string|null
	 */
	private static $request_path = null;

	/**
	 * Совпал ли запрос хотя бы с одной URL-маской.
	 *
	 * @var bool|null
	 */
	private static $mask_match = null;

	/**
	 * Разрешён ли текущий контекст показа карточек настройками.
	 *
	 * Контексты: shop, catalog, search, home, related, cart (см. Settings::SHOW_CONTEXTS).
	 *
	 * @param string               $context  Текущий контекст.
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return bool
	 */
	public static function context_allowed( string $context, array $settings ): bool {
		return in_array( $context, (array) ( $settings['show_contexts'] ?? array() ), true );
	}

	/**
	 * Разрешён ли текущий адрес настройками URL-масок.
	 *
	 * `all_except` (по умолчанию): кнопки скрываются, если адрес совпал с
	 * хотя бы одной маской. `only`: показывается, только если совпал.
	 * Пустой список масок не ограничивает показ.
	 *
	 * @param array<string, mixed> $settings Настройки плагина.
	 * @return bool
	 */
	public static function url_allowed( array $settings ): bool {
		$masks = (array) ( $settings['show_url_masks'] ?? array() );

		if ( empty( $masks ) ) {
			return true;
		}

		if ( null === self::$mask_match ) {
			self::$request_path = self::request_path();
			self::$mask_match   = self::matches_masks( self::$request_path, $masks );
		}

		$mode = (string) ( $settings['show_url_mode'] ?? 'all_except' );

		return 'only' === $mode ? self::$mask_match : ! self::$mask_match;
	}

	/**
	 * Совпал ли путь хотя бы с одной маской.
	 *
	 * Звёздочка `*` заменяет любую последовательность символов.
	 *
	 * @param string   $path  Путь запроса (без домена и строки запроса).
	 * @param string[] $masks Список масок.
	 * @return bool
	 */
	private static function matches_masks( string $path, array $masks ): bool {
		foreach ( $masks as $mask ) {
			$pattern = ltrim( (string) $mask, '/' );
			$pattern = str_replace( '\*', '.*', preg_quote( $pattern, '#' ) );

			if ( preg_match( '#^' . $pattern . '$#i', $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Путь текущего запроса без строки параметров.
	 *
	 * @return string
	 */
	private static function request_path(): string {
		// Путь используется только для сравнения с масками; экранирование не требуется.
		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$part = strtok( $request, '?' );

		return $part ? $part : '/';
	}

	/**
	 * Заблокирована ли авто-кнопка у товара в заданном месте.
	 *
	 * @param int                  $product_id ID товара.
	 * @param string               $context    Контекст показа (card или single).
	 * @param array<string, mixed> $settings   Настройки плагина.
	 * @return bool
	 */
	public static function product_blocked( int $product_id, string $context, array $settings ): bool {
		$context = in_array( $context, array( self::CONTEXT_CARD, self::CONTEXT_SINGLE ), true ) ? $context : self::CONTEXT_CARD;

		$products = (array) $settings['excluded_products'];

		if ( isset( $products[ $product_id ][ $context ] ) && true === $products[ $product_id ][ $context ] ) {
			return true;
		}

		$categories = (array) $settings['excluded_categories'];

		foreach ( Category_Model::direct_category_ids( $product_id ) as $category_id ) {
			if ( isset( $categories[ $category_id ][ $context ] ) && true === $categories[ $category_id ][ $context ] ) {
				// Исключение побеждает: хватает одной исключённой категории товара.
				return true;
			}
		}

		return false;
	}
}
