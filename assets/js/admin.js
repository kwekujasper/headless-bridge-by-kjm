/* HeadlessBridge Admin JavaScript */
/* global headlessbridgeAdmin, jQuery */

( function ( $ ) {
	'use strict';

	const cfg = window.headlessbridgeAdmin || {};

	// ── Health check ────────────────────────────────────────────────

	$( '#headlessbridge-run-check' ).on( 'click', function () {
		const $btn     = $( this );
		const $results = $( '#headlessbridge-health-results' );

		$btn.text( cfg.i18n.checking ).prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action : 'headlessbridge_health_check',
			nonce  : cfg.healthNonce,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$results.html( renderHealthResults( response.data ) );
			} else {
				$results.html( '<p class="headlessbridge-status--fail">' + cfg.i18n.error + '</p>' );
			}
		} )
		.fail( function () {
			$results.html( '<p class="headlessbridge-status--fail">' + cfg.i18n.error + '</p>' );
		} )
		.always( function () {
			$btn.text( cfg.i18n.runCheck ).prop( 'disabled', false );
		} );
	} );

	// ── Clear cache ─────────────────────────────────────────────────

	$( '#headlessbridge-clear-cache' ).on( 'click', function () {
		const $btn = $( this );
		$btn.prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action : 'headlessbridge_clear_health_cache',
			nonce  : cfg.healthNonce,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				alert( cfg.i18n.cacheCleared );
			}
		} )
		.always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	// ── Render health results HTML ───────────────────────────────────

	function renderHealthResults( data ) {
		const labelMap = {
			wp_api   : 'WordPress REST API',
			graphql  : 'GraphQL Endpoint',
			frontend : 'Frontend Reachability',
			cors     : 'CORS Configuration',
			plugin   : 'Plugin Status',
		};

		let html = '<div class="headlessbridge-health-grid">';

		Object.entries( labelMap ).forEach( function ( [ key, label ] ) {
			if ( ! data[ key ] ) return;

			const check  = data[ key ];
			const ok     = check.ok;
			const detail = check.detail || '';

			let statusClass, statusIcon, statusText;
			if ( ok === true ) {
				statusClass = 'headlessbridge-status--pass';
				statusIcon  = '✓';
				statusText  = cfg.i18n.pass;
			} else if ( ok === false ) {
				statusClass = 'headlessbridge-status--fail';
				statusIcon  = '✗';
				statusText  = cfg.i18n.fail;
			} else {
				statusClass = 'headlessbridge-status--info';
				statusIcon  = '●';
				statusText  = cfg.i18n.info;
			}

			html += `
				<div class="headlessbridge-health-item">
					<span class="headlessbridge-health-label">${ escHtml( label ) }</span>
					<span class="headlessbridge-health-status ${ escHtml( statusClass ) }">
						<span class="headlessbridge-status-icon" aria-hidden="true">${ escHtml( statusIcon ) }</span>
						${ escHtml( statusText ) }
					</span>
					<span class="headlessbridge-health-detail">${ escHtml( detail ) }</span>
				</div>
			`;
		} );

		html += '</div>';

		if ( data.checked_at ) {
			html += `<p class="headlessbridge-health-timestamp">Last checked: ${ escHtml( data.checked_at ) }</p>`;
		}

		return html;
	}

	// ── Reset Settings confirmation modal ────────────────────────────

	const $resetOverlay = $( '#headlessbridge-reset-modal-overlay' );

	function openResetModal() {
		$( '#headlessbridge-reset-password' ).val( '' );
		$( '#headlessbridge-reset-modal-error' ).hide();
		$resetOverlay.show();
		$( '#headlessbridge-reset-password' ).trigger( 'focus' );
	}

	function closeResetModal() {
		$resetOverlay.hide();
	}

	$( '#headlessbridge-reset-open' ).on( 'click', openResetModal );
	$( '#headlessbridge-reset-cancel' ).on( 'click', closeResetModal );

	$resetOverlay.on( 'click', function ( e ) {
		if ( e.target === this ) {
			closeResetModal();
		}
	} );

	$( document ).on( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && $resetOverlay.is( ':visible' ) ) {
			closeResetModal();
		}
	} );

	$( '#headlessbridge-reset-password' ).on( 'keydown', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			$( '#headlessbridge-reset-confirm' ).trigger( 'click' );
		}
	} );

	$( '#headlessbridge-reset-confirm' ).on( 'click', function () {
		const password = $( '#headlessbridge-reset-password' ).val();

		if ( ! password ) {
			$( '#headlessbridge-reset-modal-error' ).show();
			return;
		}

		$( '#headlessbridge-reset-password-hidden' ).val( password );
		$( '#headlessbridge-reset-form' ).trigger( 'submit' );
	} );

	// ── Webhook builder ───────────────────────────────────────────────

	const $formWrap = $( '#headlessbridge-webhook-form-wrap' );
	const $formError = $( '#headlessbridge-webhook-form-error' );

	function resetWebhookForm() {
		$( '#headlessbridge-webhook-form-title' ).text( cfg.i18n.addWebhook );
		$( '#headlessbridge-webhook-id' ).val( '' );
		$( '#headlessbridge-webhook-name' ).val( '' );
		$( '.headlessbridge-webhook-trigger' ).prop( 'checked', false );
		$( '#headlessbridge-webhook-url' ).val( '' );
		$( '#headlessbridge-webhook-secret' ).val( '' ).attr( 'type', 'password' );
		$( '#headlessbridge-webhook-secret-toggle' ).text( cfg.i18n.showSecret );
		$( '#headlessbridge-webhook-secret-note' ).hide();
		$( '#headlessbridge-webhook-payload' ).val( '{"type":{{type}},"slug":{{slug}}}' );
		$( '#headlessbridge-webhook-enabled' ).prop( 'checked', true );
		$formError.hide().text( '' );
	}

	$( '#headlessbridge-webhook-add' ).on( 'click', function () {
		resetWebhookForm();
		$formWrap.show();
		$formWrap.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	} );

	// "Quick Setup" — pre-fills everything except URL/secret so a non-technical
	// user only has to fill in two fields to revalidate their frontend on any
	// content change. Checks every available trigger; the default payload
	// template (already in the field) is left untouched.
	$( '#headlessbridge-webhook-quick-setup' ).on( 'click', function () {
		resetWebhookForm();
		$( '#headlessbridge-webhook-name' ).val( cfg.i18n.quickSetupName );
		$( '.headlessbridge-webhook-trigger' ).prop( 'checked', true );
		$( '#headlessbridge-webhook-url' ).trigger( 'focus' );
		$formWrap.show();
		$formWrap.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	} );

	$( '#headlessbridge-webhook-cancel' ).on( 'click', function () {
		$formWrap.hide();
	} );

	$( document ).on( 'click', '.headlessbridge-webhook-edit', function () {
		const id = $( this ).closest( 'tr' ).data( 'webhook-id' );

		$.post( cfg.ajaxUrl, {
			action : 'headlessbridge_webhook_get',
			nonce  : cfg.webhooksNonce,
			id     : id,
		} )
		.done( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			const webhook = response.data;

			resetWebhookForm();
			$( '#headlessbridge-webhook-form-title' ).text( cfg.i18n.editWebhook );
			$( '#headlessbridge-webhook-id' ).val( webhook.id );
			$( '#headlessbridge-webhook-name' ).val( webhook.name );
			$( '#headlessbridge-webhook-url' ).val( webhook.url );
			$( '#headlessbridge-webhook-payload' ).val( webhook.payload );
			$( '#headlessbridge-webhook-enabled' ).prop( 'checked', !! webhook.enabled );

			( webhook.triggers || [] ).forEach( function ( key ) {
				$( '.headlessbridge-webhook-trigger[value="' + key + '"]' ).prop( 'checked', true );
			} );

			if ( webhook.has_secret ) {
				$( '#headlessbridge-webhook-secret-note' ).show();
			}

			$formWrap.show();
			$formWrap.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		} );
	} );

	$( '#headlessbridge-webhook-save' ).on( 'click', function () {
		const $btn = $( this );
		const triggers = $( '.headlessbridge-webhook-trigger:checked' ).map( function () {
			return $( this ).val();
		} ).get();

		if ( ! triggers.length ) {
			$formError.text( cfg.i18n.noTriggers ).show();
			return;
		}

		$btn.text( cfg.i18n.saving ).prop( 'disabled', true );
		$formError.hide();

		$.post( cfg.ajaxUrl, {
			action   : 'headlessbridge_webhook_save',
			nonce    : cfg.webhooksNonce,
			id       : $( '#headlessbridge-webhook-id' ).val(),
			name     : $( '#headlessbridge-webhook-name' ).val(),
			triggers : triggers,
			url      : $( '#headlessbridge-webhook-url' ).val(),
			secret   : $( '#headlessbridge-webhook-secret' ).val(),
			payload  : $( '#headlessbridge-webhook-payload' ).val(),
			enabled  : $( '#headlessbridge-webhook-enabled' ).is( ':checked' ) ? 1 : 0,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				window.location.reload();
				return;
			}
			$formError.text( response.data || cfg.i18n.error ).show();
		} )
		.fail( function () {
			$formError.text( cfg.i18n.error ).show();
		} )
		.always( function () {
			$btn.text( cfg.i18n.save ).prop( 'disabled', false );
		} );
	} );

	$( document ).on( 'click', '.headlessbridge-webhook-delete', function () {
		if ( ! window.confirm( cfg.i18n.confirmDelete ) ) {
			return;
		}

		const $row = $( this ).closest( 'tr' );
		const id   = $row.data( 'webhook-id' );
		const $btn = $( this );

		$btn.text( cfg.i18n.deleting ).prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action : 'headlessbridge_webhook_delete',
			nonce  : cfg.webhooksNonce,
			id     : id,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				window.location.reload();
			}
		} )
		.always( function () {
			$btn.text( cfg.i18n.delete ).prop( 'disabled', false );
		} );
	} );

	$( document ).on( 'click', '.headlessbridge-webhook-test', function () {
		const $row    = $( this ).closest( 'tr' );
		const id      = $row.data( 'webhook-id' );
		const $btn    = $( this );
		const $result = $row.find( '.headlessbridge-webhook-test-result' );

		$btn.text( cfg.i18n.sendingTest ).prop( 'disabled', true );
		$result.empty();

		$.post( cfg.ajaxUrl, {
			action : 'headlessbridge_webhook_test',
			nonce  : cfg.webhooksNonce,
			id     : id,
		} )
		.done( function ( response ) {
			const ok = response.success;
			const detail = ( response.data && response.data.detail ) || response.data || '';
			$result.html(
				'<span class="' + ( ok ? 'headlessbridge-status--pass' : 'headlessbridge-status--fail' ) + '">' +
				escHtml( ok ? cfg.i18n.testPass : cfg.i18n.testFail ) + ': ' + escHtml( String( detail ) ) +
				'</span>'
			);
		} )
		.fail( function () {
			$result.html( '<span class="headlessbridge-status--fail">' + escHtml( cfg.i18n.error ) + '</span>' );
		} )
		.always( function () {
			$btn.text( cfg.i18n.sendTest ).prop( 'disabled', false );
		} );
	} );

	$( '#headlessbridge-webhook-secret-toggle' ).on( 'click', function () {
		const $input = $( '#headlessbridge-webhook-secret' );
		const isPwd  = 'password' === $input.attr( 'type' );
		$input.attr( 'type', isPwd ? 'text' : 'password' );
		$( this ).text( isPwd ? cfg.i18n.hideSecret : cfg.i18n.showSecret );
	} );

	$( '#headlessbridge-webhook-secret-generate' ).on( 'click', function () {
		const $btn = $( this );
		$btn.text( cfg.i18n.generating ).prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action : 'headlessbridge_generate_secret',
			nonce  : cfg.webhooksNonce,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$( '#headlessbridge-webhook-secret' ).val( response.data.secret ).attr( 'type', 'text' );
				$( '#headlessbridge-webhook-secret-toggle' ).text( cfg.i18n.hideSecret );
				$( '#headlessbridge-webhook-secret-note' ).hide();
			}
		} )
		.always( function () {
			$btn.text( cfg.i18n.generateSecret ).prop( 'disabled', false );
		} );
	} );

	// ── Utility ─────────────────────────────────────────────────────

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	// ── Category picker (Content tab: homepage sections) ─────────────
	// A sortable <ul> of category checkboxes bound to a hidden field. On check
	// or drag, rebuild the field value = checked slugs in the list's current
	// order, newline-separated (the format PHP already stores). The menu list
	// (.hb-menu-list) is excluded — it has its own builder below.
	$( '.headlessbridge-cat-picker' ).not( '.hb-menu-list' ).each( function () {
		const $list  = $( this );
		const $field = $( '#' + $list.data( 'target' ) );

		function sync() {
			const slugs = [];
			$list.find( '.headlessbridge-cat-item' ).each( function () {
				const $cb = $( this ).find( 'input[type="checkbox"]' );
				if ( $cb.is( ':checked' ) ) {
					slugs.push( $cb.val() );
				}
			} );
			$field.val( slugs.join( '\n' ) );
		}

		if ( $.fn.sortable ) {
			$list.sortable( {
				handle               : '.headlessbridge-cat-handle',
				axis                 : 'y',
				placeholder          : 'headlessbridge-cat-placeholder',
				forcePlaceholderSize : true,
				update               : sync,
			} );
		}

		$list.on( 'change', 'input[type="checkbox"]', sync );
		// Safety net: resync on submit in case the browser restored state.
		$list.closest( 'form' ).on( 'submit', sync );
	} );

	// ── Menu builder (Content tab): categories + custom links, one order ──
	// A sortable list mixing category checkboxes and custom-link rows (label +
	// URL). On any change/drag/add/remove, rebuild the hidden field as ordered
	// newline tokens: "category:<slug>" for checked categories, "link:<label>|
	// <url>" for links with both fields filled. Malformed rows are skipped
	// here and re-validated server-side.
	$( '.headlessbridge-menu-builder' ).each( function () {
		const $wrap  = $( this );
		const $list  = $wrap.find( '.hb-menu-list' );
		const $field = $( '#' + $wrap.data( 'target' ) );

		function sync() {
			const tokens = [];
			$list.children( '.hb-menu-item' ).each( function () {
				const $li = $( this );
				if ( 'category' === $li.attr( 'data-type' ) ) {
					if ( $li.find( 'input[type="checkbox"]' ).is( ':checked' ) ) {
						tokens.push( 'category:' + $li.attr( 'data-slug' ) );
					}
				} else {
					const label = ( $li.find( '.hb-link-label' ).val() || '' ).replace( /\|/g, '' ).trim();
					const url   = ( $li.find( '.hb-link-url' ).val() || '' ).trim();
					if ( label && url ) {
						tokens.push( 'link:' + label + '|' + url );
					}
				}
			} );
			$field.val( tokens.join( '\n' ) );
		}

		if ( $.fn.sortable ) {
			$list.sortable( {
				handle               : '.headlessbridge-cat-handle',
				axis                 : 'y',
				placeholder          : 'headlessbridge-cat-placeholder',
				forcePlaceholderSize : true,
				update               : sync,
			} );
		}

		$list.on( 'change input', 'input', sync );

		$wrap.on( 'click', '.hb-add-link', function () {
			$list.append(
				'<li class="headlessbridge-cat-item hb-menu-item" data-type="link">'
				+ '<span class="headlessbridge-cat-handle" aria-hidden="true">&#9776;</span>'
				+ '<input type="text" class="hb-link-label" placeholder="Label" />'
				+ '<input type="text" class="hb-link-url" placeholder="/about or https://…" />'
				+ '<button type="button" class="button-link hb-link-remove" aria-label="Remove link">&times;</button>'
				+ '</li>'
			);
			$list.find( '.hb-menu-item' ).last().find( '.hb-link-label' ).trigger( 'focus' );
		} );

		$wrap.on( 'click', '.hb-link-remove', function () {
			$( this ).closest( '.hb-menu-item' ).remove();
			sync();
		} );

		$wrap.closest( 'form' ).on( 'submit', sync );
	} );

} )( jQuery );
