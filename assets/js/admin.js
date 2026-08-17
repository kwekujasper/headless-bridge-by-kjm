/* KJMHCG Admin JavaScript */
/* global kjmhcgAdmin, jQuery */

( function ( $ ) {
	'use strict';

	const cfg = window.kjmhcgAdmin || {};

	// ── Health check ────────────────────────────────────────────────

	$( '#kjmhcg-run-check' ).on( 'click', function () {
		const $btn     = $( this );
		const $results = $( '#kjmhcg-health-results' );

		$btn.text( cfg.i18n.checking ).prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action : 'kjmhcg_health_check',
			nonce  : cfg.healthNonce,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$results.html( renderHealthResults( response.data ) );
			} else {
				$results.html( '<p class="kjmhcg-status--fail">' + cfg.i18n.error + '</p>' );
			}
		} )
		.fail( function () {
			$results.html( '<p class="kjmhcg-status--fail">' + cfg.i18n.error + '</p>' );
		} )
		.always( function () {
			$btn.text( cfg.i18n.runCheck ).prop( 'disabled', false );
		} );
	} );

	// ── Clear cache ─────────────────────────────────────────────────

	$( '#kjmhcg-clear-cache' ).on( 'click', function () {
		const $btn = $( this );
		$btn.prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action : 'kjmhcg_clear_health_cache',
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

		let html = '<div class="kjmhcg-health-grid">';

		Object.entries( labelMap ).forEach( function ( [ key, label ] ) {
			if ( ! data[ key ] ) return;

			const check  = data[ key ];
			const ok     = check.ok;
			const detail = check.detail || '';

			let statusClass, statusIcon, statusText;
			if ( ok === true ) {
				statusClass = 'kjmhcg-status--pass';
				statusIcon  = '✓';
				statusText  = cfg.i18n.pass;
			} else if ( ok === false ) {
				statusClass = 'kjmhcg-status--fail';
				statusIcon  = '✗';
				statusText  = cfg.i18n.fail;
			} else {
				statusClass = 'kjmhcg-status--info';
				statusIcon  = '●';
				statusText  = cfg.i18n.info;
			}

			html += `
				<div class="kjmhcg-health-item">
					<span class="kjmhcg-health-label">${ escHtml( label ) }</span>
					<span class="kjmhcg-health-status ${ escHtml( statusClass ) }">
						<span class="kjmhcg-status-icon" aria-hidden="true">${ escHtml( statusIcon ) }</span>
						${ escHtml( statusText ) }
					</span>
					<span class="kjmhcg-health-detail">${ escHtml( detail ) }</span>
				</div>
			`;
		} );

		html += '</div>';

		if ( data.checked_at ) {
			html += `<p class="kjmhcg-health-timestamp">Last checked: ${ escHtml( data.checked_at ) }</p>`;
		}

		return html;
	}

	// ── Reset Settings confirmation modal ────────────────────────────

	const $resetOverlay = $( '#kjmhcg-reset-modal-overlay' );

	function openResetModal() {
		$( '#kjmhcg-reset-password' ).val( '' );
		$( '#kjmhcg-reset-modal-error' ).hide();
		$resetOverlay.show();
		$( '#kjmhcg-reset-password' ).trigger( 'focus' );
	}

	function closeResetModal() {
		$resetOverlay.hide();
	}

	$( '#kjmhcg-reset-open' ).on( 'click', openResetModal );
	$( '#kjmhcg-reset-cancel' ).on( 'click', closeResetModal );

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

	$( '#kjmhcg-reset-password' ).on( 'keydown', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			$( '#kjmhcg-reset-confirm' ).trigger( 'click' );
		}
	} );

	$( '#kjmhcg-reset-confirm' ).on( 'click', function () {
		const password = $( '#kjmhcg-reset-password' ).val();

		if ( ! password ) {
			$( '#kjmhcg-reset-modal-error' ).show();
			return;
		}

		$( '#kjmhcg-reset-password-hidden' ).val( password );
		$( '#kjmhcg-reset-form' ).trigger( 'submit' );
	} );

	// ── Webhook builder ───────────────────────────────────────────────

	const $formWrap = $( '#kjmhcg-webhook-form-wrap' );
	const $formError = $( '#kjmhcg-webhook-form-error' );

	function resetWebhookForm() {
		$( '#kjmhcg-webhook-form-title' ).text( cfg.i18n.addWebhook );
		$( '#kjmhcg-webhook-id' ).val( '' );
		$( '#kjmhcg-webhook-name' ).val( '' );
		$( '.kjmhcg-webhook-trigger' ).prop( 'checked', false );
		$( '#kjmhcg-webhook-url' ).val( '' );
		$( '#kjmhcg-webhook-secret' ).val( '' ).attr( 'type', 'password' );
		$( '#kjmhcg-webhook-secret-toggle' ).text( cfg.i18n.showSecret );
		$( '#kjmhcg-webhook-secret-note' ).hide();
		$( '#kjmhcg-webhook-payload' ).val( '{"type":{{type}},"slug":{{slug}}}' );
		$( '#kjmhcg-webhook-enabled' ).prop( 'checked', true );
		$formError.hide().text( '' );
	}

	$( '#kjmhcg-webhook-add' ).on( 'click', function () {
		resetWebhookForm();
		$formWrap.show();
		$formWrap.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	} );

	// "Quick Setup" — pre-fills everything except URL/secret so a non-technical
	// user only has to fill in two fields to revalidate their frontend on any
	// content change. Checks every available trigger; the default payload
	// template (already in the field) is left untouched.
	$( '#kjmhcg-webhook-quick-setup' ).on( 'click', function () {
		resetWebhookForm();
		$( '#kjmhcg-webhook-name' ).val( cfg.i18n.quickSetupName );
		$( '.kjmhcg-webhook-trigger' ).prop( 'checked', true );
		$( '#kjmhcg-webhook-url' ).trigger( 'focus' );
		$formWrap.show();
		$formWrap.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	} );

	$( '#kjmhcg-webhook-cancel' ).on( 'click', function () {
		$formWrap.hide();
	} );

	$( document ).on( 'click', '.kjmhcg-webhook-edit', function () {
		const id = $( this ).closest( 'tr' ).data( 'webhook-id' );

		$.post( cfg.ajaxUrl, {
			action : 'kjmhcg_webhook_get',
			nonce  : cfg.webhooksNonce,
			id     : id,
		} )
		.done( function ( response ) {
			if ( ! response.success ) {
				return;
			}

			const webhook = response.data;

			resetWebhookForm();
			$( '#kjmhcg-webhook-form-title' ).text( cfg.i18n.editWebhook );
			$( '#kjmhcg-webhook-id' ).val( webhook.id );
			$( '#kjmhcg-webhook-name' ).val( webhook.name );
			$( '#kjmhcg-webhook-url' ).val( webhook.url );
			$( '#kjmhcg-webhook-payload' ).val( webhook.payload );
			$( '#kjmhcg-webhook-enabled' ).prop( 'checked', !! webhook.enabled );

			( webhook.triggers || [] ).forEach( function ( key ) {
				$( '.kjmhcg-webhook-trigger[value="' + key + '"]' ).prop( 'checked', true );
			} );

			if ( webhook.has_secret ) {
				$( '#kjmhcg-webhook-secret-note' ).show();
			}

			$formWrap.show();
			$formWrap.get( 0 ).scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		} );
	} );

	$( '#kjmhcg-webhook-save' ).on( 'click', function () {
		const $btn = $( this );
		const triggers = $( '.kjmhcg-webhook-trigger:checked' ).map( function () {
			return $( this ).val();
		} ).get();

		if ( ! triggers.length ) {
			$formError.text( cfg.i18n.noTriggers ).show();
			return;
		}

		$btn.text( cfg.i18n.saving ).prop( 'disabled', true );
		$formError.hide();

		$.post( cfg.ajaxUrl, {
			action   : 'kjmhcg_webhook_save',
			nonce    : cfg.webhooksNonce,
			id       : $( '#kjmhcg-webhook-id' ).val(),
			name     : $( '#kjmhcg-webhook-name' ).val(),
			triggers : triggers,
			url      : $( '#kjmhcg-webhook-url' ).val(),
			secret   : $( '#kjmhcg-webhook-secret' ).val(),
			payload  : $( '#kjmhcg-webhook-payload' ).val(),
			enabled  : $( '#kjmhcg-webhook-enabled' ).is( ':checked' ) ? 1 : 0,
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

	$( document ).on( 'click', '.kjmhcg-webhook-delete', function () {
		if ( ! window.confirm( cfg.i18n.confirmDelete ) ) {
			return;
		}

		const $row = $( this ).closest( 'tr' );
		const id   = $row.data( 'webhook-id' );
		const $btn = $( this );

		$btn.text( cfg.i18n.deleting ).prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action : 'kjmhcg_webhook_delete',
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

	$( document ).on( 'click', '.kjmhcg-webhook-test', function () {
		const $row    = $( this ).closest( 'tr' );
		const id      = $row.data( 'webhook-id' );
		const $btn    = $( this );
		const $result = $row.find( '.kjmhcg-webhook-test-result' );

		$btn.text( cfg.i18n.sendingTest ).prop( 'disabled', true );
		$result.empty();

		$.post( cfg.ajaxUrl, {
			action : 'kjmhcg_webhook_test',
			nonce  : cfg.webhooksNonce,
			id     : id,
		} )
		.done( function ( response ) {
			const ok = response.success;
			const detail = ( response.data && response.data.detail ) || response.data || '';
			$result.html(
				'<span class="' + ( ok ? 'kjmhcg-status--pass' : 'kjmhcg-status--fail' ) + '">' +
				escHtml( ok ? cfg.i18n.testPass : cfg.i18n.testFail ) + ': ' + escHtml( String( detail ) ) +
				'</span>'
			);
		} )
		.fail( function () {
			$result.html( '<span class="kjmhcg-status--fail">' + escHtml( cfg.i18n.error ) + '</span>' );
		} )
		.always( function () {
			$btn.text( cfg.i18n.sendTest ).prop( 'disabled', false );
		} );
	} );

	$( '#kjmhcg-webhook-secret-toggle' ).on( 'click', function () {
		const $input = $( '#kjmhcg-webhook-secret' );
		const isPwd  = 'password' === $input.attr( 'type' );
		$input.attr( 'type', isPwd ? 'text' : 'password' );
		$( this ).text( isPwd ? cfg.i18n.hideSecret : cfg.i18n.showSecret );
	} );

	$( '#kjmhcg-webhook-secret-generate' ).on( 'click', function () {
		const $btn = $( this );
		$btn.text( cfg.i18n.generating ).prop( 'disabled', true );

		$.post( cfg.ajaxUrl, {
			action : 'kjmhcg_generate_secret',
			nonce  : cfg.webhooksNonce,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$( '#kjmhcg-webhook-secret' ).val( response.data.secret ).attr( 'type', 'text' );
				$( '#kjmhcg-webhook-secret-toggle' ).text( cfg.i18n.hideSecret );
				$( '#kjmhcg-webhook-secret-note' ).hide();
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
	$( '.kjmhcg-cat-picker' ).not( '.hb-menu-list' ).each( function () {
		const $list  = $( this );
		const $field = $( '#' + $list.data( 'target' ) );

		function sync() {
			const slugs = [];
			$list.find( '.kjmhcg-cat-item' ).each( function () {
				const $cb = $( this ).find( 'input[type="checkbox"]' );
				if ( $cb.is( ':checked' ) ) {
					slugs.push( $cb.val() );
				}
			} );
			$field.val( slugs.join( '\n' ) );
		}

		if ( $.fn.sortable ) {
			$list.sortable( {
				handle               : '.kjmhcg-cat-handle',
				axis                 : 'y',
				placeholder          : 'kjmhcg-cat-placeholder',
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
	$( '.kjmhcg-menu-builder' ).each( function () {
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
				handle               : '.kjmhcg-cat-handle',
				axis                 : 'y',
				placeholder          : 'kjmhcg-cat-placeholder',
				forcePlaceholderSize : true,
				update               : sync,
			} );
		}

		$list.on( 'change input', 'input', sync );

		$wrap.on( 'click', '.hb-add-link', function () {
			const labelPlaceholder = escHtml( ( cfg.i18n && cfg.i18n.linkLabel ) || 'Label' );
			const urlPlaceholder   = escHtml( ( cfg.i18n && cfg.i18n.linkUrl ) || '/about or https://…' );
			$list.append(
				'<li class="kjmhcg-cat-item hb-menu-item" data-type="link">'
				+ '<span class="kjmhcg-cat-handle" aria-hidden="true">&#9776;</span>'
				+ '<input type="text" class="hb-link-label" placeholder="' + labelPlaceholder + '" />'
				+ '<input type="text" class="hb-link-url" placeholder="' + urlPlaceholder + '" />'
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
