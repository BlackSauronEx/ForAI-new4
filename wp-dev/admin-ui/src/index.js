/**
 * RVN Compare — интерфейс настроек 0.5.0, вкладка «General» (раунд B).
 *
 * Монтируется только в #rvn-compare-admin-root на странице «Compare».
 * Пакеты WordPress (@wordpress/element, @wordpress/components) берутся
 * из ядра через dependency extraction и в бандл не входят.
 * Все строки интерфейса приходят из PHP (window.rvnCompareAdminData.i18n)
 * уже переведёнными — wp.i18n здесь не используется, как и в скриптах витрины.
 *
 * Единственный валидатор значений — Settings::sanitize() на стороне PHP:
 * интерфейс отправляет данные как есть и рисует то, что вернул сервер.
 */
import { createElement, createRoot, render, useState, useEffect, useRef } from '@wordpress/element';
import { Button, CheckboxControl, Notice, SelectControl, Spinner, TextareaControl, TextControl } from '@wordpress/components';

const data = window.rvnCompareAdminData || {};
const i18n = data.i18n || {};
const urls = data.urls || {};
const categories = Array.isArray( data.categories ) ? data.categories : [];
const rules = Array.isArray( data.rules ) ? data.rules : [];
const initial = data.settings || {};

function text( key, fallback ) {
	return typeof i18n[ key ] === 'string' && '' !== i18n[ key ] ? i18n[ key ] : fallback;
}

/**
 * Отправляет форму на admin-post.php и возвращает сохранённые настройки.
 *
 * @param {string} url    Адрес обработчика (уже содержит nonce).
 * @param {Object} fields Поля формы.
 * @return {Promise<Object>} Ответ сервера.
 */
async function postForm( url, fields ) {
	const body = new FormData();

	Object.keys( fields ).forEach( function eachField( name ) {
		const value = fields[ name ];

		if ( value === null || value === undefined ) {
			return;
		}

		if ( Array.isArray( value ) ) {
			value.forEach( function eachItem( item ) {
				body.append( name + '[]', item );
			} );
			return;
		}

		body.append( name, value );
	} );

	const response = await fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		body,
		headers: { 'X-Requested-With': 'XMLHttpRequest' },
	} );

	const json = await response.json();

	if ( ! response.ok || ! json || json.success !== true ) {
		throw new Error( ( json && json.data && json.data.message ) || text( 'errorGeneric', 'Request failed.' ) );
	}

	return json.data;
}

/**
 * Собирает поля исключений для отправки: id[card], id[single].
 *
 * @param {Array}  items  Список исключений.
 * @param {string} prefix Имя поля.
 * @return {Object} Поля формы.
 */
function exclusionFields( items, prefix ) {
	const fields = {};

	items.forEach( function eachItem( item ) {
		fields[ prefix + '[' + item.id + '][card]' ] = item.card ? '1' : '';
		fields[ prefix + '[' + item.id + '][single]' ] = item.single ? '1' : '';
	} );

	return fields;
}

/**
 * Строка списка исключений: название, два контекста и кнопка удаления.
 */
function ExclusionRow( props ) {
	const item = props.item;

	return createElement(
		'tr',
		null,
		createElement( 'th', { scope: 'row' }, item.title ),
		createElement(
			'td',
			null,
			createElement( CheckboxControl, {
				checked: !! item.card,
				label: text( 'onCard', 'Product cards' ),
				onChange: function onCard( value ) {
					props.onChange( item.id, 'card', value );
				},
			} )
		),
		createElement(
			'td',
			null,
			createElement( CheckboxControl, {
				checked: !! item.single,
				label: text( 'onSingle', 'Product page' ),
				onChange: function onSingle( value ) {
					props.onChange( item.id, 'single', value );
				},
			} )
		),
		createElement(
			'td',
			null,
			createElement(
				Button,
				{
					variant: 'secondary',
					isSmall: true,
					onClick: function onRemove() {
						props.onRemove( item.id );
					},
				},
				text( 'remove', 'Remove' )
			)
		)
	);
}

/**
 * Блок исключений: список + поиск товаров (для категорий — выпадающий список).
 */
function ExclusionBlock( props ) {
	const items = props.items;
	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const timer = useRef( null );

	useEffect( function searchEffect() {
		return function cleanup() {
			if ( timer.current ) {
				clearTimeout( timer.current );
			}
		};
	}, [] );

	function runSearch( value ) {
		setQuery( value );

		if ( timer.current ) {
			clearTimeout( timer.current );
		}

		if ( ! props.search || value.length < 2 ) {
			setResults( [] );
			return;
		}

		setBusy( true );

		timer.current = setTimeout( function doSearch() {
			const request = urls.search + '?action=rvn_compare_search_products&_wpnonce=' +
				encodeURIComponent( data.nonce || '' ) + '&term=' + encodeURIComponent( value );

			fetch( request, { credentials: 'same-origin' } )
				.then( function toJson( response ) {
					return response.json();
				} )
				.then( function useJson( json ) {
					setResults( json && json.success && json.data ? json.data.products : [] );
					setBusy( false );
				} )
				.catch( function onError() {
					setResults( [] );
					setBusy( false );
				} );
		}, 400 );
	}

	function toggle( id, flag, value ) {
		props.onChange(
			items.map( function mapItem( item ) {
				return item.id === id ? Object.assign( {}, item, { [ flag ]: value } ) : item;
			} )
		);
	}

	function remove( id ) {
		props.onChange( items.filter( function filterItem( item ) {
			return item.id !== id;
		} ) );
	}

	function add( id, title ) {
		if ( items.some( function hasItem( item ) {
			return item.id === id;
		} ) ) {
			return;
		}

		props.onChange( items.concat( [ { id, title, card: true, single: true } ] ) );
		setResults( [] );
		setQuery( '' );
	}

	return createElement(
		'div',
		{ className: 'rvn-compare-admin-block' },
		createElement( 'h3', null, props.title ),
		createElement( 'p', { className: 'description' }, props.help ),
		props.search
			? createElement(
				'div',
				{ className: 'rvn-compare-admin-search' },
				createElement(
					'div',
					{ 'data-testid': 'rvn-product-search' },
					createElement( TextControl, {
						value: query,
						placeholder: text( 'searchPlaceholder', 'Search products…' ),
						onChange: runSearch,
					} )
				),
				busy ? createElement( Spinner, null ) : null,
				results.length
					? createElement(
						'ul',
						{ className: 'rvn-compare-admin-results' },
						results.map( function mapResult( product ) {
							return createElement(
								'li',
								{ key: product.id },
								createElement(
									Button,
									{
										variant: 'link',
										onClick: function onPick() {
											add( product.id, product.title );
										},
									},
									text( 'add', 'Add' ) + ': ' + product.title
								)
							);
						} )
					)
					: null,
				! busy && query.length >= 2 && ! results.length
					? createElement( 'p', { className: 'description' }, text( 'searchEmpty', 'Nothing found.' ) )
					: null
			)
			: createElement(
				SelectControl,
				{
					value: '',
					options: [ { value: '', label: text( 'add', 'Add' ) + '…' } ].concat(
						categories.map( function mapCategory( category ) {
							return { value: String( category.id ), label: category.label };
						} )
					),
					onChange: function onPick( value ) {
						if ( ! value ) {
							return;
						}
						const found = categories.filter( function filterCategory( category ) {
							return String( category.id ) === value;
						} );
						add( Number( value ), found.length ? found[ 0 ].label : value );
					},
				}
			),
		items.length
			? createElement(
				'table',
				{ className: 'widefat striped rvn-compare-admin-exclusions' },
				createElement(
					'thead',
					null,
					createElement(
						'tr',
						null,
						createElement( 'th', { scope: 'col' }, props.itemColumn ),
						createElement( 'th', { scope: 'col' }, text( 'onCard', 'Product cards' ) ),
						createElement( 'th', { scope: 'col' }, text( 'onSingle', 'Product page' ) ),
						createElement( 'th', { scope: 'col' }, '' )
					)
				),
				createElement(
					'tbody',
					null,
					items.map( function mapItem( item ) {
						return createElement( ExclusionRow, {
							key: item.id,
							item,
							onChange: toggle,
							onRemove: remove,
						} );
					} )
				)
			)
			: createElement( 'p', { className: 'description' }, text( 'listEmpty', 'The list is empty.' ) )
	);
}

/**
 * Вкладка «General»: лимиты, правило сравнения, исключения, цвет и отступы.
 */
function GeneralPanel() {
	const [ form, setForm ] = useState( initial );
	const [ notice, setNotice ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	function set( key, value ) {
		setForm( Object.assign( {}, form, { [ key ]: value } ) );
	}

	function submit( url, fields, pending ) {
		setBusy( true );
		setNotice( null );

		postForm( url, fields )
			.then( function done( payload ) {
				setForm( payload.settings );
				setNotice( { status: 'success', text: payload.message } );
				setBusy( false );
			} )
			.catch( function failed( error ) {
				setNotice( { status: 'error', text: error.message } );
				setBusy( false );
			} );

		return pending;
	}

	function save() {
		submit(
			urls.save,
			Object.assign(
				{
					limit_total: form.limit_total,
					limit_per_category: form.limit_per_category,
					compare_rule: form.compare_rule,
					other_label: form.other_label,
					accent_color: form.accent_color,
				scroll_offset_top: form.scroll_offset_top,
				scroll_offset_bottom: form.scroll_offset_bottom,
				scroll_offset_top_mobile: form.scroll_offset_top_mobile,
				scroll_offset_bottom_mobile: form.scroll_offset_bottom_mobile,
					'ignored_categories[]': ( form.ignored_categories || [] ).map( String ),
				},
				exclusionFields( form.excluded_products || [], 'excluded_products' ),
				exclusionFields( form.excluded_categories || [], 'excluded_categories' )
			),
			null
		);
	}

	function resetTab() {
		if ( ! window.confirm( text( 'resetConfirm', 'Reset this tab?' ) ) ) {
			return;
		}

		submit( urls.reset, {}, null );
	}

	return createElement(
		'div',
		{ className: 'rvn-compare-admin-general', 'data-rvn-panel': 'general' },
		notice
			? createElement( Notice, { status: notice.status, isDismissible: false }, notice.text )
			: null,

		createElement( 'h2', null, text( 'limitsHeading', 'Limits' ) ),
		createElement(
		'div',
		{ 'data-testid': 'rvn-limit-total' },
		createElement( TextControl, {
					label: text( 'limitTotal', 'Total limit' ),
					help: text( 'limitTotalHelp', 'How many products a visitor can compare at once.' ),
					type: 'number',
					min: 1,
					max: 50,
					value: form.limit_total,
					onChange: function onTotal( value ) {
						set( 'limit_total', value );
					},
				} )
	),
		createElement(
		'div',
		{ 'data-testid': 'rvn-limit-category' },
		createElement( TextControl, {
					label: text( 'limitCategory', 'Per-tab limit' ),
					help: text( 'limitCategoryHelp', 'It never exceeds the total limit.' ),
					type: 'number',
					min: 1,
					max: 50,
					value: form.limit_per_category,
					onChange: function onCategory( value ) {
						set( 'limit_per_category', value );
					},
				} )
	),

		createElement( 'h2', null, text( 'rulesHeading', 'Comparison rule' ) ),
		createElement(
		'div',
		{ 'data-testid': 'rvn-compare-rule' },
		createElement( SelectControl, {
					label: text( 'compareRule', 'How products are split into tabs' ),
					help: text( 'compareRuleHelp', 'Tabs group comparable products.' ),
					value: form.compare_rule,
					options: rules.map( function mapRule( rule ) {
						return { value: rule.value, label: rule.label };
					} ),
					onChange: function onRule( value ) {
						set( 'compare_rule', value );
					},
				} )
	),
		createElement(
		'div',
		{ 'data-testid': 'rvn-ignored-categories' },
		createElement( SelectControl, {
					label: text( 'ignoredCategories', 'Ignored categories' ),
					help: text( 'ignoredHelp', 'These categories never become tabs.' ),
					multiple: true,
					size: 6,
					value: ( form.ignored_categories || [] ).map( String ),
					options: categories.map( function mapCategory( category ) {
						return { value: String( category.id ), label: category.label };
					} ),
					onChange: function onIgnored( value ) {
						set( 'ignored_categories', value );
					},
				} )
	),
		createElement(
		'div',
		{ 'data-testid': 'rvn-other-label' },
		createElement( TextControl, {
					label: text( 'otherLabel', 'Name of the “Other” tab' ),
					help: text( 'otherLabelHelp', 'Leave blank for the built-in translation.' ),
					maxLength: 80,
					value: form.other_label,
					onChange: function onLabel( value ) {
						set( 'other_label', value );
					},
				} )
	),

		createElement( 'h2', null, text( 'exclusionsHeading', 'Exclusions for automatic buttons' ) ),
		createElement( 'p', { className: 'description' }, text( 'exclusionsHelp', 'Shortcodes still work there.' ) ),
		createElement( ExclusionBlock, {
			title: text( 'excludedProducts', 'Excluded products' ),
			help: '',
			itemColumn: text( 'excludedProducts', 'Excluded products' ),
			search: true,
			items: form.excluded_products || [],
			onChange: function onProducts( value ) {
				set( 'excluded_products', value );
			},
		} ),
		createElement( ExclusionBlock, {
			title: text( 'excludedCategories', 'Excluded categories' ),
			help: '',
			itemColumn: text( 'excludedCategories', 'Excluded categories' ),
			search: false,
			items: form.excluded_categories || [],
			onChange: function onCategories( value ) {
				set( 'excluded_categories', value );
			},
		} ),

		createElement( 'h2', null, text( 'lookHeading', 'Look and offsets' ) ),
		createElement(
		'div',
		{ 'data-testid': 'rvn-accent-color' },
		createElement( TextControl, {
					label: text( 'accentColor', 'Accent color' ),
					help: text( 'accentColorHelp', 'Buttons and highlights are derived from it.' ),
					type: 'color',
					value: form.accent_color,
					onChange: function onColor( value ) {
						set( 'accent_color', value );
					},
				} )
	),
		createElement(
		'div',
		{ 'data-testid': 'rvn-offset-top' },
		createElement( TextControl, {
					label: text( 'offsetTop', 'Top offset' ),
					help: text( 'offsetHelp', 'For example 80px or 4rem.' ),
					value: form.scroll_offset_top,
					onChange: function onTop( value ) {
						set( 'scroll_offset_top', value );
					},
				} )
	),
		createElement(
		'div',
		{ 'data-testid': 'rvn-offset-bottom' },
		createElement( TextControl, {
					label: text( 'offsetBottom', 'Bottom offset' ),
					help: text( 'offsetHelp', 'For example 80px or 4rem.' ),
					value: form.scroll_offset_bottom,
					onChange: function onBottom( value ) {
						set( 'scroll_offset_bottom', value );
					},
				} )
		),

		// Отступы для телефона: пустое поле означает «как на компьютере».
		createElement(
			'div',
			{ 'data-testid': 'rvn-offset-top-mobile' },
			createElement( TextControl, {
				label: text( 'offsetTopMobile', 'Top offset on the phone' ),
				help: text( 'offsetMobileHelp', 'Leave blank to use the computer value.' ),
				value: form.scroll_offset_top_mobile,
				onChange: function onTopMobile( value ) {
					set( 'scroll_offset_top_mobile', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-offset-bottom-mobile' },
			createElement( TextControl, {
				label: text( 'offsetBottomMobile', 'Bottom offset on the phone' ),
				help: text( 'offsetMobileHelp', 'Leave blank to use the computer value.' ),
				value: form.scroll_offset_bottom_mobile,
				onChange: function onBottomMobile( value ) {
					set( 'scroll_offset_bottom_mobile', value );
				},
			} )
		),

		createElement(
			'p',
			{ className: 'rvn-compare-admin-actions' },
			createElement(
				Button,
				{
					variant: 'primary',
					isBusy: busy,
					disabled: busy,
					'data-testid': 'rvn-save-general',
					onClick: save,
				},
				text( 'save', 'Save changes' )
			),
			createElement(
				Button,
				{
					variant: 'secondary',
					disabled: busy,
					'data-testid': 'rvn-reset-general',
					onClick: resetTab,
				},
				text( 'resetTab', 'Reset this tab' )
			)
		)
	);
}

/**
 * Вкладка «Кнопки и уведомления»: позиции, режимы, тексты, поведение,
 * иконка, «где показывать» и уведомления (позиции, длительность, шаблоны).
 */
function ButtonsPanel() {
	const buttons = data.buttons || {};
	const initialButtons = buttons.settings || {};
	const positions = Array.isArray( buttons.positions ) ? buttons.positions : [];
	const modes = Array.isArray( buttons.modes ) ? buttons.modes : [];
	const modesWithInherit = modes.concat( Array.isArray( buttons.modeInherit ) ? buttons.modeInherit : [] );
	const secondClickActions = Array.isArray( buttons.secondClickActions ) ? buttons.secondClickActions : [];
	const iconModes = Array.isArray( buttons.iconModes ) ? buttons.iconModes : [];
	const contexts = Array.isArray( buttons.contexts ) ? buttons.contexts : [];
	const urlModes = Array.isArray( buttons.urlModes ) ? buttons.urlModes : [];
	const toastPositions = Array.isArray( buttons.toastPositions ) ? buttons.toastPositions : [];
	const panelUrls = buttons.urls || {};

	// Шаблоны уведомлений. Пустое поле означает встроенный перевод; доступные
	// подстановки перечислены в подсказке к каждому полю.
	const placeholders = Array.isArray( buttons.toastPlaceholders )
		? buttons.toastPlaceholders.join( ' ' )
		: '{product} {category} {count} {limit}';

	const toastTemplates = [
		{
			key: 'toast_tpl_added',
			testid: 'rvn-toast-tpl-added',
			label: text( 'toastTplAdded', 'When a product is added' ),
			help: placeholders,
		},
		{
			key: 'toast_tpl_removed',
			testid: 'rvn-toast-tpl-removed',
			label: text( 'toastTplRemoved', 'When a product is removed' ),
			help: placeholders,
		},
		{
			key: 'toast_tpl_limit',
			testid: 'rvn-toast-tpl-limit',
			label: text( 'toastTplLimit', 'When the list is full' ),
			help: placeholders,
		},
		{
			key: 'toast_tpl_limit_tab',
			testid: 'rvn-toast-tpl-limit-tab',
			label: text( 'toastTplLimitTab', 'When the category tab is full' ),
			help: placeholders,
		},
	];

	const [ form, setForm ] = useState( initialButtons );
	const [ notice, setNotice ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	function set( key, value ) {
		setForm( Object.assign( {}, form, { [ key ]: value } ) );
	}

	function toggleContext( value ) {
		const list = Array.isArray( form.show_contexts ) ? form.show_contexts : [];

		set(
			'show_contexts',
			list.indexOf( value ) === -1 ? list.concat( [ value ] ) : list.filter( function filterContext( item ) {
				return item !== value;
			} )
		);
	}

	function submit( url, fields ) {
		setBusy( true );
		setNotice( null );

		postForm( url, fields )
			.then( function done( payload ) {
				setForm( payload.settings );
				setNotice( { status: 'success', text: payload.message } );
				setBusy( false );
			} )
			.catch( function failed( error ) {
				setNotice( { status: 'error', text: error.message } );
				setBusy( false );
			} );
	}

	function save() {
		submit(
			panelUrls.save,
			{
				card_position: form.card_position,
				single_position: form.single_position,
				card_mode: form.card_mode,
				card_mode_mobile: form.card_mode_mobile,
				single_mode: form.single_mode,
				single_mode_mobile: form.single_mode_mobile,
				button_label: form.button_label,
				button_in_label: form.button_in_label,
				second_click_action: form.second_click_action,
				button_icon: form.button_icon,
				button_icon_emoji: form.button_icon_emoji,
				show_url_mode: form.show_url_mode,
				show_url_masks: form.show_url_masks,
				toast_position: form.toast_position,
				toast_position_mobile: form.toast_position_mobile,
				toast_duration: form.toast_duration,
				toast_tpl_added: form.toast_tpl_added,
				toast_tpl_removed: form.toast_tpl_removed,
				toast_tpl_limit: form.toast_tpl_limit,
				toast_tpl_limit_tab: form.toast_tpl_limit_tab,
				'show_contexts[]': Array.isArray( form.show_contexts ) ? form.show_contexts : [],
			}
		);
	}

	function resetTab() {
		if ( ! window.confirm( text( 'resetConfirm', 'Reset this tab?' ) ) ) {
			return;
		}

		submit( panelUrls.reset, {} );
	}

	return createElement(
		'div',
		{ className: 'rvn-compare-admin-buttons', 'data-rvn-panel': 'buttons' },
		notice
			? createElement( Notice, { status: notice.status, isDismissible: false }, notice.text )
			: null,

		createElement( 'h2', null, text( 'positionsHeading', 'Button position' ) ),
		createElement( 'p', { className: 'description' }, text( 'positionsHelp', '' ) ),
		createElement(
			'div',
			{ 'data-testid': 'rvn-card-position' },
			createElement( SelectControl, {
				label: text( 'cardPosition', 'On product cards' ),
				value: form.card_position,
				options: positions,
				onChange: function onCardPosition( value ) {
					set( 'card_position', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-single-position' },
			createElement( SelectControl, {
				label: text( 'singlePosition', 'On the product page' ),
				value: form.single_position,
				options: positions,
				onChange: function onSinglePosition( value ) {
					set( 'single_position', value );
				},
			} )
		),

		createElement( 'h2', null, text( 'modesHeading', 'Button mode' ) ),
		createElement( 'p', { className: 'description' }, text( 'modesHelp', '' ) ),
		createElement(
			'div',
			{ 'data-testid': 'rvn-card-mode' },
			createElement( SelectControl, {
				label: text( 'cardMode', 'Cards — computer' ),
				value: form.card_mode,
				options: modes,
				onChange: function onCardMode( value ) {
					set( 'card_mode', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-card-mode-mobile' },
			createElement( SelectControl, {
				label: text( 'cardModeMobile', 'Cards — phone' ),
				value: form.card_mode_mobile,
				options: modes,
				onChange: function onCardModeMobile( value ) {
					set( 'card_mode_mobile', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-single-mode' },
			createElement( SelectControl, {
				label: text( 'singleMode', 'Product page — computer' ),
				value: form.single_mode,
				options: modes,
				onChange: function onSingleMode( value ) {
					set( 'single_mode', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-single-mode-mobile' },
			createElement( SelectControl, {
				label: text( 'singleModeMobile', 'Product page — phone' ),
				value: form.single_mode_mobile,
				options: modesWithInherit,
				onChange: function onSingleModeMobile( value ) {
					set( 'single_mode_mobile', value );
				},
			} )
		),

		createElement( 'h2', null, text( 'textsHeading', 'Button texts' ) ),
		createElement(
			'div',
			{ 'data-testid': 'rvn-button-label' },
			createElement( TextControl, {
				label: text( 'buttonLabel', '“Compare” state' ),
				help: text( 'emptyMeansTranslation', 'Leave empty to use the built-in translation.' ),
				maxLength: 60,
				value: form.button_label,
				onChange: function onLabel( value ) {
					set( 'button_label', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-button-in-label' },
			createElement( TextControl, {
				label: text( 'buttonInLabel', '“In comparison” state' ),
				help: text( 'emptyMeansTranslation', 'Leave empty to use the built-in translation.' ),
				maxLength: 60,
				value: form.button_in_label,
				onChange: function onInLabel( value ) {
					set( 'button_in_label', value );
				},
			} )
		),

		createElement( 'h2', null, text( 'behaviorHeading', 'Behavior' ) ),
		createElement(
			'div',
			{ 'data-testid': 'rvn-second-click' },
			createElement( SelectControl, {
				label: text( 'secondClick', 'When a visitor clicks an active button' ),
				help: text( 'secondClickHelp', '' ),
				value: form.second_click_action,
				options: secondClickActions,
				onChange: function onSecondClick( value ) {
					set( 'second_click_action', value );
				},
			} )
		),

		createElement( 'h2', null, text( 'iconHeading', 'Button icon' ) ),
		createElement(
			'div',
			{ 'data-testid': 'rvn-button-icon' },
			createElement( SelectControl, {
				label: text( 'buttonIcon', 'Icon' ),
				help: text( 'iconHelp', '' ),
				value: form.button_icon,
				options: iconModes,
				onChange: function onIcon( value ) {
					set( 'button_icon', value );
				},
			} )
		),
		form.button_icon === 'emoji'
			? createElement(
				'div',
				{ 'data-testid': 'rvn-button-icon-emoji' },
				createElement( TextControl, {
					label: text( 'iconEmojiValue', 'Your emoji (one symbol)' ),
					help: text( 'iconEmojiHelp', '' ),
					value: form.button_icon_emoji,
					onChange: function onEmoji( value ) {
						set( 'button_icon_emoji', value );
					},
				} )
			)
			: null,

		createElement( 'h2', null, text( 'whereHeading', 'Where to show the automatic button' ) ),
		createElement( 'p', { className: 'description' }, text( 'whereHelp', '' ) ),
		createElement(
			'div',
			{ 'data-testid': 'rvn-show-contexts' },
			contexts.map( function mapContext( context ) {
				const checked = ( form.show_contexts || [] ).indexOf( context.value ) !== -1;

				// Обёртка обязана быть div: CheckboxControl рендерит div,
				// и внутри <p> браузер и React ругаются на вложенность DOM.
				return createElement(
					'div',
					{ key: context.value, className: 'rvn-compare-admin-context' },
					createElement( CheckboxControl, {
						checked: checked,
						label: context.label,
						onChange: function onContext() {
							toggleContext( context.value );
						},
					} )
				);
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-url-mode' },
			createElement( SelectControl, {
				label: text( 'urlRestriction', 'Page restrictions by URL' ),
				value: form.show_url_mode,
				options: urlModes,
				onChange: function onUrlMode( value ) {
					set( 'show_url_mode', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-url-masks' },
			createElement( TextareaControl, {
				label: text( 'urlMasks', 'Page masks, one per line' ),
				help: text( 'urlMasksHelp', '' ),
				rows: 4,
				value: form.show_url_masks,
				onChange: function onMasks( value ) {
					set( 'show_url_masks', value );
				},
			} )
		),

		createElement( 'h2', null, text( 'toastsHeading', 'Notifications (toasts)' ) ),
		createElement( 'p', { className: 'description' }, text( 'toastsHelp', '' ) ),
		createElement(
			'div',
			{ 'data-testid': 'rvn-toast-position' },
			createElement( SelectControl, {
				label: text( 'toastPosition', 'Position on the computer' ),
				value: form.toast_position,
				options: toastPositions,
				onChange: function onToastPosition( value ) {
					set( 'toast_position', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-toast-position-mobile' },
			createElement( SelectControl, {
				label: text( 'toastPositionMobile', 'Position on the phone' ),
				value: form.toast_position_mobile,
				options: toastPositions,
				onChange: function onToastPositionMobile( value ) {
					set( 'toast_position_mobile', value );
				},
			} )
		),
		createElement(
			'div',
			{ 'data-testid': 'rvn-toast-duration' },
			createElement( TextControl, {
				label: text( 'toastDuration', 'How long a notification stays, ms' ),
				help: text( 'toastDurationHelp', '' ),
				type: 'number',
				min: 0,
				max: 15000,
				value: form.toast_duration,
				onChange: function onToastDuration( value ) {
					set( 'toast_duration', value );
				},
			} )
		),
		createElement( 'h3', null, text( 'toastTemplatesHeading', 'Notification texts' ) ),
		createElement( 'p', { className: 'description' }, text( 'toastTemplatesHelp', '' ) ),
		toastTemplates.map( function mapTemplate( template ) {
			return createElement(
				'div',
				{ key: template.key, 'data-testid': template.testid },
				createElement( TextControl, {
					label: template.label,
					help: template.help,
					value: form[ template.key ] || '',
					onChange: function onTemplate( value ) {
						set( template.key, value );
					},
				} )
			);
		} ),
		createElement(
			'p',
			{ className: 'rvn-compare-admin-actions' },
			createElement(
				Button,
				{
					variant: 'primary',
					isBusy: busy,
					disabled: busy,
					'data-testid': 'rvn-save-buttons',
					onClick: save,
				},
				text( 'save', 'Save changes' )
			),
			createElement(
				Button,
				{
					variant: 'secondary',
					disabled: busy,
					'data-testid': 'rvn-reset-buttons',
					onClick: resetTab,
				},
				text( 'resetTab', 'Reset this tab' )
			)
		)
	);
}

function App() {
	// Навигация по вкладкам — штатные nav-tab страницы: каждой вкладке свой
	// адрес. React рисует только панель активной вкладки.
	const active = typeof data.activeTab === 'string' && 'buttons' === data.activeTab ? 'buttons' : 'general';

	return createElement(
		'div',
		{ className: 'rvn-compare-admin-app', 'data-testid': 'rvn-admin-app' },
		'buttons' === active ? createElement( ButtonsPanel ) : createElement( GeneralPanel )
	);
}

( function mount() {
	const root = document.getElementById( 'rvn-compare-admin-root' );
	if ( ! root ) {
		return;
	}
	const app = createElement( App );
	if ( typeof createRoot === 'function' ) {
		createRoot( root ).render( app );
	} else {
		render( app, root );
	}
} )();
