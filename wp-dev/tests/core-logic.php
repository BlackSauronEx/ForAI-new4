<?php
/**
 * Автотесты ядра сравнения. Запуск: wp eval-file wp-dev/tests/core-logic.php
 *
 * Создаёт собственные термины и товары, проверяет лимиты, категории, группы,
 * исключения и хранилище списков, затем удаляет всё созданное.
 * Завершается ошибкой, если хоть одна проверка провалилась.
 *
 * @package RVN_Compare
 */

namespace RVN_Compare\Tests;

use WP_CLI;
use WP_Error;

$root = dirname( __DIR__, 2 ) . '/rvn-compare-products-for-woocommerce';
require_once $root . '/includes/class-autoloader.php';
\RVN_Compare\Autoloader::register();

use RVN_Compare\Category_Model;
use RVN_Compare\Compare_Service;
use RVN_Compare\Limits;
use RVN_Compare\Settings;
use RVN_Compare\Storage;
use RVN_Compare\Table_Data;
use RVN_Compare\Visibility;

$GLOBALS['rvn_test_failures'] = 0;
$GLOBALS['rvn_test_checks']   = 0;

/**
 * Проверяет условие и выводит результат.
 *
 * @param bool   $condition Условие.
 * @param string $message   Описание проверки.
 * @return void
 */
function check( bool $condition, string $message ): void {
	$GLOBALS['rvn_test_checks']++;

	if ( $condition ) {
		WP_CLI::log( '  ok ' . $message );
		return;
	}

	$GLOBALS['rvn_test_failures']++;
	WP_CLI::log( '  XX ' . $message );
}

/**
 * Создаёт категорию тестовых данных.
 *
 * @param string   $slug   Ярлык.
 * @param string   $name   Название.
 * @param int      $parent Родитель.
 * @return int ID термина.
 */
function term( string $slug, string $name, int $parent = 0 ): int {
	$result = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug, 'parent' => $parent ) );

	if ( is_wp_error( $result ) ) {
		$existing = get_term_by( 'slug', $slug, 'product_cat' );
		return $existing ? (int) $existing->term_id : 0;
	}

	return (int) $result['term_id'];
}

/**
 * Создаёт простой товар.
 *
 * @param string   $name   Название.
 * @param int[]    $cats   Категории.
 * @param string   $status Статус.
 * @return int ID товара.
 */
function product( string $name, array $cats = array(), string $status = 'publish' ): int {
	$item = new \WC_Product_Simple();
	$item->set_name( $name );
	$item->set_regular_price( '100' );
	$item->set_status( $status );
	$item->set_category_ids( $cats );

	return $item->save();
}

// --- Тестовые данные -------------------------------------------------------
$phones  = term( 'rvt-phones', 'RVT Смартфоны' );
$android = term( 'rvt-android', 'RVT Android', $phones );
$ios     = term( 'rvt-ios', 'RVT iOS', $phones );
$sale    = term( 'rvt-sale', 'RVT Акции' );

$p_android  = product( 'RVT 1', array( $android ) );
$p_android2 = product( 'RVT 2', array( $android ) );
$p_both     = product( 'RVT 3', array( $android, $phones ) );
$p_sale     = product( 'RVT 4', array( $phones, $sale ) );
$p_default  = product( 'RVT 5', array() );
$p_draft    = product( 'RVT 6', array( $android ), 'draft' );
$p_private  = product( 'RVT 7', array( $android ), 'private' );
$p_trash    = product( 'RVT 8', array( $android ) );
wp_trash_post( $p_trash );

$backup = Settings::all();

/**
 * Записывает тестовые настройки.
 *
 * @param array<string, mixed> $overrides Изменения относительно базовых тестовых значений.
 * @return void
 */
function configure( array $overrides = array() ): void {
	Settings::update(
		array_merge(
			array(
				'limit_total'             => 4,
				'limit_per_category'      => 2,
				'compare_rule'            => 'assigned',
				'ignored_categories'      => array(),
				'category_groups'         => array(),
				'excluded_products'       => array(),
				'excluded_categories'     => array(),
				'other_label'             => '',
				'delete_data_on_uninstall' => true,
				'table_fields'            => array(),
				'meta_fields'             => array(),
			),
			$overrides
		)
	);
}

WP_CLI::log( '— Доступность товара' );
configure();
check( Compare_Service::product_available( $p_android ), 'опубликованный товар доступен' );
check( ! Compare_Service::product_available( $p_draft ), 'черновик недоступен' );
check( ! Compare_Service::product_available( $p_private ), 'приватный товар недоступен' );
check( ! Compare_Service::product_available( $p_trash ), 'товар в корзине недоступен' );
check( ! Compare_Service::product_available( 99999999 ), 'несуществующий товар недоступен' );

WP_CLI::log( '— Недоступные товары отсеиваются из списка и таблицы' );
$state = Compare_Service::get_state( null, array( $p_draft, $p_android, $p_private, $p_trash ) );
check(
	array( $p_android ) === $state['ids'],
	'состояние гостя: черновик, приватный и корзина отсеяны'
);
$table     = Table_Data::build( array( $p_android, $p_draft, $p_private, $p_trash ), Settings::all() );
$table_ids = array();
foreach ( $table['tabs'] as $tab ) {
	foreach ( $tab['columns'] as $column ) {
		$table_ids[] = (int) $column['id'];
	}
}
check( array( $p_android ) === $table_ids, 'таблица: недоступные товары не попадают в колонки' );

WP_CLI::log( '— Добавление и удаление (гость)' );
$state = Compare_Service::add( null, $p_android, array() );
check( 'added' === $state['status'] && array( $p_android ) === $state['ids'], 'добавление создаёт список' );
$state = Compare_Service::add( null, $p_android, $state['ids'] );
check( 'already' === $state['status'], 'повторное добавление — already' );
$state = Compare_Service::add( null, $p_draft, array( $p_android ) );
check( 'not_found' === $state['status'], 'черновик — not_found' );
$state = Compare_Service::remove( null, $p_android, array( $p_android ) );
check( 'removed' === $state['status'] && array() === $state['ids'], 'удаление очищает список' );

WP_CLI::log( '— Общий лимит' );
configure( array( 'limit_total' => 2, 'limit_per_category' => 2 ) );
$state = Compare_Service::add( null, $p_android2, array( $p_android, $p_both ) );
check( 'limit_total' === $state['status'], 'лишний товар не добавляется: limit_total' );
check( 2 === $state['count'], 'размер списка не вырос' );

WP_CLI::log( '— Лимит категории: железное правило' );
configure();
Compare_Service::add( null, $p_android, array() );
Compare_Service::add( null, $p_both, array( $p_android ) );
$state = Compare_Service::add( null, $p_android2, array( $p_android, $p_both ) );
check( 'limit_category' === $state['status'], 'заполненная категория блокирует добавление' );
check( isset( $state['tab']['key'] ) && 'cat:' . $android === $state['tab']['key'], 'в ответе названа вкладка-причина' );
check( '' !== $state['reason'], 'в ответе есть понятная причина' );

WP_CLI::log( '— Категории и «Прочее»' );
configure();
check( array( 'cat:' . $android ) === Category_Model::membership( $p_android, Settings::all() ), 'товар учитывается только по прямым категориям' );
$membership = Category_Model::membership( $p_both, Settings::all() );
check( in_array( 'cat:' . $android, $membership, true ) && in_array( 'cat:' . $phones, $membership, true ), 'две категории дают две вкладки' );
check( array( Category_Model::TAB_OTHER ) === Category_Model::membership( $p_default, Settings::all() ), 'товар без категорий уходит в «Прочее»' );

configure( array( 'ignored_categories' => array( $sale ) ) );
check( array( 'cat:' . $phones ) === Category_Model::membership( $p_sale, Settings::all() ), 'игнорируемая категория не создаёт вкладку' );
configure( array( 'ignored_categories' => array( $sale, $phones ) ) );
check( array( Category_Model::TAB_OTHER ) === Category_Model::membership( $p_sale, Settings::all() ), 'если остались только игнорируемые — «Прочее»' );

configure( array( 'compare_rule' => 'top_level' ) );
check( array( 'cat:' . $phones ) === Category_Model::membership( $p_android, Settings::all() ), 'правило «по верхнему уровню» сворачивает ветку' );
configure( array( 'compare_rule' => 'all' ) );
check( array( Category_Model::TAB_ALL ) === Category_Model::membership( $p_android, Settings::all() ), 'правило «без разделения» даёт одну вкладку' );

WP_CLI::log( '— Данные таблицы' );
configure();
$table = Table_Data::build( array( $p_android, $p_android2 ), Settings::all() );
check( 2 === $table['count'] && ! empty( $table['tabs'] ), 'таблица строит вкладки для доступных товаров' );
check( isset( $table['tabs'][0]['columns'][0]['title'] ), 'таблица отдаёт безопасные публичные колонки' );
check( in_array( 'price', array_column( $table['tabs'][0]['rows'], 'key' ), true ), 'таблица содержит базовое поле цены' );

WP_CLI::log( '— Группы категорий' );
configure(
	array(
		'category_groups' => array(
			array(
				'key'          => 'g1',
				'name'         => 'Смартфоны тест',
				'category_ids' => array( $android, $ios ),
			),
		),
	)
);
check( array( 'grp:g1' ) === Category_Model::membership( $p_android, Settings::all() ), 'категория из группы попадает в вкладку группы' );
check( 'Смартфоны тест' === Category_Model::label( 'grp:g1', Settings::all() ), 'группа показывает своё название' );

WP_CLI::log( '— Исключения' );
configure(
	array(
		'excluded_products'   => array( $p_android => array( 'card' => true, 'single' => false ) ),
		'excluded_categories' => array( $phones => array( 'card' => true, 'single' => true ) ),
	)
);
check( Visibility::product_blocked( $p_android, 'card', Settings::all() ), 'исключённый товар скрыт на карточке' );
check( ! Visibility::product_blocked( $p_android, 'single', Settings::all() ), 'тот же товар виден на странице товара' );
check( Visibility::product_blocked( $p_sale, 'single', Settings::all() ), 'исключение категории действует на её товары' );
check( ! Visibility::product_blocked( $p_default, 'card', Settings::all() ), 'остальные товары видимы' );

WP_CLI::log( '— Хранилище пользователя' );
configure();
$user_id = wp_create_user( 'rvn_test_user', wp_generate_password(), 'rvn-test@example.com' );

if ( ! $user_id instanceof WP_Error ) {
	$user_id = (int) $user_id;
	Storage::save_ids( $user_id, array( $p_android, $p_both, $p_android, 0, -1 ) );
	check( array( $p_android, $p_both ) === Storage::get_ids( $user_id ), 'список сохраняется без дублей и мусора' );
	update_user_option( $user_id, Storage::USER_OPTION, '{повреждено' );
	check( array() === Storage::get_ids( $user_id ), 'повреждённая запись не ломает чтение' );

	WP_CLI::log( '— Слияние списков при входе' );
	configure();
	Storage::save_ids( $user_id, array( $p_android ) );
	$state = Compare_Service::merge( $user_id, array( $p_both, $p_android, $p_sale, $p_android2, $p_draft ) );
	check( 'merged' === $state['status'], 'слияние выполняется для авторизованного' );
	check( 2 === $state['added'], 'добавлены только новые товары (2 из 5)' );
	check( 2 === $state['skipped'], 'переполненная категория и черновик пропущены (2)' );
	check( array( $p_android, $p_both, $p_sale ) === $state['ids'], 'порядок сохранён, дублей нет' );
	$state = Compare_Service::merge( $user_id, array( $p_android, $p_both, $p_sale ) );
	check( 0 === $state['added'], 'повторное слияние идемпотентно' );
	$state = Compare_Service::merge( null, array( $p_android ) );
	check( 'not_user' === $state['status'], 'для гостя слияние отклоняется' );

	WP_CLI::log( '— Понижение лимита отсекает хвост' );
	Storage::save_ids( $user_id, array( $p_android, $p_both, $p_sale ) );
	configure( array( 'limit_total' => 2 ) );
	$state = Compare_Service::get_state( $user_id );
	check( 2 === $state['count'] && array( $p_android, $p_both ) === $state['ids'], 'лишние товары отсечены с конца' );
} else {
	check( false, 'тестовый пользователь создан' );
}

// --- Уборка ----------------------------------------------------------------
configure( array_merge( $backup, array() ) );
Settings::update( $backup );

if ( isset( $user_id ) && is_int( $user_id ) ) {
	Storage::delete( $user_id );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user_id );
}

foreach ( array( $p_android, $p_android2, $p_both, $p_sale, $p_default, $p_draft, $p_private, $p_trash ) as $cleanup_id ) {
	wp_delete_post( $cleanup_id, true );
}

foreach ( array( $sale, $ios, $android, $phones ) as $cleanup_term ) {
	wp_delete_term( $cleanup_term, 'product_cat' );
}

WP_CLI::log( '' );
WP_CLI::log( sprintf( 'Проверок: %d, провалено: %d', $GLOBALS['rvn_test_checks'], $GLOBALS['rvn_test_failures'] ) );

if ( $GLOBALS['rvn_test_failures'] > 0 ) {
	WP_CLI::error( 'Автотесты ядра провалены' );
}

WP_CLI::success( 'Автотесты ядра пройдены' );
