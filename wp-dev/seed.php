<?php
/**
 * Тестовый магазин для стенда RVN Compare: категории, атрибуты и товары разных типов.
 *
 * Запускается автоматически из `bash wp-dev/stand.sh up` (wp eval-file).
 * Не входит в плагин и не попадает в ZIP.
 *
 * @package RVN_Compare
 */

if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce не активирован.' );
}

/**
 * Создаёт термин таксономии или возвращает существующий.
 *
 * @param string               $name     Название.
 * @param string               $taxonomy Таксономия.
 * @param array<string, mixed> $args     Параметры wp_insert_term.
 * @return int ID термина.
 */
function rvn_seed_term( string $name, string $taxonomy, array $args = array() ): int {
	$found = term_exists( $args['slug'] ?? $name, $taxonomy, (int) ( $args['parent'] ?? 0 ) );
	if ( is_array( $found ) ) {
		return (int) $found['term_id'];
	}
	$result = wp_insert_term( $name, $taxonomy, $args );
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( $name . ': ' . $result->get_error_message() );
	}
	return (int) $result['term_id'];
}

/**
 * Создаёт глобальный атрибут WooCommerce и регистрирует его таксономию в текущем запросе.
 *
 * @param string $label Название атрибута.
 * @param string $slug  Ярлык без префикса pa_.
 * @return array{0: int, 1: string} ID атрибута и имя таксономии.
 */
function rvn_seed_attribute( string $label, string $slug ): array {
	$id = (int) wc_attribute_taxonomy_id_by_name( $slug );
	if ( ! $id ) {
		$created = wc_create_attribute(
			array(
				'name'         => $label,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);
		if ( is_wp_error( $created ) ) {
			WP_CLI::error( $created->get_error_message() );
		}
		$id = (int) $created;
	}
	$taxonomy = wc_attribute_taxonomy_name( $slug );
	if ( ! taxonomy_exists( $taxonomy ) ) {
		register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'rewrite' => false ) );
	}
	return array( $id, $taxonomy );
}

/**
 * Готовит атрибут товара.
 *
 * @param int                    $id        ID глобального атрибута или 0 для атрибута товара.
 * @param string                 $name      Таксономия (pa_*) или название атрибута товара.
 * @param array<int, int|string> $options   ID терминов или текстовые значения.
 * @param bool                   $variation Используется для вариаций.
 * @return WC_Product_Attribute
 */
function rvn_seed_product_attribute( int $id, string $name, array $options, bool $variation = false ): WC_Product_Attribute {
	$attribute = new WC_Product_Attribute();
	$attribute->set_id( $id );
	$attribute->set_name( $name );
	$attribute->set_options( $options );
	$attribute->set_visible( true );
	$attribute->set_variation( $variation );
	return $attribute;
}

// Категории: две ветки, сквозная подборка «Акции» и категория по умолчанию WooCommerce.
$phones  = rvn_seed_term( 'Смартфоны', 'product_cat', array( 'slug' => 'smartphones' ) );
$android = rvn_seed_term( 'Android', 'product_cat', array( 'slug' => 'android', 'parent' => $phones ) );
$ios     = rvn_seed_term( 'iOS', 'product_cat', array( 'slug' => 'ios', 'parent' => $phones ) );
$laptops = rvn_seed_term( 'Ноутбуки', 'product_cat', array( 'slug' => 'laptops' ) );
$sale    = rvn_seed_term( 'Акции', 'product_cat', array( 'slug' => 'sale' ) );

// Глобальные атрибуты; у части значений есть описания — для будущих подсказок [?].
list( $color_id, $color ) = rvn_seed_attribute( 'Цвет', 'color' );
list( $memory_id, $memory ) = rvn_seed_attribute( 'Встроенная память', 'memory' );
list( $screen_id, $screen ) = rvn_seed_attribute( 'Диагональ экрана', 'screen' );
list( $cpu_id, $cpu ) = rvn_seed_attribute( 'Процессор', 'cpu' );

$terms = array(
	'black' => rvn_seed_term( 'Чёрный', $color, array( 'slug' => 'black', 'description' => 'Глубокий чёрный цвет с матовым покрытием.' ) ),
	'white' => rvn_seed_term( 'Белый', $color, array( 'slug' => 'white' ) ),
	'red'   => rvn_seed_term( 'Красный', $color, array( 'slug' => 'red', 'description' => 'Яркий красный, как маковое поле.' ) ),
	'blue'  => rvn_seed_term( 'Синий', $color, array( 'slug' => 'blue' ) ),
	'128'   => rvn_seed_term( '128 ГБ', $memory, array( 'slug' => '128gb' ) ),
	'256'   => rvn_seed_term( '256 ГБ', $memory, array( 'slug' => '256gb', 'description' => 'Около 60 000 фотографий.' ) ),
	'61'    => rvn_seed_term( '6,1″', $screen, array( 'slug' => '6-1' ) ),
	'67'    => rvn_seed_term( '6,7″', $screen, array( 'slug' => '6-7' ) ),
	'156'   => rvn_seed_term( '15,6″', $screen, array( 'slug' => '15-6' ) ),
	'i5'    => rvn_seed_term( 'Core i5', $cpu, array( 'slug' => 'core-i5' ) ),
	'r7'    => rvn_seed_term( 'Ryzen 7', $cpu, array( 'slug' => 'ryzen-7' ) ),
);

$created = array();

/**
 * Сохраняет простой товар с типовыми полями.
 *
 * @param array<string, mixed> $data Поля товара.
 * @return int ID товара.
 */
function rvn_seed_simple( array $data ): int {
	$product = new WC_Product_Simple();
	$product->set_name( $data['name'] );
	$product->set_status( $data['status'] ?? 'publish' );
	$product->set_regular_price( (string) $data['price'] );
	if ( isset( $data['sale'] ) ) {
		$product->set_sale_price( (string) $data['sale'] );
	}
	$product->set_sku( $data['sku'] ?? '' );
	$product->set_category_ids( $data['cats'] ?? array() );
	$product->set_attributes( $data['attributes'] ?? array() );
	$product->set_short_description( $data['short'] ?? '' );
	$product->set_description( $data['description'] ?? '' );
	$product->set_weight( $data['weight'] ?? '' );
	$product->set_length( $data['length'] ?? '' );
	$product->set_width( $data['width'] ?? '' );
	$product->set_height( $data['height'] ?? '' );
	$product->set_stock_status( $data['stock'] ?? 'instock' );
	return $product->save();
}

$created[] = rvn_seed_simple(
	array(
		'name'       => 'Смартфон Альфа 128 ГБ',
		'price'      => 24990,
		'sku'        => 'ALPHA-128',
		'cats'       => array( $android ),
		'short'      => 'Компактный смартфон на Android.',
		'weight'     => '0.18',
		'length'     => '147',
		'width'      => '71',
		'height'     => '8',
		'attributes' => array(
			rvn_seed_product_attribute( $color_id, $color, array( $terms['black'] ) ),
			rvn_seed_product_attribute( $memory_id, $memory, array( $terms['128'] ) ),
			rvn_seed_product_attribute( $screen_id, $screen, array( $terms['61'] ) ),
			rvn_seed_product_attribute( 0, 'Гарантия', array( '12 месяцев' ) ),
		),
	)
);

$created[] = rvn_seed_simple(
	array(
		'name'       => 'Смартфон Бета Про Макс 256 ГБ с очень длинным названием для проверки переноса строк в таблице',
		'price'      => 89990,
		'sale'       => 84990,
		'sku'        => 'BETA-PRO-256',
		'cats'       => array( $ios ),
		'short'      => 'Флагман с большим экраном.',
		'weight'     => '0.22',
		'attributes' => array(
			rvn_seed_product_attribute( $color_id, $color, array( $terms['white'], $terms['blue'] ) ),
			rvn_seed_product_attribute( $memory_id, $memory, array( $terms['256'] ) ),
			rvn_seed_product_attribute( $screen_id, $screen, array( $terms['67'] ) ),
		),
	)
);

// Вариативный товар: цвет и память — вариации.
$variable = new WC_Product_Variable();
$variable->set_name( 'Смартфон Гамма' );
$variable->set_sku( 'GAMMA' );
$variable->set_category_ids( array( $android ) );
$variable->set_short_description( 'Смартфон с выбором цвета и памяти.' );
$variable->set_attributes(
	array(
		rvn_seed_product_attribute( $color_id, $color, array( $terms['red'], $terms['blue'] ), true ),
		rvn_seed_product_attribute( $memory_id, $memory, array( $terms['128'], $terms['256'] ), true ),
		rvn_seed_product_attribute( $screen_id, $screen, array( $terms['61'] ) ),
	)
);
$variable_id = $variable->save();
foreach ( array( array( 'red', '128gb', 29990 ), array( 'red', '256gb', 33990 ), array( 'blue', '128gb', 29990 ), array( 'blue', '256gb', 33990 ) ) as $combo ) {
	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $variable_id );
	$variation->set_attributes( array( $color => $combo[0], $memory => $combo[1] ) );
	$variation->set_regular_price( (string) $combo[2] );
	$variation->save();
}
WC_Product_Variable::sync( $variable_id );
$created[] = $variable_id;

$created[] = rvn_seed_simple(
	array(
		'name'  => 'Смартфон Дельта (нет в наличии)',
		'price' => 19990,
		'sku'   => 'DELTA',
		'cats'  => array( $android ),
		'stock' => 'outofstock',
	)
);
$created[] = rvn_seed_simple(
	array(
		'name'  => 'Смартфон Эпсилон (в «Акциях»)',
		'price' => 21990,
		'sale'  => 17990,
		'sku'   => 'EPSILON',
		'cats'  => array( $phones, $sale ),
	)
);
$created[] = rvn_seed_simple(
	array(
		'name'  => 'Смартфон Йота (в двух категориях)',
		'price' => 27990,
		'sku'   => 'IOTA',
		'cats'  => array( $phones, $android ),
	)
);
$created[] = rvn_seed_simple(
	array(
		'name'        => 'Ноутбук Зета 15,6″',
		'price'       => 74990,
		'sku'         => 'ZETA',
		'cats'        => array( $laptops ),
		'description' => 'Полное описание ноутбука для проверки длинного текста в таблице сравнения.',
		'attributes'  => array(
			rvn_seed_product_attribute( $screen_id, $screen, array( $terms['156'] ) ),
			rvn_seed_product_attribute( $cpu_id, $cpu, array( $terms['r7'] ) ),
		),
	)
);

$external = new WC_Product_External();
$external->set_name( 'Ноутбук Эта (внешний магазин)' );
$external->set_regular_price( '69990' );
$external->set_product_url( 'https://example.com/laptop-eta' );
$external->set_button_text( 'Купить у партнёра' );
$external->set_category_ids( array( $laptops ) );
$created[] = $external->save();

$created[] = rvn_seed_simple(
	array(
		'name'  => 'Товар без категории',
		'price' => 990,
		'sku'   => 'NO-CAT',
	)
);
$created[] = rvn_seed_simple(
	array(
		'name'   => 'Черновик: Смартфон Тета',
		'price'  => 15990,
		'sku'    => 'THETA-DRAFT',
		'cats'   => array( $android ),
		'status' => 'draft',
	)
);
$created[] = rvn_seed_simple(
	array(
		'name'   => 'Личный товар (private)',
		'price'  => 1990,
		'sku'    => 'PRIVATE',
		'cats'   => array( $android ),
		'status' => 'private',
	)
);

$grouped = new WC_Product_Grouped();
$grouped->set_name( 'Набор: два смартфона' );
$grouped->set_children( array( $created[0], $created[5] ) );
$grouped->set_category_ids( array( $phones ) );
$created[] = $grouped->save();

// Страница с шорткодами: используется браузерными проверками счётчиков и кнопок.
$rvn_shortcodes_page = get_page_by_path( 'rvn-shortcodes' );

if ( ! $rvn_shortcodes_page ) {
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'RVN шорткоды',
			'post_name'    => 'rvn-shortcodes',
			'post_content' => '[rvn-compare-counter-button][rvn-compare-counter text="в списке" text_position="after"][rvn-compare-progress][rvn-compare-clear]',
		)
	);
}

WP_CLI::success( sprintf( 'Тестовый магазин: %d товаров, 5 категорий, 4 атрибута.', count( $created ) ) );
